<?php

namespace App\Http\Controllers;

use App\Events\UserStatusUpdated;
use App\Services\UserStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserStatusController extends Controller
{
    public function __construct(
        private UserStatusService $statusService
    ) {}

    /**
     * Update current user's status
     * This endpoint is called frequently, so it's optimized for speed
     * Supports both PUT (JSON) and POST (sendBeacon blob) requests
     */
    public function update(Request $request): JsonResponse
    {
        // Handle sendBeacon blob requests (for beforeunload)
        $data = $request->all();
        if (empty($data) && $request->getContent()) {
            $content = $request->getContent();
            $data = json_decode($content, true) ?? [];
        }
        
        $validated = validator($data, [
            'status' => 'required|string|in:active,inactive_tab,private_disabled,away,incognito',
            'last_activity' => 'sometimes|nullable|integer', // Timestamp in milliseconds
        ])->validate();

        $user = $request->user();
        
        // Get previous status to check if it changed to "active"
        $previousStatus = $this->statusService->getStatus($user->id)['status'];
        
        // Update status in cache (fast, non-blocking)
        $this->statusService->updateStatus(
            $user->id,
            $validated['status'],
            $validated['last_activity'] ?? null
        );
        
        // Add to pending set for batch DB update
        $this->statusService->addToPendingSet($user->id);

        // If status changed to "active", broadcast to all users via global channel
        if ($validated['status'] === 'active' && $previousStatus !== 'active') {
            broadcast(new UserStatusUpdated($user, $validated['status']));
        }

        return response()->json([
            'success' => true,
            'status' => $validated['status'],
        ]);
    }

    /**
     * Get statuses for multiple users (for loading user lists)
     * Optimized for bulk reads
     */
    public function getMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $statuses = $this->statusService->getMultipleStatuses($validated['user_ids']);

        return response()->json([
            'statuses' => $statuses,
        ]);
    }
}

