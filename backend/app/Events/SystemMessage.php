<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public int $roomId,
        public string $action, // 'joined', 'left', or 'moved'
        public ?int $previousRoomId = null, // For 'moved' action - the room they left
        public ?int $movedToRoomId = null, // For 'moved' action when broadcasting to old room - the room they moved to
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
        return [
            new PresenceChannel('room.' . $this->roomId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'system.message';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $actionText = match($this->action) {
            'joined' => 'انضم إلى الغرفة',
            'moved' => 'انتقل إلى الغرفة',
            'left' => 'غادر الغرفة',
            default => 'انضم إلى الغرفة',
        };
        
        // Get system messages image from site settings
        $systemMessagesImage = \App\Models\SiteSettings::getValue('system_messages_image');
        
        return [
            'id' => 'system-' . time() . '-' . rand(1000, 9999),
            'room_id' => $this->roomId,
            'user_id' => $this->user->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'email' => $this->user->email,
                'avatar_url' => $systemMessagesImage ?: null, // Use system messages image instead of user avatar
                'bio' => $this->user->bio,
                'name_color' => $this->user->name_color,
                'message_color' => $this->user->message_color,
                'name_bg_color' => $this->user->name_bg_color,
                'image_border_color' => $this->user->image_border_color,
                'bio_color' => $this->user->bio_color,
                'gifts' => $this->user->gifts,
                'is_guest' => $this->user->is_guest,
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
            'content' => ($this->user->name || $this->user->username) . ' ' . $actionText,
            'meta' => [
                'is_system' => true,
                'action' => $this->action,
                'previous_room_id' => $this->previousRoomId,
                'room_id' => $this->movedToRoomId ?? $this->roomId, // For 'moved' action, this is the destination room
                'room_name' => $this->movedToRoomId ? \App\Models\Room::find($this->movedToRoomId)?->name : null,
            ],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
    }
}

