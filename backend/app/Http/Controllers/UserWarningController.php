<?php

namespace App\Http\Controllers;

use App\Models\UserWarning;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserWarningController extends Controller
{
    /**
     * Get warnings for the authenticated user.
     */
    public function myWarnings(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $warnings = UserWarning::where('user_id', $user->id)
            ->with(['warnedBy:id,username,name', 'violation'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($warnings);
    }

    /**
     * Mark warning as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        $warning = UserWarning::where('user_id', $user->id)
            ->findOrFail($id);
        
        $warning->markAsRead();

        return response()->json($warning);
    }

    /**
     * Get warnings for a specific user (admin only).
     */
    public function getUserWarnings(Request $request, string $userId): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $warnings = UserWarning::where('user_id', $userId)
            ->with(['warnedBy:id,username,name', 'violation'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($warnings);
    }

    /**
     * Create a manual warning (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|max:1000',
        ]);

        $warning = UserWarning::create([
            'user_id' => $validated['user_id'],
            'warned_by' => $currentUser->id,
            'reason' => $validated['reason'],
            'type' => 'manual',
            'is_read' => false,
        ]);

        $warning->load(['warnedBy', 'user']);

        return response()->json($warning, 201);
    }

    /**
     * Delete a warning (admin only).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $warning = UserWarning::findOrFail($id);
        $warning->delete();

        return response()->json(['message' => 'Warning deleted successfully']);
    }
}



