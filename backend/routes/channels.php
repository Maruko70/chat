<?php

use App\Models\Room;
use App\Services\UserStatusService;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Channel for presence-room.{roomId} - Laravel Echo automatically prefixes presence channels with "presence-"
// So echo.join('room.1') becomes presence-room.1
// Presence channels return user data that will be available in here(), joining(), and leaving() events
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    // Check if user is banned - deny access to all channels
    $bannedUser = \App\Models\BannedUser::where('user_id', $user->id)->active()->first();
    if ($bannedUser) {
        return false; // Deny channel access for banned users
    }
    
    // Load user media relationship and role groups
    $user->load('media', 'roleGroups', 'membershipBackground', 'membershipFrame');
    
    // Get user status from cache (fast, doesn't block)
    $statusService = app(UserStatusService::class);
    $statusData = $statusService->getStatus($user->id);
    
    // Check if user is a member of the room
    $room = Room::find($roomId);
    if (!$room) {
        return false;
    }
    
    $userData = [
        'id' => $user->id,
        'name' => $user->name,
        'username' => $user->username,
        'avatar_url' => $user->avatar_url,
        'bio' => $user->bio,
        'social_media_type' => $user->social_media_type,
        'social_media_url' => $user->social_media_url,
        'country_code' => $user->country_code,
        'name_color' => $user->name_color,
        'message_color' => $user->message_color,
        'name_bg_color' => $user->name_bg_color,
        'image_border_color' => $user->image_border_color,
        'bio_color' => $user->bio_color,
        'gifts' => $user->gifts,
        'is_guest' => $user->is_guest,
        'premium_entry' => $user->premium_entry,
        'premium_entry_background' => $user->premium_entry_background,
        'designed_membership' => $user->designed_membership,
        'incognito_mode_enabled' => $user->incognito_mode_enabled ?? false,
        'private_messages_enabled' => $user->private_messages_enabled ?? true,
        'status' => $statusData['status'],
        'last_activity' => $statusData['last_activity'],
        'membership_background' => $user->membershipBackground ? [
            'id' => $user->membershipBackground->id,
            'name' => $user->membershipBackground->name,
            'image_url' => $user->membershipBackground->image_url,
        ] : null,
        'membership_frame' => $user->membershipFrame ? [
            'id' => $user->membershipFrame->id,
            'name' => $user->membershipFrame->name,
            'image_url' => $user->membershipFrame->image_url,
        ] : null,
        'role_groups' => $user->roleGroups->map(function ($roleGroup) {
            return [
                'id' => $roleGroup->id,
                'name' => $roleGroup->name,
                'banner' => $roleGroup->banner,
                'priority' => $roleGroup->priority,
                'permissions' => $roleGroup->permissions,
            ];
        })->toArray(),
        'role_group_banner' => $user->role_group_banner,
        'all_permissions' => $user->all_permissions,
    ];
    
    // For public rooms, allow access and return user data for presence
    if ($room->is_public) {
        return $userData;
    }
    
    // For private rooms, check membership
    if ($user->rooms()->where('rooms.id', $roomId)->exists()) {
        return $userData;
    }
    
    return false;
});

// Channel for user.{userId} - for profile updates and ban notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    // Users can only listen to their own user channel
    // Allow access even if banned so they can receive the ban event
    if ((int) $user->id === (int) $userId) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    
    return false;
});

// Presence channel for online/offline status and profile updates
Broadcast::channel('presence', function ($user) {
    // Check if user is banned - deny access to presence channel
    $bannedUser = \App\Models\BannedUser::where('user_id', $user->id)->active()->first();
    if ($bannedUser) {
        return false; // Deny channel access for banned users
    }
    
    // Load user media relationship and role groups
    $user->load('media', 'roleGroups', 'membershipBackground', 'membershipFrame');
    
    // Get user status from cache (fast, doesn't block)
    $statusService = app(UserStatusService::class);
    $statusData = $statusService->getStatus($user->id);
    
    // Return full user data for presence channel
    return [
        'id' => $user->id,
        'name' => $user->name,
        'username' => $user->username,
        'avatar_url' => $user->avatar_url,
        'bio' => $user->bio,
        'social_media_type' => $user->social_media_type,
        'social_media_url' => $user->social_media_url,
        'country_code' => $user->country_code,
        'name_color' => $user->name_color,
        'message_color' => $user->message_color,
        'name_bg_color' => $user->name_bg_color,
        'image_border_color' => $user->image_border_color,
        'bio_color' => $user->bio_color,
        'room_font_size' => $user->room_font_size,
        'gifts' => $user->gifts,
        'is_guest' => $user->is_guest,
        'premium_entry' => $user->premium_entry,
        'premium_entry_background' => $user->premium_entry_background,
        'designed_membership' => $user->designed_membership,
        'incognito_mode_enabled' => $user->incognito_mode_enabled ?? false,
        'private_messages_enabled' => $user->private_messages_enabled ?? true,
        'status' => $statusData['status'],
        'last_activity' => $statusData['last_activity'],
        'membership_background' => $user->membershipBackground ? [
            'id' => $user->membershipBackground->id,
            'name' => $user->membershipBackground->name,
            'image_url' => $user->membershipBackground->image_url,
        ] : null,
        'membership_frame' => $user->membershipFrame ? [
            'id' => $user->membershipFrame->id,
            'name' => $user->membershipFrame->name,
            'image_url' => $user->membershipFrame->image_url,
        ] : null,
        'role_groups' => $user->roleGroups->map(function ($roleGroup) {
            return [
                'id' => $roleGroup->id,
                'name' => $roleGroup->name,
                'banner' => $roleGroup->banner,
                'priority' => $roleGroup->priority,
                'permissions' => $roleGroup->permissions,
            ];
        })->toArray(),
        'role_group_banner' => $user->role_group_banner,
        'all_permissions' => $user->all_permissions,
    ];
});

