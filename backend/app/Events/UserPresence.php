<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresence implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public string $status, // 'online' or 'offline'
        public ?int $roomId = null
    ) {
        $this->user->load('media', 'roleGroups', 'membershipBackground', 'membershipFrame');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // If roomId is specified, broadcast to that room (using PresenceChannel to match frontend)
        if ($this->roomId) {
            $channels[] = new PresenceChannel('room.' . $this->roomId);
        } else {
            // Broadcast to all rooms the user is in (using PresenceChannel to match frontend)
            foreach ($this->user->rooms as $room) {
                $channels[] = new PresenceChannel('room.' . $room->id);
            }
        }
        
        // Also broadcast to a global presence channel
        $channels[] = new PresenceChannel('presence');
        
        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'user.presence';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'email' => $this->user->email,
                'avatar_url' => $this->user->avatar_url,
                'bio' => $this->user->bio,
                'country_code' => $this->user->country_code,
                'name_color' => $this->user->name_color,
                'message_color' => $this->user->message_color,
                'name_bg_color' => $this->user->name_bg_color,
                'image_border_color' => $this->user->image_border_color,
                'bio_color' => $this->user->bio_color,
                'gifts' => $this->user->gifts,
                'group_role' => $this->user->group_role,
                'is_guest' => $this->user->is_guest,
                'premium_entry' => $this->user->premium_entry,
                'premium_entry_background' => $this->user->premium_entry_background,
                'designed_membership' => $this->user->designed_membership,
                'membership_background' => $this->user->membershipBackground ? [
                    'id' => $this->user->membershipBackground->id,
                    'name' => $this->user->membershipBackground->name,
                    'image_url' => $this->user->membershipBackground->image_url,
                ] : null,
                'membership_frame' => $this->user->membershipFrame ? [
                    'id' => $this->user->membershipFrame->id,
                    'name' => $this->user->membershipFrame->name,
                    'image_url' => $this->user->membershipFrame->image_url,
                ] : null,
                'role_groups' => $this->user->roleGroups->map(function ($roleGroup) {
                    return [
                        'id' => $roleGroup->id,
                        'name' => $roleGroup->name,
                        'banner' => $roleGroup->banner,
                        'priority' => $roleGroup->priority,
                        'permissions' => $roleGroup->permissions,
                    ];
                })->toArray(),
                'role_group_banner' => $this->user->role_group_banner,
                'all_permissions' => $this->user->all_permissions,
            ],
            'status' => $this->status,
            'room_id' => $this->roomId,
        ];
    }
}
