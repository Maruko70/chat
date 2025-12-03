<?php

namespace App\Http\Controllers;

use App\Models\Shortcut;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ShortcutsController extends Controller
{
    /**
     * Display a listing of shortcuts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shortcut::query();

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by key or value
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', '%' . $search . '%')
                  ->orWhere('value', 'like', '%' . $search . '%');
            });
        }

        // Order by key
        $shortcuts = $query->orderBy('key')
            ->get();

        return response()->json($shortcuts);
    }

    /**
     * Store a newly created shortcut.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:shortcuts,key',
            'value' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $shortcut = Shortcut::create($validated);

        // Clear shortcuts cache and bootstrap cache
        Cache::forget('active_shortcuts');
        Cache::forget('bootstrap_data_guest');
        // Clear all user bootstrap caches
        Cache::flush(); // Simplified - in production use cache tags

        return response()->json($shortcut, 201);
    }

    /**
     * Display the specified shortcut.
     */
    public function show(string $id): JsonResponse
    {
        $shortcut = Shortcut::findOrFail($id);
        return response()->json($shortcut);
    }

    /**
     * Update the specified shortcut.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $shortcut = Shortcut::findOrFail($id);

        $validated = $request->validate([
            'key' => 'sometimes|required|string|max:255|unique:shortcuts,key,' . $id,
            'value' => 'sometimes|required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $shortcut->update($validated);

        // Clear shortcuts cache and bootstrap cache
        Cache::forget('active_shortcuts');
        Cache::forget('bootstrap_data_guest');
        // Clear all user bootstrap caches
        Cache::flush(); // Simplified - in production use cache tags

        return response()->json($shortcut);
    }

    /**
     * Remove the specified shortcut.
     */
    public function destroy(string $id): JsonResponse
    {
        $shortcut = Shortcut::findOrFail($id);
        $shortcut->delete();

        // Clear shortcuts cache and bootstrap cache
        Cache::forget('active_shortcuts');
        Cache::forget('bootstrap_data_guest');
        // Clear all user bootstrap caches
        Cache::flush(); // Simplified - in production use cache tags

        return response()->json(['message' => 'Shortcut deleted successfully']);
    }

    /**
     * Get active shortcuts for public use (chat expansion).
     * Cached for 1 hour to reduce database queries.
     */
    public function getActive(): JsonResponse
    {
        $shortcuts = Cache::remember('active_shortcuts', 3600, function () {
            return Shortcut::where('is_active', true)
                ->orderBy('created_at', 'asc')
                ->get(['key', 'value']);
        });

        return response()->json($shortcuts);
    }
}
