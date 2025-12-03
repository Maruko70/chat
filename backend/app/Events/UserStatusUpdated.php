<?php

namespace App\Events;

use App\Models\User;
use App\Services\UserStatusService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public string $status, // 'active', 'inactive_tab', 'private_disabled', 'away', 'incognito'
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
        // Broadcast to global presence channel so all users can receive status updates
        return [
            new PresenceChannel('presence'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'user.status.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $statusService = app(UserStatusService::class);
        $statusData = $statusService->getStatus($this->user->id);
        
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'avatar_url' => $this->user->avatar_url,
                'country_code' => $this->user->country_code,
                'incognito_mode_enabled' => $this->user->incognito_mode_enabled ?? false,
                'private_messages_enabled' => $this->user->private_messages_enabled ?? true,
                'status' => $this->status,
                'last_activity' => $statusData['last_activity'],
            ],
            'status' => $this->status,
        ];
    }
}

