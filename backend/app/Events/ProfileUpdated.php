<?php

namespace App\Events;

use App\Models\User;
use App\Services\UserStatusService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user
    ) {
        $this->user->load('media', 'roleGroups');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to all rooms the user is in (using PresenceChannel to match frontend)
        $channels = [];
        foreach ($this->user->rooms as $room) {
            $channels[] = new PresenceChannel('room.' . $room->id);
        }
        // Also broadcast to a global user channel (keep as PrivateChannel)
        $channels[] = new PrivateChannel('user.' . $this->user->id);
        // Broadcast to global presence channel so home page and other pages can listen
        $channels[] = new PresenceChannel('presence');
        
        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'profile.updated';
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
                'avatar_url' => $this->user->avatar_url,
                'bio' => $this->user->bio,
                'country_code' => $this->user->country_code,
                'name_color' => $this->user->name_color,
                'message_color' => $this->user->message_color,
                'name_bg_color' => $this->user->name_bg_color,
                'image_border_color' => $this->user->image_border_color,
                'bio_color' => $this->user->bio_color,
                'room_font_size' => $this->user->room_font_size,
                'gifts' => $this->user->gifts,
                'group_role' => $this->user->group_role,
                'is_guest' => $this->user->is_guest,
                'incognito_mode_enabled' => $this->user->incognito_mode_enabled ?? false,
                'private_messages_enabled' => $this->user->private_messages_enabled ?? true,
                'status' => app(UserStatusService::class)->getStatus($this->user->id)['status'],
                'last_activity' => app(UserStatusService::class)->getStatus($this->user->id)['last_activity'],
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
        ];
    }
}
