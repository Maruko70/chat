<?php

namespace App\Http\Controllers;

use App\Events\WallPostSent;
use App\Models\Room;
use App\Models\User;
use App\Models\WallPost;
use App\Models\WallPostLike;
use App\Models\WallPostComment;
use App\Services\WordFilterService;
use App\Traits\ChecksBans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class WallPostController extends Controller
{
    use ChecksBans;

    /**
     * Display a listing of wall posts for a room.
     */
    public function index(Request $request, string $roomId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);

        // Check if user is a member of the room
        if (!$room->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $userId = $request->user()->id;
        $page = $request->get('page', 1);
        
        // Get cache version for this room (increments when cache needs invalidation)
        $cacheVersion = Cache::get("wall_posts_cache_version_room_{$roomId}", 1);
        
        // Cache key includes room ID, user ID (for is_liked), page number, and version
        $cacheKey = "wall_posts_room_{$roomId}_user_{$userId}_page_{$page}_v{$cacheVersion}";
        
        // Cache for 1 hour (3600 seconds) - wall posts change less frequently than messages
        $wallPosts = Cache::remember($cacheKey, 3600, function () use ($roomId, $userId) {
            $posts = WallPost::where('room_id', $roomId)
                ->with(['user.media', 'user.roleGroups', 'likes.user', 'comments.user.media'])
                ->withCount('likes')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            // Add is_liked flag for each post
            $posts->getCollection()->transform(function ($post) use ($userId) {
                $post->is_liked = $post->isLikedBy($userId);
                return $post;
            });
            
            return $posts;
        });

        return response()->json($wallPosts);
    }
    
    /**
     * Clear wall posts cache for a room by incrementing version number.
     * This invalidates all cached pages for all users efficiently.
     */
    private function clearWallPostsCache(string $roomId): void
    {
        // Increment cache version to invalidate all cached pages
        $currentVersion = Cache::get("wall_posts_cache_version_room_{$roomId}", 1);
        Cache::forever("wall_posts_cache_version_room_{$roomId}", $currentVersion + 1);
        
        // Also clear wall creator cache
        Cache::forget("wall_creator_room_{$roomId}");
    }

    /**
     * Store a newly created wall post.
     */
    public function store(Request $request, string $roomId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);

        // Check if user is a member of the room
        if (!$room->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'image' => 'nullable|image|max:10240', // 10MB max
            'youtube_video' => 'nullable|array',
            'youtube_video.id' => 'required_with:youtube_video|string',
            'youtube_video.title' => 'required_with:youtube_video|string',
            'youtube_video.thumbnail' => 'required_with:youtube_video|string',
        ]);

        // At least content, image, or youtube_video must be provided
        if (empty($validated['content']) && !$request->hasFile('image') && empty($validated['youtube_video'])) {
            return response()->json([
                'message' => 'Post must have content, image, or YouTube video',
                'errors' => ['content' => ['Post cannot be empty']]
            ], 422);
        }

        $content = $validated['content'] ?? '';
        
        // Check for filtered words in wall posts if content exists
        if (!empty($content) && WordFilterService::containsFilteredWords($content, 'chats')) {
            // Log the violation before rejecting
            WordFilterService::logViolation(
                $request->user()->id,
                $content,
                'chats'
            );
            
            return response()->json([
                'message' => 'Your post contains inappropriate content',
                'errors' => ['content' => ['Your post contains words that are not allowed']]
            ], 422);
        }

        // Filter the content before storing
        $filteredContent = !empty($content) ? WordFilterService::filterText($content, 'chats') : '';
        
        // If content was filtered, log it as a violation
        if (!empty($content) && $filteredContent !== $content) {
            WordFilterService::logViolation(
                $request->user()->id,
                $content,
                'chats',
                null,
                $filteredContent
            );
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('wall-posts', 'public');
        }

        $wallPost = WallPost::create([
            'room_id' => $roomId,
            'user_id' => $request->user()->id,
            'content' => $filteredContent,
            'image' => $imagePath,
            'youtube_video' => $validated['youtube_video'] ?? null,
        ]);

        $wallPost->load(['user.media', 'user.roleGroups', 'likes', 'comments']);
        $wallPost->is_liked = false;
        $wallPost->likes_count = 0;

        // Clear wall posts cache for this room
        $this->clearWallPostsCache($roomId);

        // Broadcast the wall post immediately
        try {
            broadcast(new WallPostSent($wallPost))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to broadcast wall post', [
                'error' => $e->getMessage(),
                'wall_post_id' => $wallPost->id,
            ]);
        }

        // Add image URL if exists
        if ($wallPost->image) {
            $wallPost->image_url = Storage::url($wallPost->image);
        }

        return response()->json($wallPost, 201);
    }

    /**
     * Remove the specified wall post.
     */
    public function destroy(Request $request, string $roomId, string $id): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);
        $wallPost = WallPost::findOrFail($id);

        // Check if wall post belongs to the room
        if ($wallPost->room_id != $roomId) {
            return response()->json(['message' => 'Wall post does not belong to this room'], 404);
        }

        // Check if user is the author or has admin permissions
        $isAuthor = $wallPost->user_id === $request->user()->id;
        $hasPermission = $request->user()->all_permissions['delete_wall_posts'] ?? false;

        if (!$isAuthor && !$hasPermission) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated image if exists
        if ($wallPost->image && Storage::disk('public')->exists($wallPost->image)) {
            Storage::disk('public')->delete($wallPost->image);
        }

        $wallPost->delete();

        // Clear wall posts cache for this room
        $this->clearWallPostsCache($roomId);

        return response()->json(['message' => 'Wall post deleted successfully']);
    }

    /**
     * Like or unlike a wall post.
     */
    public function toggleLike(Request $request, string $roomId, string $id): JsonResponse
    {
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);
        $wallPost = WallPost::findOrFail($id);

        if ($wallPost->room_id != $roomId) {
            return response()->json(['message' => 'Wall post does not belong to this room'], 404);
        }

        $like = WallPostLike::where('wall_post_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            WallPostLike::create([
                'wall_post_id' => $id,
                'user_id' => $request->user()->id,
            ]);
            $liked = true;
        }

        $likesCount = $wallPost->likes()->count();

        // Clear wall posts cache for this room (likes affect the display)
        $this->clearWallPostsCache($roomId);

        return response()->json([
            'liked' => $liked,
            'likes_count' => $likesCount,
        ]);
    }

    /**
     * Get comments for a wall post.
     */
    public function getComments(Request $request, string $roomId, string $id): JsonResponse
    {
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);
        $wallPost = WallPost::findOrFail($id);

        if ($wallPost->room_id != $roomId) {
            return response()->json(['message' => 'Wall post does not belong to this room'], 404);
        }

        $comments = $wallPost->comments()
            ->with(['user.media', 'user.roleGroups'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($comments);
    }

    /**
     * Store a comment on a wall post.
     */
    public function storeComment(Request $request, string $roomId, string $id): JsonResponse
    {
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);
        $wallPost = WallPost::findOrFail($id);

        if ($wallPost->room_id != $roomId) {
            return response()->json(['message' => 'Wall post does not belong to this room'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        // Check for filtered words
        if (WordFilterService::containsFilteredWords($validated['content'], 'chats')) {
            WordFilterService::logViolation(
                $request->user()->id,
                $validated['content'],
                'chats'
            );
            
            return response()->json([
                'message' => 'Your comment contains inappropriate content',
                'errors' => ['content' => ['Your comment contains words that are not allowed']]
            ], 422);
        }

        $filteredContent = WordFilterService::filterText($validated['content'], 'chats');
        
        if ($filteredContent !== $validated['content']) {
            WordFilterService::logViolation(
                $request->user()->id,
                $validated['content'],
                'chats',
                null,
                $filteredContent
            );
        }

        $comment = WallPostComment::create([
            'wall_post_id' => $id,
            'user_id' => $request->user()->id,
            'content' => $filteredContent,
        ]);

        $comment->load(['user.media', 'user.roleGroups']);

        // Clear wall posts cache (comments are included in the response)
        $this->clearWallPostsCache($roomId);

        return response()->json($comment, 201);
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(Request $request, string $roomId, string $postId, string $commentId): JsonResponse
    {
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);
        $wallPost = WallPost::findOrFail($postId);
        $comment = WallPostComment::findOrFail($commentId);

        if ($wallPost->room_id != $roomId || $comment->wall_post_id != $postId) {
            return response()->json(['message' => 'Comment does not belong to this wall post'], 404);
        }

        $isAuthor = $comment->user_id === $request->user()->id;
        $hasPermission = $request->user()->all_permissions['delete_wall_posts'] ?? false;

        if (!$isAuthor && !$hasPermission) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        // Clear wall posts cache (comments are included in the response)
        $this->clearWallPostsCache($roomId);

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    /**
     * Get the wall creator (user with most likes on their posts).
     */
    public function getWallCreator(Request $request, string $roomId): JsonResponse
    {
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);

        if (!$room->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Cache key for wall creator
        $cacheKey = "wall_creator_room_{$roomId}";
        
        // Cache for 1 hour (3600 seconds) - wall creator changes less frequently
        $result = Cache::remember($cacheKey, 3600, function () use ($roomId) {
            // Get top 3 creators
            $topCreators = User::select('users.*', DB::raw('COUNT(wall_post_likes.id) as total_likes'))
                ->join('wall_posts', 'users.id', '=', 'wall_posts.user_id')
                ->join('wall_post_likes', 'wall_posts.id', '=', 'wall_post_likes.wall_post_id')
                ->where('wall_posts.room_id', $roomId)
                ->groupBy('users.id')
                ->orderByRaw('COUNT(wall_post_likes.id) DESC')
                ->limit(3)
                ->get();

            if ($topCreators->isEmpty()) {
                return [
                    'wall_creator' => null,
                    'total_likes' => 0,
                    'top_creators' => []
                ];
            }

            // Load relationships and calculate total likes for each
            $topCreators->each(function ($creator) use ($roomId) {
                $creator->load('media', 'roleGroups');
                $creator->total_likes = DB::table('wall_post_likes')
                    ->join('wall_posts', 'wall_post_likes.wall_post_id', '=', 'wall_posts.id')
                    ->where('wall_posts.room_id', $roomId)
                    ->where('wall_posts.user_id', $creator->id)
                    ->count();
            });

            // For backward compatibility, return the first creator as wall_creator
            $wallCreator = $topCreators->first();

            return [
                'wall_creator' => $wallCreator,
                'total_likes' => $wallCreator->total_likes,
                'top_creators' => $topCreators->values()->all(),
            ];
        });

        return response()->json($result);
    }
}
