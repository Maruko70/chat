<?php

namespace App\Events;

use App\Models\PrivateMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public PrivateMessage $message
    ) {
        $this->message->load(['sender.media', 'sender.roleGroups', 'recipient.media', 'recipient.roleGroups']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Broadcast to both sender and recipient's private channels
            new PrivateChannel('user.' . $this->message->sender_id),
            new PrivateChannel('user.' . $this->message->recipient_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'private-message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $data = [
            'id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'recipient_id' => $this->message->recipient_id,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
                'username' => $this->message->sender->username,
                'email' => $this->message->sender->email,
                'avatar_url' => $this->message->sender->avatar_url,
                'bio' => $this->message->sender->bio,
                'country_code' => $this->message->sender->country_code,
                'name_color' => $this->message->sender->name_color,
                'message_color' => $this->message->sender->message_color,
                'name_bg_color' => $this->message->sender->name_bg_color,
                'image_border_color' => $this->message->sender->image_border_color,
                'bio_color' => $this->message->sender->bio_color,
                'gifts' => $this->message->sender->gifts,
                'group_role' => $this->message->sender->group_role ?? null,
                'role_groups' => $this->message->sender->roleGroups->map(function ($roleGroup) {
                    return [
                        'id' => $roleGroup->id,
                        'name' => $roleGroup->name,
                        'banner' => $roleGroup->banner,
                        'priority' => $roleGroup->priority,
                        'permissions' => $roleGroup->permissions,
                    ];
                })->toArray(),
                'role_group_banner' => $this->message->sender->role_group_banner,
                'all_permissions' => $this->message->sender->all_permissions,
            ],
            'recipient' => [
                'id' => $this->message->recipient->id,
                'name' => $this->message->recipient->name,
                'username' => $this->message->recipient->username,
                'avatar_url' => $this->message->recipient->avatar_url,
                'country_code' => $this->message->recipient->country_code,
            ],
            'content' => $this->message->content,
            'meta' => $this->message->meta,
            'read_at' => $this->message->read_at?->toISOString(),
            'created_at' => $this->message->created_at->toISOString(),
            'updated_at' => $this->message->updated_at->toISOString(),
        ];
        
        return $data;
    }
}

