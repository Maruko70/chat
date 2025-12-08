<?php

namespace App\Http\Controllers;

use App\Events\StoryCreated;
use App\Models\Story;
use App\Models\StoryView;
use App\Traits\ChecksBans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoryController extends Controller
{
    use ChecksBans;

    /**
     * Get all active stories (global, not room-restricted).
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $userId = $request->user()->id;

        // Get all active stories (global, not room-restricted)
        $allStories = Story::active()
            ->latest()
            ->with(['user:id,name,username,avatar_url'])
            ->get();

        // Group stories by user_id
        $groupedStories = $allStories->groupBy('user_id');

        // Format response: group by user
        $formatted = [];
        foreach ($groupedStories as $storyUserId => $userStories) {
            // Get user from first story (before mapping)
            $firstStory = $userStories->first();
            if (!$firstStory || !$firstStory->user) {
                continue; // Skip if no user data
            }

            $user = $firstStory->user;
            
            // Map stories to array format
            $storiesArray = $userStories->map(function ($story) use ($userId) {
                return [
                    'id' => $story->id,
                    'media_url' => $story->media_url,
                    'media_type' => $story->media_type,
                    'caption' => $story->caption,
                    'expires_at' => $story->expires_at->toISOString(),
                    'created_at' => $story->created_at->toISOString(),
                    'is_viewed' => $story->isViewedBy($userId),
                    'views_count' => $story->views()->count(),
                ];
            })->values()->all();

            $formatted[] = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar_url' => $user->avatar_url,
                ],
                'stories' => $storiesArray,
                'has_unviewed' => collect($storiesArray)->contains(function ($story) {
                    return !$story['is_viewed'];
                }),
            ];
        }

        return response()->json($formatted);
    }

    /**
     * Get stories for a specific user.
     */
    public function show(Request $request, string $userId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $stories = Story::active()
            ->where('user_id', $userId)
            ->latest()
            ->with(['user:id,name,username,avatar_url'])
            ->get()
            ->map(function ($story) use ($request) {
                return [
                    'id' => $story->id,
                    'media_url' => $story->media_url,
                    'media_type' => $story->media_type,
                    'caption' => $story->caption,
                    'expires_at' => $story->expires_at->toISOString(),
                    'created_at' => $story->created_at->toISOString(),
                    'is_viewed' => $story->isViewedBy($request->user()->id),
                    'views_count' => $story->views()->count(),
                ];
            });

        $user = $stories->first()?->user ?? \App\Models\User::find($userId);

        return response()->json([
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ] : null,
            'stories' => $stories,
        ]);
    }

    /**
     * Create a new story.
     */
    public function store(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $validator = Validator::make($request->all(), [
            'media' => 'required|file|mimes:jpeg,jpg,png,gif,mp4,webm|max:10240', // 10MB max
            'caption' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $file = $request->file('media');
        
        // Determine media type
        $mediaType = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';
        
        // Store file
        $path = $file->store('stories', 'public');
        $mediaUrl = Storage::url($path);

        // Create story (expires in 24 hours)
        $story = Story::create([
            'user_id' => $user->id,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'caption' => $request->input('caption'),
            'expires_at' => now()->addHours(24),
        ]);

        // Load user relationship
        $story->load('user:id,name,username,avatar_url');

        // Broadcast story created event
        broadcast(new StoryCreated($story))->toOthers();

        return response()->json([
            'id' => $story->id,
            'media_url' => $story->media_url,
            'media_type' => $story->media_type,
            'caption' => $story->caption,
            'expires_at' => $story->expires_at->toISOString(),
            'created_at' => $story->created_at->toISOString(),
            'is_viewed' => false,
            'views_count' => 0,
        ], 201);
    }

    /**
     * Mark a story as viewed.
     */
    public function markAsViewed(Request $request, string $storyId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $story = Story::active()->findOrFail($storyId);
        $userId = $request->user()->id;

        // Check if already viewed
        if ($story->isViewedBy($userId)) {
            return response()->json(['message' => 'Already viewed'], 200);
        }

        // Create view record
        StoryView::create([
            'story_id' => $story->id,
            'user_id' => $userId,
        ]);

        return response()->json(['message' => 'Story marked as viewed'], 200);
    }

    /**
     * Delete a story.
     */
    public function destroy(Request $request, string $storyId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $story = Story::findOrFail($storyId);

        // Check if user owns the story
        if ($story->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete media file
        $path = str_replace('/storage/', '', $story->media_url);
        Storage::disk('public')->delete($path);

        // Delete story (cascade will delete views)
        $story->delete();

        return response()->json(['message' => 'Story deleted successfully'], 200);
    }

    /**
     * Get story views.
     */
    public function views(Request $request, string $storyId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $story = Story::findOrFail($storyId);

        // Check if user owns the story
        if ($story->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $views = $story->views()
            ->with('user:id,name,username,avatar_url')
            ->latest()
            ->get()
            ->map(function ($view) {
                return [
                    'id' => $view->id,
                    'user' => [
                        'id' => $view->user->id,
                        'name' => $view->user->name,
                        'username' => $view->user->username,
                        'avatar_url' => $view->user->avatar_url,
                    ],
                    'viewed_at' => $view->created_at->toISOString(),
                ];
            });

        return response()->json($views);
    }
}

