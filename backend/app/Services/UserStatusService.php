<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserStatusService
{
    /**
     * Cache key prefix for user status
     */
    const CACHE_PREFIX = 'user_status:';
    
    /**
     * Cache TTL in seconds (5 minutes)
     */
    const CACHE_TTL = 300;
    
    /**
     * Batch update interval in seconds (30 seconds)
     */
    const BATCH_UPDATE_INTERVAL = 30;

    /**
     * Update user status (fast cache update, queue DB update)
     * 
     * @param int $userId
     * @param string $status
     * @param int|null $lastActivity Timestamp in milliseconds
     * @return void
     */
    public function updateStatus(int $userId, string $status, ?int $lastActivity = null): void
    {
        $timestamp = $lastActivity ? (int)($lastActivity / 1000) : now()->timestamp;
        
        // Update cache immediately (fast, non-blocking)
        $cacheKey = self::CACHE_PREFIX . $userId;
        Cache::put($cacheKey, [
            'status' => $status,
            'last_activity' => $timestamp,
            'updated_at' => now()->timestamp,
        ], self::CACHE_TTL);
        
        // Queue DB update (batched, non-blocking)
        // Use cache to track pending updates for batching
        $pendingKey = 'user_status_pending:' . $userId;
        Cache::put($pendingKey, [
            'status' => $status,
            'last_activity' => date('Y-m-d H:i:s', $timestamp),
        ], self::BATCH_UPDATE_INTERVAL + 10);
        
        // Add to pending set for batch processing
        $this->addToPendingSet($userId);
    }

    /**
     * Get user status from cache or database
     * 
     * @param int $userId
     * @return array{status: string, last_activity: int|null}
     */
    public function getStatus(int $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . $userId;
        
        // Try cache first (fast)
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return [
                'status' => $cached['status'],
                'last_activity' => $cached['last_activity'] * 1000, // Convert to milliseconds
            ];
        }
        
        // Fallback to database
        $user = User::find($userId);
        if ($user) {
            $lastActivity = $user->last_activity 
                ? $user->last_activity->timestamp * 1000 
                : null;
            
            // Cache the result
            Cache::put($cacheKey, [
                'status' => $user->status ?? 'away',
                'last_activity' => $user->last_activity ? $user->last_activity->timestamp : null,
                'updated_at' => now()->timestamp,
            ], self::CACHE_TTL);
            
            return [
                'status' => $user->status ?? 'away',
                'last_activity' => $lastActivity,
            ];
        }
        
        return [
            'status' => 'away',
            'last_activity' => null,
        ];
    }

    /**
     * Batch update statuses in database (called by scheduled job)
     * This batches all pending updates to minimize DB writes
     * 
     * @return int Number of users updated
     */
    public function batchUpdateStatuses(): int
    {
        $updated = 0;
        $batchSize = 100; // Process in batches to avoid memory issues
        
        // Get all pending status updates from cache
        $pattern = 'user_status_pending:*';
        
        // Note: Laravel cache doesn't support pattern matching directly
        // We'll use a different approach: track pending updates in a set
        $pendingSetKey = 'user_status_pending_set';
        $pendingUserIds = Cache::get($pendingSetKey, []);
        
        if (empty($pendingUserIds)) {
            return 0;
        }
        
        // Process in batches
        $chunks = array_chunk($pendingUserIds, $batchSize);
        
        foreach ($chunks as $chunk) {
            $updates = [];
            
            foreach ($chunk as $userId) {
                $pendingKey = 'user_status_pending:' . $userId;
                $pending = Cache::get($pendingKey);
                
                if ($pending) {
                    $updates[] = [
                        'id' => $userId,
                        'status' => $pending['status'],
                        'last_activity' => $pending['last_activity'],
                    ];
                    
                    // Remove from pending
                    Cache::forget($pendingKey);
                }
            }
            
            if (!empty($updates)) {
                // Batch update using parameterized queries for security and performance
                $ids = array_column($updates, 'id');
                
                // Build CASE statements safely
                $statusCases = [];
                $activityCases = [];
                $bindings = [];
                
                foreach ($updates as $update) {
                    $statusCases[] = "WHEN ? THEN ?";
                    $activityCases[] = "WHEN ? THEN ?";
                    $bindings[] = $update['id'];
                    $bindings[] = $update['status'];
                    $bindings[] = $update['id'];
                    $bindings[] = $update['last_activity'];
                }
                
                $statusCaseSql = implode(' ', $statusCases);
                $activityCaseSql = implode(' ', $activityCases);
                $idsPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                
                // Add IDs to bindings for WHERE clause
                $bindings = array_merge($bindings, $ids);
                
                DB::update(
                    "UPDATE users SET 
                        status = CASE id {$statusCaseSql} END,
                        last_activity = CASE id {$activityCaseSql} END,
                        updated_at = NOW()
                    WHERE id IN ({$idsPlaceholders})",
                    $bindings
                );
                
                $updated += count($updates);
            }
        }
        
        // Clear pending set
        Cache::forget($pendingSetKey);
        
        return $updated;
    }

    /**
     * Add user to pending updates set (for batching)
     * 
     * @param int $userId
     * @return void
     */
    public function addToPendingSet(int $userId): void
    {
        $pendingSetKey = 'user_status_pending_set';
        $pendingUserIds = Cache::get($pendingSetKey, []);
        
        if (!in_array($userId, $pendingUserIds)) {
            $pendingUserIds[] = $userId;
            Cache::put($pendingSetKey, $pendingUserIds, self::BATCH_UPDATE_INTERVAL + 60);
        }
    }

    /**
     * Get multiple user statuses efficiently (for loading users list)
     * 
     * @param array $userIds
     * @return array<int, array{status: string, last_activity: int|null}>
     */
    public function getMultipleStatuses(array $userIds): array
    {
        $result = [];
        $missingIds = [];
        
        // Try cache first
        foreach ($userIds as $userId) {
            $cacheKey = self::CACHE_PREFIX . $userId;
            $cached = Cache::get($cacheKey);
            
            if ($cached) {
                $result[$userId] = [
                    'status' => $cached['status'],
                    'last_activity' => $cached['last_activity'] ? $cached['last_activity'] * 1000 : null,
                ];
            } else {
                $missingIds[] = $userId;
            }
        }
        
        // Fetch missing from database in one query
        if (!empty($missingIds)) {
            $users = User::whereIn('id', $missingIds)
                ->select('id', 'status', 'last_activity')
                ->get();
            
            foreach ($users as $user) {
                $lastActivity = $user->last_activity 
                    ? $user->last_activity->timestamp * 1000 
                    : null;
                
                $result[$user->id] = [
                    'status' => $user->status ?? 'away',
                    'last_activity' => $lastActivity,
                ];
                
                // Cache the result
                $cacheKey = self::CACHE_PREFIX . $user->id;
                Cache::put($cacheKey, [
                    'status' => $user->status ?? 'away',
                    'last_activity' => $user->last_activity ? $user->last_activity->timestamp : null,
                    'updated_at' => now()->timestamp,
                ], self::CACHE_TTL);
            }
        }
        
        return $result;
    }
}

