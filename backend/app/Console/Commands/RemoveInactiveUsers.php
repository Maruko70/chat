<?php

namespace App\Console\Commands;

use App\Events\UserPresence;
use App\Events\SystemMessage;
use App\Models\User;
use App\Services\UserStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemoveInactiveUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:remove-inactive {--minutes=10 : Minutes of inactivity before removal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove offline/inactive users from active rooms';

    /**
     * Execute the console command.
     */
    public function handle(UserStatusService $statusService): int
    {
        $inactiveMinutes = (int) $this->option('minutes');
        $inactiveThreshold = now()->subMinutes($inactiveMinutes);
        
        $this->info("Checking for users inactive for more than {$inactiveMinutes} minutes...");
        
        // Find all room_user entries where last_activity is older than threshold
        // This is the most reliable indicator - if last_activity hasn't been updated,
        // the user is not actively connected/interacting with the room
        $inactiveEntries = DB::table('room_user')
            ->where(function($query) use ($inactiveThreshold) {
                $query->where('last_activity', '<', $inactiveThreshold)
                      ->orWhereNull('last_activity');
            })
            ->get();

        $removedCount = 0;
        $skippedCount = 0;

        foreach ($inactiveEntries as $entry) {
            $user = User::find($entry->user_id);
            $roomId = $entry->room_id;

            if (!$user) {
                // User doesn't exist, clean up orphaned entry
                DB::table('room_user')
                    ->where('room_id', $roomId)
                    ->where('user_id', $entry->user_id)
                    ->delete();
                $removedCount++;
                continue;
            }

            // Check room_user last_activity - this is updated when user interacts with room
            $roomUserLastActivity = $entry->last_activity 
                ? \Carbon\Carbon::parse($entry->last_activity) 
                : null;
            
            // Also check user status cache - if cache expired, user might be offline
            $cacheKey = 'user_status:' . $user->id;
            $cachedStatus = Cache::get($cacheKey);
            
            // Determine if user should be removed
            $shouldRemove = false;
            
            // Remove if room_user last_activity is old or null
            if (!$roomUserLastActivity || $roomUserLastActivity->lt($inactiveThreshold)) {
                $shouldRemove = true;
                
                // Additional check: if cache exists but is also old, more confident they're offline
                if ($cachedStatus) {
                    $cacheUpdatedAt = isset($cachedStatus['updated_at']) 
                        ? \Carbon\Carbon::createFromTimestamp($cachedStatus['updated_at'])
                        : null;
                    
                    // If cache is also old (older than threshold), definitely offline
                    if ($cacheUpdatedAt && $cacheUpdatedAt->lt($inactiveThreshold)) {
                        $shouldRemove = true;
                    }
                } else {
                    // No cache entry - user might be offline (cache TTL is 5 minutes)
                    // If room_user activity is old AND no cache, likely offline
                    $shouldRemove = true;
                }
            }

            if ($shouldRemove) {
                try {
                    // Remove user from room
                    DB::table('room_user')
                        ->where('room_id', $roomId)
                        ->where('user_id', $user->id)
                        ->delete();

                    // Broadcast offline status
                    broadcast(new UserPresence($user, 'offline', $roomId))->toOthers();
                    
                    // Broadcast system message for user leaving
                    broadcast(new SystemMessage($user, $roomId, 'left'));

                    $removedCount++;
                    
                    if ($this->getOutput()->isVerbose()) {
                        $lastActivityStr = $roomUserLastActivity 
                            ? $roomUserLastActivity->diffForHumans() 
                            : 'never';
                        $this->line("Removed user {$user->username} (ID: {$user->id}) from room {$roomId} - last activity: {$lastActivityStr}");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to remove inactive user {$user->id} from room {$roomId}: " . $e->getMessage());
                    $this->error("Failed to remove user {$user->id} from room {$roomId}: " . $e->getMessage());
                }
            } else {
                $skippedCount++;
            }
        }

        $this->info("Removed {$removedCount} offline/inactive users from rooms.");
        if ($skippedCount > 0 && $this->getOutput()->isVerbose()) {
            $this->info("Skipped {$skippedCount} users (still active).");
        }

        return Command::SUCCESS;
    }
}
