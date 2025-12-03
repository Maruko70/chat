<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Message $message
    ) {
        $this->message->load(['user.media', 'user.roleGroups']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->message->room_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
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
            'room_id' => $this->message->room_id,
            'user_id' => $this->message->user_id,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'username' => $this->message->user->username,
                'email' => $this->message->user->email,
                'avatar_url' => $this->message->user->avatar_url,
                'bio' => $this->message->user->bio,
                'name_color' => $this->message->user->name_color,
                'message_color' => $this->message->user->message_color,
                'name_bg_color' => $this->message->user->name_bg_color,
                'image_border_color' => $this->message->user->image_border_color,
                'bio_color' => $this->message->user->bio_color,
                'gifts' => $this->message->user->gifts,
                'group_role' => $this->message->user->group_role,
                'role_groups' => $this->message->user->roleGroups->map(function ($roleGroup) {
                    return [
                        'id' => $roleGroup->id,
                        'name' => $roleGroup->name,
                        'banner' => $roleGroup->banner,
                        'priority' => $roleGroup->priority,
                        'permissions' => $roleGroup->permissions,
                    ];
                })->toArray(),
                'role_group_banner' => $this->message->user->role_group_banner,
                'all_permissions' => $this->message->user->all_permissions,
            ],
            'content' => $this->message->content,
            'meta' => $this->message->meta,
            'created_at' => $this->message->created_at->toISOString(),
            'updated_at' => $this->message->updated_at->toISOString(),
        ];
        
        // Include reply_to user information if present
        if (isset($this->message->meta['reply_to']['user_id'])) {
            $replyUserId = $this->message->meta['reply_to']['user_id'];
            $replyUser = \App\Models\User::with('media')->find($replyUserId);
            if ($replyUser) {
                $data['meta']['reply_to']['user'] = [
                    'id' => $replyUser->id,
                    'name' => $replyUser->name,
                    'username' => $replyUser->username,
                    'avatar_url' => $replyUser->avatar_url,
                ];
            }
        }
        
        return $data;
    }
}

