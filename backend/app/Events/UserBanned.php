<?php

namespace App\Events;

use App\Models\User;
use App\Models\BannedUser;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserBanned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public BannedUser $bannedUser
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to the banned user's private channel
        return [
            new PrivateChannel('user.' . $this->user->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'user.banned';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'ban' => [
                'id' => $this->bannedUser->id,
                'reason' => $this->bannedUser->reason,
                'is_permanent' => $this->bannedUser->is_permanent,
                'ends_at' => $this->bannedUser->ends_at?->toISOString(),
                'banned_at' => $this->bannedUser->banned_at->toISOString(),
            ],
            'message' => $this->bannedUser->reason 
                ? 'Your account has been banned: ' . $this->bannedUser->reason
                : 'Your account has been banned',
        ];
    }
}

