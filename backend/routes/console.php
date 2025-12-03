<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule batch status updates every 30 seconds
Schedule::command('users:batch-update-statuses')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground();
