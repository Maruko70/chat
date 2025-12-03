<?php

namespace App\Http\Controllers;

use App\Models\FilteredWordViolation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserWarning;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FilteredWordViolationController extends Controller
{
    /**
     * Display a listing of violations.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = FilteredWordViolation::with(['user', 'filteredWord', 'message', 'reviewer']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by content type
        if ($request->has('content_type')) {
            $query->where('content_type', $request->content_type);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Search by content
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('original_content', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $violations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($violations);
    }

    /**
     * Display the specified violation.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $violation = FilteredWordViolation::with(['user', 'filteredWord', 'message', 'reviewer'])
            ->findOrFail($id);

        return response()->json($violation);
    }

    /**
     * Update violation status and take action.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $violation = FilteredWordViolation::with(['user', 'message', 'filteredWord'])->findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,reviewed,action_taken,dismissed',
            'action_taken' => 'sometimes|string|max:500',
            'notes' => 'sometimes|nullable|string|max:1000',
            'ban_user' => 'sometimes|boolean',
            'ban_duration' => 'sometimes|nullable|integer|min:1', // days, null = permanent
            'delete_message' => 'sometimes|boolean',
            'warn_user' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Update violation
            $violation->update([
                'status' => $validated['status'] ?? $violation->status,
                'action_taken' => $validated['action_taken'] ?? $violation->action_taken,
                'notes' => $validated['notes'] ?? $violation->notes,
                'reviewed_by' => $currentUser->id,
                'reviewed_at' => now(),
            ]);

            // Take actions
            $actionsTaken = [];

            // Ban user
            if ($validated['ban_user'] ?? false) {
                $user = $violation->user;
                if (!$user) {
                    $actionsTaken[] = 'Failed to ban user: User not found';
                } else {
                    $banDuration = $validated['ban_duration'] ?? null;
                    
                    try {
                        // Check if user is already banned
                        $existingBan = \App\Models\BannedUser::where('user_id', $user->id)->active()->first();
                        if ($existingBan) {
                            $actionsTaken[] = 'User is already banned';
                        } else {
                            // Get IP and country from request or user
                            $ipService = new \App\Services\IpGeolocationService();
                            $ipAddress = $ipService->getClientIp($request);
                            if (!$ipAddress && $user->ip_address) {
                                $ipAddress = $user->ip_address;
                            }
                            if (!$ipAddress) {
                                $ipAddress = '0.0.0.0'; // Placeholder for unknown IP
                            }
                            
                            $country = $ipService->getCountryFromIp($ipAddress);
                            if (!$country && $user->country) {
                                $country = $user->country;
                            }
                            
                            $userAgentService = new \App\Services\UserAgentService();
                            $userAgent = $request->userAgent();
                            $device = $userAgent ? $userAgentService->detectBrowser($userAgent) : 'Unknown';
                            
                            // Calculate ends_at for temporary bans
                            $endsAt = null;
                            if ($banDuration !== null && $banDuration > 0) {
                                $endsAt = now()->addDays($banDuration);
                            }
                            
                            // Create ban record
                            $bannedUser = \App\Models\BannedUser::create([
                                'user_id' => $user->id,
                                'username' => $user->username,
                                'name' => $user->name,
                                'banned_by' => $currentUser->id,
                                'reason' => 'Violation of filtered words policy: ' . ($violation->filteredWord->word ?? 'unknown'),
                                'device' => $device,
                                'ip_address' => $ipAddress,
                                'account_name' => $user->username,
                                'country' => $country,
                                'banned_at' => now(),
                                'ends_at' => $endsAt,
                                'is_permanent' => $banDuration === null || $banDuration === 0,
                            ]);
                            
                            // Update user's is_blocked status
                            $user->update(['is_blocked' => true]);
                            
                            // Revoke all tokens for the banned user to force logout
                            $user->tokens()->delete();
                            
                            // Remove user from all rooms
                            $userRooms = DB::table('room_user')->where('user_id', $user->id)->get();
                            foreach ($userRooms as $userRoom) {
                                $room = \App\Models\Room::find($userRoom->room_id);
                                if ($room) {
                                    $room->users()->detach($user->id);
                                    // Broadcast user left message to all rooms
                                    broadcast(new \App\Events\SystemMessage($user, (int)$userRoom->room_id, 'left'));
                                    broadcast(new \App\Events\UserPresence($user, 'offline', (int)$userRoom->room_id))->toOthers();
                                }
                            }
                            
                            // Broadcast ban event to the banned user
                            broadcast(new \App\Events\UserBanned($user, $bannedUser));
                            
                            $actionsTaken[] = $banDuration ? "Banned user for {$banDuration} days" : 'Permanently banned user';
                        }
                    } catch (\Exception $e) {
                        // Log error but continue
                        \Illuminate\Support\Facades\Log::error('Failed to ban user from violation', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'user_id' => $user->id,
                        ]);
                        $actionsTaken[] = 'Failed to ban user: ' . $e->getMessage();
                    }
                }
            }

            // Delete message
            if (($validated['delete_message'] ?? false) && $violation->message_id) {
                $message = Message::find($violation->message_id);
                if ($message) {
                    $message->delete();
                    $actionsTaken[] = 'Deleted message';
                }
            }

            // Warn user
            if ($validated['warn_user'] ?? false) {
                $user = $violation->user;
                $reason = $validated['notes'] ?? 'Violation of filtered words policy: ' . ($violation->filteredWord->word ?? 'unknown');
                
                // Create warning record
                UserWarning::create([
                    'user_id' => $user->id,
                    'warned_by' => $currentUser->id,
                    'reason' => $reason,
                    'violation_id' => $violation->id,
                    'type' => 'violation',
                    'is_read' => false,
                ]);
                
                $warningCount = UserWarning::getWarningCount($user->id);
                $actionsTaken[] = "User warned (Total warnings: {$warningCount})";
                
                // Optional: Auto-ban after X warnings (e.g., 3 warnings = ban)
                // You can configure this threshold in settings
                $maxWarnings = 3; // Could be from site settings
                if ($warningCount >= $maxWarnings) {
                    try {
                        $banController = new BanController();
                        $banRequest = Request::create('/api/bans/users', 'POST', [
                            'user_id' => $user->id,
                            'reason' => "Auto-banned after {$warningCount} warnings for filtered word violations",
                            'is_permanent' => false,
                            'ends_at' => now()->addDays(7)->toDateTimeString(), // 7 day ban
                        ]);
                        
                        // Copy headers from original request for IP detection
                        foreach ($request->headers->all() as $key => $value) {
                            $banRequest->headers->set($key, $value);
                        }
                        
                        $banRequest->setUserResolver(function () use ($currentUser) {
                            return $currentUser;
                        });
                        
                        // Set the request instance for proper IP detection
                        $banRequest->server->set('REQUEST_METHOD', 'POST');
                        if ($request->server->has('REMOTE_ADDR')) {
                            $banRequest->server->set('REMOTE_ADDR', $request->server->get('REMOTE_ADDR'));
                        }
                        
                        $banController->banUser($banRequest);
                        $actionsTaken[] = "Auto-banned user after {$warningCount} warnings";
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to auto-ban user after warnings', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'user_id' => $user->id,
                        ]);
                    }
                }
            }

            // Update action_taken with all actions
            if (!empty($actionsTaken)) {
                $violation->update([
                    'action_taken' => implode(', ', $actionsTaken),
                    'status' => 'action_taken',
                ]);
            }

            DB::commit();

            $violation->load(['user', 'filteredWord', 'message', 'reviewer']);

            return response()->json($violation);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update violation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get violation statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_violations' => FilteredWordViolation::count(),
            'pending_violations' => FilteredWordViolation::where('status', 'pending')->count(),
            'by_content_type' => FilteredWordViolation::select('content_type', DB::raw('count(*) as count'))
                ->groupBy('content_type')
                ->get()
                ->pluck('count', 'content_type'),
            'by_status' => FilteredWordViolation::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status'),
            'top_offenders' => FilteredWordViolation::select('user_id', DB::raw('count(*) as count'))
                ->groupBy('user_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->with('user:id,username,name')
                ->get(),
        ];

        return response()->json($stats);
    }
}

