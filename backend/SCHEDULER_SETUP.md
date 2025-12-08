# Laravel Scheduler Setup for Ubuntu Server

This guide explains how to set up the automatic removal of offline users from active rooms on your Ubuntu server.

## Overview

The system includes a scheduled command (`users:remove-inactive`) that automatically removes offline/inactive users from active rooms. This prevents offline users from appearing in the active or online users sections.

## How It Works

- **Command**: `users:remove-inactive`
- **Frequency**: Runs every 5 minutes
- **Threshold**: Removes users inactive for more than 10 minutes
- **Checks**: 
  - User's status (must be 'away')
  - Last activity timestamp (both in room_user table and user status cache)
  - Removes users who are truly offline

## Setup Instructions

### Step 1: Ensure Laravel Scheduler is Running

Laravel's task scheduler needs to be run via a cron job. Add this to your server's crontab:

```bash
sudo crontab -e
```

Add the following line (adjust the path to your Laravel installation):

```cron
* * * * * cd /path/to/your/laravel/project && php artisan schedule:run >> /dev/null 2>&1
```

**Important**: Replace `/path/to/your/laravel/project` with your actual project path.

### Step 2: Verify the Scheduler is Working

Test the scheduler manually:

```bash
cd /path/to/your/laravel/project
php artisan schedule:run
```

You should see output indicating scheduled tasks are running.

### Step 3: Test the Command Manually

Test the inactive user removal command:

```bash
php artisan users:remove-inactive
```

Or with custom minutes threshold:

```bash
php artisan users:remove-inactive --minutes=5
```

### Step 4: Monitor the Scheduler

Check Laravel logs to ensure the scheduler is running:

```bash
tail -f storage/logs/laravel.log
```

You can also check if the scheduler is running:

```bash
ps aux | grep "schedule:run"
```

## Configuration

### Adjusting the Inactivity Threshold

The default threshold is 10 minutes. To change it, edit `routes/console.php`:

```php
Schedule::command('users:remove-inactive --minutes=10')
    ->everyFiveMinutes()
```

Change `--minutes=10` to your desired threshold (e.g., `--minutes=5` for 5 minutes).

### Adjusting the Frequency

To run more or less frequently, change the schedule frequency in `routes/console.php`:

```php
// Every 2 minutes
->everyTwoMinutes()

// Every 10 minutes
->everyTenMinutes()

// Every hour
->hourly()

// Custom cron expression
->cron('*/3 * * * *') // Every 3 minutes
```

## Troubleshooting

### Scheduler Not Running

1. **Check cron is installed**:
   ```bash
   which cron
   ```

2. **Check cron service is running**:
   ```bash
   sudo systemctl status cron
   ```

3. **Verify crontab entry**:
   ```bash
   sudo crontab -l
   ```

4. **Check Laravel logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Command Not Removing Users

1. **Check if users are actually offline**:
   - Users must have status 'away' AND last activity older than threshold
   - Check user status: `php artisan tinker` then `User::find(1)->status`

2. **Run command with verbose output**:
   ```bash
   php artisan users:remove-inactive -v
   ```

3. **Check database directly**:
   ```sql
   SELECT * FROM room_user WHERE last_activity < NOW() - INTERVAL 10 MINUTE;
   ```

## Manual Execution

You can manually trigger the command at any time:

```bash
php artisan users:remove-inactive
```

Or with custom threshold:

```bash
php artisan users:remove-inactive --minutes=5
```

## Notes

- The command runs in the background to avoid blocking other scheduled tasks
- The command uses `withoutOverlapping()` to prevent multiple instances running simultaneously
- Users are only removed if they are truly offline (status = 'away' AND inactive for threshold time)
- The command broadcasts presence events so frontend clients are notified of user removals

