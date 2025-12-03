<?php

namespace App\Console\Commands;

use App\Events\UserPresence;
use App\Events\SystemMessage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveInactiveUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:remove-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove users from rooms if they have been inactive for 30 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $inactiveThreshold = now()->subMinutes(30);
        
        // Find all room_user entries where last_activity is older than 30 minutes
        $inactiveEntries = DB::table('room_user')
            ->where(function($query) use ($inactiveThreshold) {
                $query->where('last_activity', '<', $inactiveThreshold)
                      ->orWhereNull('last_activity');
            })
            ->get();

        $removedCount = 0;

        foreach ($inactiveEntries as $entry) {
            $user = User::find($entry->user_id);
            $roomId = $entry->room_id;

            if ($user) {
                // Remove user from room
                DB::table('room_user')
                    ->where('room_id', $roomId)
                    ->where('user_id', $user->id)
                    ->delete();

                // Broadcast offline status
                broadcast(new UserPresence($user, 'offline', $roomId))->toOthers();
                // Broadcast system message for user leaving (all users including the leaving user should see it)
                broadcast(new SystemMessage($user, $roomId, 'left'));

                $removedCount++;
            }
        }

        $this->info("Removed {$removedCount} inactive users from rooms.");

        return Command::SUCCESS;
    }
}
