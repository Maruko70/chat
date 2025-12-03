<?php

namespace App\Events;

use App\Models\ScheduledMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduledMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ScheduledMessage $scheduledMessage,
        public int $roomId,
    ) {
        $this->scheduledMessage->load('room:id,name,slug');
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
        return 'scheduled.message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Get system messages image from site settings
        $systemMessagesImage = \App\Models\SiteSettings::getValue('system_messages_image');
        
        return [
            'id' => 'scheduled-' . $this->scheduledMessage->id . '-' . time(),
            'room_id' => $this->roomId,
            'user_id' => null,
            'user' => [
                'id' => null,
                'name' => $this->scheduledMessage->title,
                'username' => 'system',
                'avatar_url' => $systemMessagesImage ?: null,
                'is_guest' => false,
                'role_groups' => [],
            ],
            'content' => $this->scheduledMessage->message,
            'meta' => [
                'is_scheduled' => true,
                'scheduled_message_id' => $this->scheduledMessage->id,
                'type' => $this->scheduledMessage->type,
            ],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
    }
}
