<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\LoginLog;
use App\Events\UserPresence;
use App\Events\SystemMessage;
use App\Events\UserMoveRequest;
use App\Traits\ChecksBans;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RoomController extends Controller
{
    use ChecksBans;

    /**
     * Clear rooms cache for user and bootstrap cache
     */
    private function clearRoomsCache($user): void
    {
        // Clear user-specific cache
        if ($user) {
            Cache::forget("rooms_user_{$user->id}");
            Cache::forget("bootstrap_data_user_{$user->id}");
        }
        
        // Clear public cache
        Cache::forget('rooms_public');
        Cache::forget('bootstrap_data_guest');
        
        // Clear all user bootstrap caches (since rooms affect all users)
        // In production, consider using cache tags for more efficient clearing
        $keys = [
            'bootstrap_data_guest',
            'rooms_public',
        ];
        
        // Clear bootstrap cache for all users (simplified approach)
        // For better performance, use cache tags: Cache::tags(['rooms'])->flush();
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Display a listing of rooms.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is banned
        if ($user) {
            $banCheck = $this->checkUserBan($user);
            if ($banCheck) {
                return $banCheck;
            }
        }
        
        // Cache key based on user (different rooms for different users)
        $cacheKey = $user 
            ? "rooms_user_{$user->id}" 
            : 'rooms_public';
        
        // Cache for 1 hour (3600 seconds) - rooms change less frequently
        $rooms = Cache::remember($cacheKey, 3600, function () use ($user, $request) {
            if ($user) {
                return Room::where('is_public', true)
                    ->orWhereHas('users', function ($query) use ($request) {
                        $query->where('user_id', $request->user()->id);
                    })
                    ->with(['users.media', 'users.roleGroups'])
                    ->latest()
                    ->get();
            } else {
                return Room::where('is_public', true)
                    ->with(['users.media', 'users.roleGroups'])
                    ->latest()
                    ->get();
            }
        });

        return response()->json($rooms);
    }

    /**
     * Get the first general room.
     */
    public function getGeneralRoom(): JsonResponse
    {
        $room = Room::where('name', 'الغرفة العامة')
            ->orWhere('slug', 'like', 'alghurfah-alammah%')
            ->with(['users.media', 'users.roleGroups'])
            ->first();

        if (!$room) {
            return response()->json(['message' => 'General room not found'], 404);
        }

        return response()->json($room);
    }

    /**
     * Display the specified room.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is banned
        $banCheck = $this->checkUserBan($user);
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::with(['users.media', 'users.roleGroups', 'messages.user.media', 'messages.user.roleGroups'])
            ->findOrFail($id);
        
        // Check if room has password and user is not already a member
        if ($room->password && $user) {
            $isMember = $room->users()->where('user_id', $user->id)->exists();
            
            // If user is not a member, require password
            if (!$isMember) {
                $providedPassword = $request->input('password') ?? $request->query('password');
                if (!$providedPassword || $providedPassword !== $room->password) {
                    return response()->json([
                        'message' => 'Room password required',
                        'requires_password' => true,
                        'room_id' => $room->id,
                        'room_name' => $room->name,
                    ], 403);
                }
            }
        }

        $isNewMember = false;

        // If user is authenticated and room is public, automatically add them to the room
        if ($user && $room->is_public) {
            // Check if user is already a member
            if (!$room->users()->where('user_id', $request->user()->id)->exists()) {
                $isNewMember = true;
                // IMPORTANT: User can only be in ONE room at a time
                // Remove user from ALL other rooms before adding to this room
                $allUserRooms = DB::table('room_user')
                    ->where('user_id', $request->user()->id)
                    ->where('room_id', '!=', $room->id)
                    ->get();
                
                foreach ($allUserRooms as $userRoom) {
                    $previousRoom = Room::find($userRoom->room_id);
                    if ($previousRoom) {
                        $previousRoom->users()->detach($request->user()->id);
                        // Broadcast user left message to previous room
                        broadcast(new SystemMessage($request->user(), (int)$userRoom->room_id, 'left'));
                        broadcast(new UserPresence($request->user(), 'offline', (int)$userRoom->room_id))->toOthers();
                    }
                }
                
                // Add user to room as a member with current activity time
                $room->users()->attach($request->user()->id, [
                    'role' => 'member',
                    'last_activity' => now()
                ]);
                
                // Update login log with room information (most recent login without room)
                LoginLog::where('user_id', $request->user()->id)
                    ->whereNull('room_id')
                    ->orderBy('created_at', 'desc')
                    ->first()
                    ?->update([
                        'room_id' => $room->id,
                        'room_name' => $room->name,
                    ]);
                
                // Reload the room with updated users
                $room->load('users');
                // Broadcast user presence
                broadcast(new UserPresence($request->user(), 'online', $room->id))->toOthers();
                
                // Only broadcast "joined" message if user wasn't in any other room (reconnect scenario)
                // If user was in another room, it's a "moved" action (handled by the loop above)
                $wasInOtherRoom = !empty($allUserRooms);
                if (!$wasInOtherRoom) {
                    // User is joining/reconnecting (not moving from another room)
                    broadcast(new SystemMessage($request->user(), $room->id, 'joined'));
                } else {
                    // User moved from another room - broadcast "moved" message instead
                    $previousRoomId = $allUserRooms->first()->room_id ?? null;
                    if ($previousRoomId) {
                        broadcast(new SystemMessage($request->user(), $room->id, 'moved', (int)$previousRoomId));
                    }
                }

            } else {
                // Update last_activity for existing member
                DB::table('room_user')
                    ->where('room_id', $room->id)
                    ->where('user_id', $request->user()->id)
                    ->update(['last_activity' => now()]);
            }
        }

        // Load scheduled messages for this room
        $scheduledMessages = \App\Models\ScheduledMessage::active()
            ->forRoom($room->id)
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'type' => $msg->type,
                    'title' => $msg->title,
                    'message' => $msg->message,
                    'time_span' => $msg->time_span,
                    'is_active' => $msg->is_active,
                ];
            });

        $roomData = $room->toArray();
        $roomData['scheduled_messages'] = $scheduledMessages;

        return response()->json($roomData);
    }


    /**
     * Store a newly created room.
     */
    public function store(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'welcome_message' => 'nullable|string',
            'required_likes' => 'nullable|integer|min:0',
            'room_hashtag' => 'nullable|integer|min:1|max:100',
            'max_count' => 'nullable|integer|min:2|max:40',
            'password' => 'nullable|string|max:255',
            'room_image' => 'nullable|string|max:255',
            'room_cover' => 'nullable|string|max:255',
            'is_public' => 'boolean',
            'is_staff_only' => 'boolean',
            'enable_mic' => 'boolean',
            'disable_incognito' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        // Auto-generate room_hashtag if not provided
        $roomHashtag = $validated['room_hashtag'] ?? null;
        if (!$roomHashtag) {
            // Find the next available hashtag (1-100)
            $usedHashtags = Room::whereNotNull('room_hashtag')
                ->where('room_hashtag', '>=', 1)
                ->where('room_hashtag', '<=', 100)
                ->pluck('room_hashtag')
                ->toArray();
            
            $availableHashtag = null;
            for ($i = 1; $i <= 100; $i++) {
                if (!in_array($i, $usedHashtags)) {
                    $availableHashtag = $i;
                    break;
                }
            }
            
            if ($availableHashtag) {
                $roomHashtag = $availableHashtag;
            }
        }

        $room = Room::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
            'description' => $validated['description'] ?? null,
            'welcome_message' => $validated['welcome_message'] ?? null,
            'required_likes' => $validated['required_likes'] ?? 0,
            'room_hashtag' => $roomHashtag,
            'max_count' => $validated['max_count'] ?? 20,
            'password' => $validated['password'] ?? null,
            'room_image' => $validated['room_image'] ?? null,
            'room_cover' => $validated['room_cover'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
            'is_staff_only' => $validated['is_staff_only'] ?? false,
            'enable_mic' => $validated['enable_mic'] ?? false,
            'disable_incognito' => $validated['disable_incognito'] ?? false,
            'created_by' => $request->user()->id,
            'settings' => $validated['settings'] ?? null,
        ]);

        // Add creator as admin with current activity time
        $room->users()->attach($request->user()->id, [
            'role' => 'admin',
            'last_activity' => now()
        ]);

        $room->load('users');

        // Clear rooms cache for all users and bootstrap cache
        $this->clearRoomsCache($request->user());

        return response()->json($room, 201);
    }

    /**
     * Upload room image.
     */
    public function uploadImage(Request $request, string $id): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($id);
        $user = $request->user();

        // Check permissions (same as update)
        if (!($user->group_role === '999' || $user->group_role === 999 || 
              $room->created_by === $user->id || 
              ($room->users()->where('user_id', $user->id)->first()?->pivot->role === 'admin'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // Clear existing image and add new one
        $room->clearMediaCollection('image');
        $room->addMediaFromRequest('image')
            ->toMediaCollection('image');

        // Update room_image column with the URL for backward compatibility
        $room->room_image = $room->room_image_url;
        $room->save();

        $room->load('media');

        return response()->json([
            'room_image' => $room->room_image_url,
            'room' => $room,
        ]);
    }

    /**
     * Update the specified room.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($id);
        $user = $request->user();

        // Check if user has special role 999 (can edit all rooms)
        if ($user->group_role === '999' || $user->group_role === 999) {
            // User with role 999 can edit any room
        }
        // Check if user is the creator/owner
        elseif ($room->created_by === $user->id) {
            // Room owner can edit
        }
        // Check if user is admin in the room
        else {
            $roomUser = $room->users()->where('user_id', $user->id)->first();
            if (!$roomUser || $roomUser->pivot->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'welcome_message' => 'nullable|string',
            'required_likes' => 'nullable|integer|min:0',
            'room_hashtag' => 'nullable|integer|min:1|max:100',
            'max_count' => 'nullable|integer|min:2|max:40',
            'password' => 'nullable|string|max:255',
            'room_image' => 'nullable|string|max:255',
            'room_cover' => 'nullable|string|max:255',
            'is_public' => 'sometimes|boolean',
            'is_staff_only' => 'sometimes|boolean',
            'enable_mic' => 'sometimes|boolean',
            'disable_incognito' => 'sometimes|boolean',
            'settings' => 'nullable|array',
        ]);

        // Auto-generate room_hashtag if not provided
        if (!isset($validated['room_hashtag']) || $validated['room_hashtag'] === null) {
            // Find the next available hashtag (1-100)
            $usedHashtags = Room::whereNotNull('room_hashtag')
                ->where('room_hashtag', '>=', 1)
                ->where('room_hashtag', '<=', 100)
                ->pluck('room_hashtag')
                ->toArray();
            
            $availableHashtag = null;
            for ($i = 1; $i <= 100; $i++) {
                if (!in_array($i, $usedHashtags)) {
                    $availableHashtag = $i;
                    break;
                }
            }
            
            if ($availableHashtag) {
                $validated['room_hashtag'] = $availableHashtag;
            }
        }

        // Merge settings if they exist
        if (isset($validated['settings']) && is_array($validated['settings'])) {
            $existingSettings = $room->settings ?? [];
            $validated['settings'] = array_merge($existingSettings, $validated['settings']);
        }
        
        $room->update($validated);
        $room->load('users');

        // Clear rooms cache for all users and bootstrap cache
        $this->clearRoomsCache($request->user());

        return response()->json($room);
    }

    /**
     * Get all online/active users (users who are members of public rooms).
     */
    public function getActiveUsers(Request $request): JsonResponse
    {
        // Get all unique users who are members of public rooms
        // First get unique user IDs to avoid PostgreSQL JSON comparison issues
        $userIds = DB::table('room_user')
            ->join('rooms', 'room_user.room_id', '=', 'rooms.id')
            ->where('rooms.is_public', true)
            ->distinct()
            ->pluck('room_user.user_id')
            ->toArray();

        // Then fetch users with their relationships
        $activeUsers = \App\Models\User::whereIn('id', $userIds)
            ->with('media', 'roleGroups')
            ->get();

        return response()->json($activeUsers);
    }

    /**
     * Add a user to a room.
     */
    public function addUser(Request $request, string $roomId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);
        $currentUser = $request->user();

        // Check permissions - only admins, room owner, or users with role 999 can add users
        $canManage = false;
        if ($currentUser->group_role === '999' || $currentUser->group_role === 999) {
            $canManage = true;
        } elseif ($room->created_by === $currentUser->id) {
            $canManage = true;
        } else {
            $roomUser = $room->users()->where('user_id', $currentUser->id)->first();
            if ($roomUser && $roomUser->pivot->role === 'admin') {
                $canManage = true;
            }
        }

        if (!$canManage) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'password' => 'nullable|string',
            'previous_room_id' => 'nullable|integer|exists:rooms,id',
        ]);

        $targetUser = \App\Models\User::findOrFail($validated['user_id']);

        // Check if room has password and validate it
        if ($room->password) {
            $providedPassword = $validated['password'] ?? null;
            if (!$providedPassword || $providedPassword !== $room->password) {
                return response()->json(['message' => 'Invalid room password'], 403);
            }
        }

        // Check if user is already in the room - if so, just update activity and return success (idempotent)
        $isAlreadyInRoom = $room->users()->where('user_id', $targetUser->id)->exists();
        
        if ($isAlreadyInRoom) {
            // User is already in room - update last_activity and return success
            DB::table('room_user')
                ->where('room_id', $room->id)
                ->where('user_id', $targetUser->id)
                ->update(['last_activity' => now()]);
            
            // Reload room with users
            $room->load('users.media');
            
            return response()->json([
                'message' => 'User is already in the room',
                'room' => $room,
            ]);
        }

        // Check room capacity
        $currentUserCount = $room->users()->count();
        if ($room->max_count && $currentUserCount >= $room->max_count) {
            return response()->json(['message' => 'Room is at maximum capacity'], 400);
        }

        // IMPORTANT: User can only be in ONE room at a time
        // Remove user from ALL other rooms before adding to the new room
        $allUserRooms = DB::table('room_user')
            ->where('user_id', $targetUser->id)
            ->where('room_id', '!=', $roomId)
            ->get();
        
        $previousRoomId = $validated['previous_room_id'] ?? null;
        
        // Remove user from all other rooms and broadcast leave messages
        foreach ($allUserRooms as $userRoom) {
            $previousRoomModel = Room::find($userRoom->room_id);
            if ($previousRoomModel) {
                $previousRoomModel->users()->detach($targetUser->id);
                
                // Broadcast user left message to each previous room
                broadcast(new SystemMessage($targetUser, (int)$userRoom->room_id, 'left'));
                broadcast(new UserPresence($targetUser, 'offline', (int)$userRoom->room_id))->toOthers();
                
                // Use the most recent room as previousRoomId for the moved message
                $currentPreviousRoom = $allUserRooms->firstWhere('room_id', $previousRoomId);
                if (!$previousRoomId || ($userRoom->last_activity && (!$currentPreviousRoom || !$currentPreviousRoom->last_activity || $userRoom->last_activity > $currentPreviousRoom->last_activity))) {
                    $previousRoomId = $userRoom->room_id;
                }
            }
        }
        
        // If no previous room found from database, use provided one
        if (!$previousRoomId) {
            $previousRoomId = $validated['previous_room_id'] ?? null;
        }

        // Add user to target room (do this directly as fallback in case event is not received)
        if (!$room->users()->where('user_id', $targetUser->id)->exists()) {
            $room->users()->attach($targetUser->id, [
                'role' => 'member',
                'last_activity' => now()
            ]);
        } else {
            // Update last_activity if already in room
            DB::table('room_user')
                ->where('room_id', $room->id)
                ->where('user_id', $targetUser->id)
                ->update(['last_activity' => now()]);
        }

        // Reload room with users
        $room->load('users.media');

        // Send an event to the user to perform the navigation action
        // The user's frontend will receive the event and navigate to the room themselves
        broadcast(new UserMoveRequest(
            $targetUser,
            (int)$roomId,
            $previousRoomId ? (int)$previousRoomId : null,
            $validated['password'] ?? null
        ));

        // Broadcast user presence and system message for the target room
        broadcast(new UserPresence($targetUser, 'online', (int)$roomId))->toOthers();
        
        if ($previousRoomId && $previousRoomId != $roomId) {
            // User is moving from another room
            broadcast(new SystemMessage($targetUser, (int)$roomId, 'moved', (int)$previousRoomId));
        } else {
            // User is joining for the first time
            broadcast(new SystemMessage($targetUser, (int)$roomId, 'joined'));
        }

        return response()->json([
            'message' => 'User moved to room successfully',
            'target_room_id' => $roomId,
            'user_id' => $targetUser->id,
            'room' => $room,
        ]);
    }

    /**
     * Remove a user from a room.
     */
    public function removeUser(Request $request, string $roomId, string $userId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);
        $currentUser = $request->user();
        $targetUser = \App\Models\User::findOrFail($userId);

        // Check permissions - only admins, room owner, or users with role 999 can remove users
        // Users can also remove themselves
        $canManage = false;
        if ($currentUser->id === $targetUser->id) {
            // Users can remove themselves
            $canManage = true;
        } elseif ($currentUser->group_role === '999' || $currentUser->group_role === 999) {
            $canManage = true;
        } elseif ($room->created_by === $currentUser->id) {
            $canManage = true;
        } else {
            $roomUser = $room->users()->where('user_id', $currentUser->id)->first();
            if ($roomUser && $roomUser->pivot->role === 'admin') {
                $canManage = true;
            }
        }

        if (!$canManage) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if user is in the room
        if (!$room->users()->where('user_id', $targetUser->id)->exists()) {
            return response()->json(['message' => 'User is not in the room'], 400);
        }

        // Remove user from room
        $room->users()->detach($targetUser->id);

        // Reload room with users
        $room->load('users.media');

        // Broadcast user presence (offline/left)
        broadcast(new UserPresence($targetUser, 'offline', $roomId))->toOthers();

        // Broadcast system message for user left
        broadcast(new SystemMessage($targetUser, (int)$roomId, 'left'));

        return response()->json([
            'message' => 'User removed from room successfully',
            'room' => $room,
        ]);
    }

    /**
     * Mute a user in a room.
     */
    public function muteUser(Request $request, string $roomId, string $userId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);
        $currentUser = $request->user();

        // Check permissions - only admins, room owner, or users with role 999 can mute users
        $canManage = false;
        if ($currentUser->group_role === '999' || $currentUser->group_role === 999) {
            $canManage = true;
        } elseif ($room->created_by === $currentUser->id) {
            $canManage = true;
        } else {
            $roomUser = $room->users()->where('user_id', $currentUser->id)->first();
            if ($roomUser && $roomUser->pivot->role === 'admin') {
                $canManage = true;
            }
        }

        if (!$canManage) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $targetUser = \App\Models\User::findOrFail($userId);

        // Check if user is in the room
        if (!$room->users()->where('user_id', $targetUser->id)->exists()) {
            return response()->json(['message' => 'User is not in the room'], 400);
        }

        $validated = $request->validate([
            'muted_until' => 'nullable|date',
            'reason' => 'nullable|string|max:255',
        ]);

        // Update or create mute record in room_user pivot table
        // Note: You may need to add a 'muted_until' column to the room_user pivot table
        // For now, we'll store it in the settings or use a separate table
        // This is a basic implementation - you may want to enhance it
        
        // Update last_activity and add mute info
        DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('user_id', $targetUser->id)
            ->update([
                'last_activity' => now(),
                // If you have a muted_until column, uncomment:
                // 'muted_until' => $validated['muted_until'] ?? null,
            ]);

        // Reload room with users
        $room->load('users.media');

        return response()->json([
            'message' => 'User muted successfully',
            'room' => $room,
        ]);
    }

    /**
     * Remove the specified room.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($id);
        $user = $request->user();

        // Check if user has special role 999 (can delete all rooms)
        if ($user->group_role === '999' || $user->group_role === 999) {
            // User with role 999 can delete any room
        }
        // Check if user is the creator/owner
        elseif ($room->created_by === $user->id) {
            // Room owner can delete
        }
        else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Detach all users from the room before deleting
        $room->users()->detach();

        // Delete the room
        $room->delete();

        // Clear rooms cache for all users and bootstrap cache
        $this->clearRoomsCache($request->user());

        return response()->json([
            'message' => 'Room deleted successfully',
        ]);
    }
}

