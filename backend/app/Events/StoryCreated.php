<?php

namespace App\Events;

use App\Models\Story;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoryCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Story $story;

    /**
     * Create a new event instance.
     */
    public function __construct(Story $story)
    {
        $this->story = $story->load('user:id,name,username,avatar_url');
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('stories'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'story.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->story->id,
            'user_id' => $this->story->user_id,
            'user' => [
                'id' => $this->story->user->id,
                'name' => $this->story->user->name,
                'username' => $this->story->user->username,
                'avatar_url' => $this->story->user->avatar_url,
            ],
            'media_url' => $this->story->media_url,
            'media_type' => $this->story->media_type,
            'caption' => $this->story->caption,
            'expires_at' => $this->story->expires_at->toISOString(),
            'created_at' => $this->story->created_at->toISOString(),
            'is_viewed' => false, // New story, not viewed yet
            'views_count' => 0, // New story, no views yet
        ];
    }
}

