<?php

namespace App\Http\Controllers;

use App\Models\BannedBrowser;
use App\Models\BannedOperatingSystem;
use App\Models\BannedUser;
use App\Models\User;
use App\Models\Room;
use App\Events\UserPresence;
use App\Events\SystemMessage;
use App\Services\UserAgentService;
use App\Services\IpGeolocationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BanController extends Controller
{
    protected $userAgentService;
    protected $ipService;

    public function __construct()
    {
        $this->userAgentService = new UserAgentService();
        $this->ipService = new IpGeolocationService();
    }

    // ==================== Banned Browsers ====================

    /**
     * Get all banned browsers.
     */
    public function getBannedBrowsers(): JsonResponse
    {
        $browsers = BannedBrowser::with('bannedBy')->orderBy('browser_name')->get();
        return response()->json($browsers);
    }

    /**
     * Ban a browser.
     */
    public function banBrowser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'browser_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $browser = BannedBrowser::firstOrCreate(
            ['browser_name' => $validated['browser_name']],
            [
                'description' => $validated['description'] ?? null,
                'banned_by' => $request->user()->id,
            ]
        );

        $browser->load('bannedBy');

        return response()->json($browser, 201);
    }

    /**
     * Unban a browser.
     */
    public function unbanBrowser(string $id): JsonResponse
    {
        $browser = BannedBrowser::findOrFail($id);
        $browser->delete();

        return response()->json(['message' => 'Browser unbanned successfully']);
    }

    // ==================== Banned Operating Systems ====================

    /**
     * Get all banned operating systems.
     */
    public function getBannedOperatingSystems(): JsonResponse
    {
        $os = BannedOperatingSystem::with('bannedBy')->orderBy('os_name')->get();
        return response()->json($os);
    }

    /**
     * Ban an operating system.
     */
    public function banOperatingSystem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'os_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $os = BannedOperatingSystem::firstOrCreate(
            ['os_name' => $validated['os_name']],
            [
                'description' => $validated['description'] ?? null,
                'banned_by' => $request->user()->id,
            ]
        );

        $os->load('bannedBy');

        return response()->json($os, 201);
    }

    /**
     * Unban an operating system.
     */
    public function unbanOperatingSystem(string $id): JsonResponse
    {
        $os = BannedOperatingSystem::findOrFail($id);
        $os->delete();

        return response()->json(['message' => 'Operating system unbanned successfully']);
    }

    // ==================== Banned Users ====================

    /**
     * Get all banned users.
     */
    public function getBannedUsers(Request $request): JsonResponse
    {
        $query = BannedUser::with(['user', 'bannedBy'])
            ->orderBy('banned_at', 'desc');

        // Filter by active/inactive
        if ($request->has('active_only')) {
            if ($request->boolean('active_only')) {
                $query->active();
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('account_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $bannedUsers = $query->paginate($perPage);

        return response()->json($bannedUsers);
    }

    /**
     * Ban a user.
     */
    public function banUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string',
            'is_permanent' => 'boolean',
            'ends_at' => 'nullable|date|after:now',
        ]);

        $user = User::findOrFail($validated['user_id']);

        // Check if user is already banned
        $existingBan = BannedUser::where('user_id', $user->id)->active()->first();
        if ($existingBan) {
            return response()->json(['message' => 'User is already banned'], 400);
        }

        // Get IP and country from request
        // If IP is null (e.g., from programmatic ban), try to get from user's stored IP
        $ipAddress = $this->ipService->getClientIp($request);
        if (!$ipAddress && $user->ip_address) {
            $ipAddress = $user->ip_address;
        }
        // Fallback to a default if still null
        if (!$ipAddress) {
            $ipAddress = '0.0.0.0'; // Placeholder for unknown IP
        }
        
        $country = $this->ipService->getCountryFromIp($ipAddress);
        if (!$country && $user->country) {
            $country = $user->country;
        }
        
        $userAgent = $request->userAgent();
        $device = $userAgent ? $this->userAgentService->detectBrowser($userAgent) : 'Unknown';

        $bannedUser = BannedUser::create([
            'user_id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'banned_by' => $request->user()->id,
            'reason' => $validated['reason'] ?? null,
            'device' => $device,
            'ip_address' => $ipAddress,
            'account_name' => $user->username,
            'country' => $country,
            'banned_at' => now(),
            'ends_at' => $validated['is_permanent'] ? null : ($validated['ends_at'] ?? null),
            'is_permanent' => $validated['is_permanent'] ?? false,
        ]);

        // Update user's is_blocked status
        $user->update(['is_blocked' => true]);

        // Revoke all tokens for the banned user to force logout
        $user->tokens()->delete();

        // Remove user from all rooms
        $userRooms = DB::table('room_user')->where('user_id', $user->id)->get();
        foreach ($userRooms as $userRoom) {
            $room = Room::find($userRoom->room_id);
            if ($room) {
                $room->users()->detach($user->id);
                // Broadcast user left message to all rooms
                broadcast(new \App\Events\SystemMessage($user, (int)$userRoom->room_id, 'left'));
                broadcast(new \App\Events\UserPresence($user, 'offline', (int)$userRoom->room_id))->toOthers();
            }
        }

        $bannedUser->load(['user', 'bannedBy']);

        // Broadcast ban event to the banned user
        broadcast(new \App\Events\UserBanned($user, $bannedUser));

        return response()->json($bannedUser, 201);
    }

    /**
     * Ban a user due to rate limit violation (self-ban by system).
     */
    public function banUserRateLimit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ban_minutes' => 'required|integer|min:0',
        ]);

        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if user is already banned
        $existingBan = BannedUser::where('user_id', $user->id)->active()->first();
        if ($existingBan) {
            return response()->json(['message' => 'User is already banned'], 400);
        }

        // Get IP and country from request
        $ipAddress = $this->ipService->getClientIp($request);
        if (!$ipAddress && $user->ip_address) {
            $ipAddress = $user->ip_address;
        }
        if (!$ipAddress) {
            $ipAddress = '0.0.0.0';
        }
        
        $country = $this->ipService->getCountryFromIp($ipAddress);
        if (!$country && $user->country) {
            $country = $user->country;
        }
        
        $userAgent = $request->userAgent();
        $device = $userAgent ? $this->userAgentService->detectBrowser($userAgent) : 'Unknown';

        // Calculate ends_at based on ban_minutes
        $endsAt = null;
        $isPermanent = false;
        if ($validated['ban_minutes'] > 0) {
            $endsAt = now()->addMinutes($validated['ban_minutes']);
        } else {
            // If 0 minutes, it's a kick (no ban record needed, but we'll create one for tracking)
            // Actually, if 0, we shouldn't create a ban record - just kick
            return response()->json([
                'message' => 'User will be kicked (no ban record)',
                'kick_only' => true
            ]);
        }

        $bannedUser = BannedUser::create([
            'user_id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'banned_by' => $user->id, // Self-ban by system (rate limit)
            'reason' => 'Rate limit violation - Excessive requests',
            'device' => $device,
            'ip_address' => $ipAddress,
            'account_name' => $user->username,
            'country' => $country,
            'banned_at' => now(),
            'ends_at' => $endsAt,
            'is_permanent' => $isPermanent,
        ]);

        // Update user's is_blocked status
        $user->update(['is_blocked' => true]);

        // Revoke all tokens for the banned user to force logout
        $user->tokens()->delete();

        // Remove user from all rooms
        $userRooms = DB::table('room_user')->where('user_id', $user->id)->get();
        foreach ($userRooms as $userRoom) {
            $room = Room::find($userRoom->room_id);
            if ($room) {
                $room->users()->detach($user->id);
                // Broadcast user left message to all rooms
                broadcast(new \App\Events\SystemMessage($user, (int)$userRoom->room_id, 'left'));
                broadcast(new \App\Events\UserPresence($user, 'offline', (int)$userRoom->room_id))->toOthers();
            }
        }

        $bannedUser->load(['user', 'bannedBy']);

        // Broadcast ban event to the banned user
        broadcast(new \App\Events\UserBanned($user, $bannedUser));

        return response()->json([
            'message' => 'User banned due to rate limit violation',
            'ban' => $bannedUser,
        ]);

        // Remove user from all rooms
        $userRooms = DB::table('room_user')->where('user_id', $user->id)->get();
        foreach ($userRooms as $userRoom) {
            $room = Room::find($userRoom->room_id);
            if ($room) {
                $room->users()->detach($user->id);
                // Broadcast user left message to all rooms
                broadcast(new \App\Events\SystemMessage($user, (int)$userRoom->room_id, 'left'));
                broadcast(new \App\Events\UserPresence($user, 'offline', (int)$userRoom->room_id))->toOthers();
            }
        }

        $bannedUser->load(['user', 'bannedBy']);

        // Broadcast ban event to the banned user
        broadcast(new \App\Events\UserBanned($user, $bannedUser));

        return response()->json($bannedUser, 201);
    }

    /**
     * Unban a user.
     */
    public function unbanUser(string $id): JsonResponse
    {
        $bannedUser = BannedUser::findOrFail($id);
        $user = $bannedUser->user;
        
        // Delete the ban record
        $bannedUser->delete();
        
        // Update user's is_blocked status to false
        if ($user) {
            $user->update(['is_blocked' => false]);
        }

        return response()->json(['message' => 'User unbanned successfully']);
    }

    /**
     * Check if browser/OS/user is banned (for auth endpoints).
     */
    public function checkBan(Request $request): JsonResponse
    {
        $userAgent = $request->userAgent();
        $deviceInfo = $this->userAgentService->getDeviceInfo($userAgent);

        // Check browser ban
        if ($deviceInfo['browser']) {
            $bannedBrowser = BannedBrowser::where('browser_name', $deviceInfo['browser'])->first();
            if ($bannedBrowser) {
                return response()->json([
                    'banned' => true,
                    'type' => 'browser',
                    'message' => 'Request rejected.',
                ], 403);
            }
        }

        // Check OS ban
        if ($deviceInfo['os']) {
            $bannedOS = BannedOperatingSystem::where('os_name', $deviceInfo['os'])->first();
            if ($bannedOS) {
                return response()->json([
                    'banned' => true,
                    'type' => 'os',
                    'message' => 'Request rejected.',
                ], 403);
            }
        }

        // Check user ban (if user is authenticated)
        if ($request->user()) {
            $bannedUser = BannedUser::where('user_id', $request->user()->id)->active()->first();
            if ($bannedUser) {
                return response()->json([
                    'banned' => true,
                    'type' => 'user',
                    'message' => 'Request rejected.',
                    'ends_at' => $bannedUser->ends_at?->toISOString(),
                    'is_permanent' => $bannedUser->is_permanent,
                ], 403);
            }
        }

        return response()->json(['banned' => false]);
    }
}
