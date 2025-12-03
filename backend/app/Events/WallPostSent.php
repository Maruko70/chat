<?php

namespace App\Events;

use App\Models\WallPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WallPostSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public WallPost $wallPost
    ) {
        $this->wallPost->load(['user.media', 'user.roleGroups', 'likes', 'comments']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->wallPost->room_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'wall.post.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->wallPost->id,
            'room_id' => $this->wallPost->room_id,
            'user_id' => $this->wallPost->user_id,
            'user' => [
                'id' => $this->wallPost->user->id,
                'name' => $this->wallPost->user->name,
                'username' => $this->wallPost->user->username,
                'email' => $this->wallPost->user->email,
                'avatar_url' => $this->wallPost->user->avatar_url,
                'bio' => $this->wallPost->user->bio,
                'name_color' => $this->wallPost->user->name_color,
                'message_color' => $this->wallPost->user->message_color,
                'name_bg_color' => $this->wallPost->user->name_bg_color,
                'image_border_color' => $this->wallPost->user->image_border_color,
                'bio_color' => $this->wallPost->user->bio_color,
                'gifts' => $this->wallPost->user->gifts,
                'group_role' => $this->wallPost->user->group_role,
                'role_groups' => $this->wallPost->user->roleGroups->map(function ($roleGroup) {
                    return [
                        'id' => $roleGroup->id,
                        'name' => $roleGroup->name,
                        'banner' => $roleGroup->banner,
                        'priority' => $roleGroup->priority,
                        'permissions' => $roleGroup->permissions,
                    ];
                })->toArray(),
                'role_group_banner' => $this->wallPost->user->role_group_banner,
                'all_permissions' => $this->wallPost->user->all_permissions,
            ],
            'content' => $this->wallPost->content,
            'image' => $this->wallPost->image,
            'image_url' => $this->wallPost->image ? \Illuminate\Support\Facades\Storage::url($this->wallPost->image) : null,
            'youtube_video' => $this->wallPost->youtube_video,
            'likes_count' => $this->wallPost->likes()->count(),
            'comments_count' => $this->wallPost->comments()->count(),
            'created_at' => $this->wallPost->created_at->toISOString(),
            'updated_at' => $this->wallPost->updated_at->toISOString(),
        ];
    }
}
