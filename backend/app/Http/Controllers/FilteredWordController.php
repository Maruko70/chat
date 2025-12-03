<?php

namespace App\Http\Controllers;

use App\Models\FilteredWord;
use App\Services\WordFilterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class FilteredWordController extends Controller
{
    /**
     * Display a listing of filtered words.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions - only users with site_administration permission can manage filtered words
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = FilteredWord::query();

        // Filter by applies_to if provided
        if ($request->has('applies_to')) {
            $query->where('applies_to', $request->applies_to);
        }

        // Filter by is_active if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by word
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('word', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $words = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($words);
    }

    /**
     * Store a newly created filtered word.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'applies_to' => [
                'required',
                Rule::in(['chats', 'names', 'bios', 'walls', 'statuses', 'all']),
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        // Check if word already exists for this type
        $existing = FilteredWord::where('word', $validated['word'])
            ->where('applies_to', $validated['applies_to'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'This word already exists for this type',
                'errors' => ['word' => ['This word is already filtered for this type']]
            ], 422);
        }

        $word = FilteredWord::create([
            'word' => $validated['word'],
            'applies_to' => $validated['applies_to'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Clear cache
        WordFilterService::clearCache();

        return response()->json($word, 201);
    }

    /**
     * Display the specified filtered word.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $word = FilteredWord::findOrFail($id);

        return response()->json($word);
    }

    /**
     * Update the specified filtered word.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $word = FilteredWord::findOrFail($id);

        $validated = $request->validate([
            'word' => 'sometimes|string|max:255',
            'applies_to' => [
                'sometimes',
                Rule::in(['chats', 'names', 'bios', 'walls', 'statuses', 'all']),
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        // Check if word already exists for this type (excluding current word)
        if (isset($validated['word']) || isset($validated['applies_to'])) {
            $checkWord = $validated['word'] ?? $word->word;
            $checkType = $validated['applies_to'] ?? $word->applies_to;

            $existing = FilteredWord::where('word', $checkWord)
                ->where('applies_to', $checkType)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'This word already exists for this type',
                    'errors' => ['word' => ['This word is already filtered for this type']]
                ], 422);
            }
        }

        $word->update($validated);

        // Clear cache
        WordFilterService::clearCache();

        return response()->json($word);
    }

    /**
     * Remove the specified filtered word.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $word = FilteredWord::findOrFail($id);
        $word->delete();

        // Clear cache
        WordFilterService::clearCache();

        return response()->json(['message' => 'Filtered word deleted successfully']);
    }
}

