<?php

namespace App\Console\Commands;

use App\Events\ScheduledMessageSent;
use App\Models\ScheduledMessage;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheduled-messages:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled daily messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled messages to send...');
        
        // Get all active daily messages
        $messages = ScheduledMessage::active()
            ->daily()
            ->get();
        
        $sentCount = 0;
        
        foreach ($messages as $message) {
            if ($message->shouldSendNow()) {
                try {
                    $this->sendMessage($message);
                    $message->update(['last_sent_at' => now()]);
                    $sentCount++;
                    $this->info("Sent message: {$message->title}");
                } catch (\Exception $e) {
                    $this->error("Failed to send message {$message->id}: {$e->getMessage()}");
                    Log::error('Failed to send scheduled message', [
                        'message_id' => $message->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        $this->info("Sent {$sentCount} scheduled message(s)");
        
        return Command::SUCCESS;
    }
    
    /**
     * Send a scheduled message to the appropriate room(s).
     */
    private function sendMessage(ScheduledMessage $message)
    {
        if ($message->room_id) {
            // Send to specific room
            $room = Room::find($message->room_id);
            if ($room) {
                broadcast(new ScheduledMessageSent($message, $room->id));
            }
        } else {
            // Send to all rooms
            $rooms = Room::where('is_public', true)->get();
            foreach ($rooms as $room) {
                broadcast(new ScheduledMessageSent($message, $room->id));
            }
        }
    }
}
