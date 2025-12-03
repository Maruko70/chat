<?php

namespace App\Console\Commands;

use App\Services\UserStatusService;
use Illuminate\Console\Command;

class BatchUpdateUserStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:batch-update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batch update user statuses from cache to database';

    /**
     * Execute the console command.
     */
    public function handle(UserStatusService $statusService): int
    {
        $this->info('Starting batch status update...');
        
        $updated = $statusService->batchUpdateStatuses();
        
        $this->info("Updated {$updated} user statuses.");
        
        return Command::SUCCESS;
    }
}

