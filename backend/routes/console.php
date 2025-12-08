<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule batch status updates every 30 seconds
Schedule::command('users:batch-update-statuses')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule removal of offline/inactive users from rooms every 5 minutes
Schedule::command('users:remove-inactive --minutes=10')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
