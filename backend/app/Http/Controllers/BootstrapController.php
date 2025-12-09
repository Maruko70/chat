<?php

namespace App\Http\Controllers;

use App\Models\SiteSettings;
use App\Models\Room;
use App\Models\Story;
use App\Models\WallPost;
use App\Models\WallPostLike;
use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BootstrapController extends Controller
{
    /**
     * Get all bootstrap data in a single request
     * This includes frequently accessed data that doesn't change often
     * Cached for 5 minutes to reduce database load
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Create cache key based on user (authenticated users see different data)
        $cacheKey = $user 
            ? "bootstrap_data_user_{$user->id}" 
            : 'bootstrap_data_guest';
        
        // Cache for 5 minutes
        $data = Cache::remember($cacheKey, 300, function () use ($user, $request) {
            // Get site settings (cached separately for 1 hour)
            $settings = Cache::remember('site_settings_all', 3600, function () {
                return SiteSettings::orderBy('key')->get();
            });
            
            // Transform settings into a key-value object
            $settingsObject = [];
            foreach ($settings as $setting) {
                $settingsObject[$setting->key] = [
                    'id' => $setting->id,
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'type' => $setting->type,
                    'description' => $setting->description,
                    'updated_at' => $setting->updated_at,
                ];
            }
            
            // Get public rooms (or user's rooms if authenticated)
            $rooms = $user
                ? Room::where('is_public', true)
                    ->orWhereHas('users', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->with(['users.media', 'users.roleGroups'])
                    ->latest()
                    ->get()
                : Room::where('is_public', true)
                    ->with(['users.media', 'users.roleGroups'])
                    ->latest()
                    ->get();
            
            $bootstrapData = [
                'site_settings' => $settingsObject,
                'rooms' => $rooms,
                'timestamp' => now()->toIso8601String(),
            ];
            
            // Add authenticated user data
            if ($user) {
                // Get user profile
                $user->load('media', 'roleGroups');
                $profile = $user->toArray();
                
                // Ensure color settings have defaults if null
                if (!$profile['name_color']) {
                    $profile['name_color'] = ['r' => 69, 'g' => 9, 'b' => 36];
                }
                if (!$profile['message_color']) {
                    $profile['message_color'] = ['r' => 69, 'g' => 9, 'b' => 36];
                }
                if (!$profile['name_bg_color']) {
                    $profile['name_bg_color'] = null;
                }
                if (!$profile['image_border_color']) {
                    $profile['image_border_color'] = ['r' => 69, 'g' => 9, 'b' => 36];
                }
                if (!$profile['bio_color']) {
                    $profile['bio_color'] = ['r' => 107, 'g' => 114, 'b' => 128];
                }
                if (!$profile['room_font_size']) {
                    $profile['room_font_size'] = 14;
                }
                
                $bootstrapData['profile'] = $profile;
                
                // Get active stories
                $allStories = Story::active()
                    ->latest()
                    ->with(['user:id,name,username,avatar_url'])
                    ->get();
                
                $groupedStories = $allStories->groupBy('user_id');
                $formattedStories = [];
                foreach ($groupedStories as $storyUserId => $userStories) {
                    $firstStory = $userStories->first();
                    if (!$firstStory || !$firstStory->user) {
                        continue;
                    }
                    
                    $storyUser = $firstStory->user;
                    $storiesArray = $userStories->map(function ($story) use ($user) {
                        return [
                            'id' => $story->id,
                            'media_url' => $story->media_url,
                            'media_type' => $story->media_type,
                            'caption' => $story->caption,
                            'expires_at' => $story->expires_at->toISOString(),
                            'created_at' => $story->created_at->toISOString(),
                            'is_viewed' => $story->isViewedBy($user->id),
                            'views_count' => $story->views()->count(),
                        ];
                    })->values()->all();
                    
                    $formattedStories[] = [
                        'user' => [
                            'id' => $storyUser->id,
                            'name' => $storyUser->name,
                            'username' => $storyUser->username,
                            'avatar_url' => $storyUser->avatar_url,
                        ],
                        'stories' => $storiesArray,
                        'has_unviewed' => collect($storiesArray)->contains(function ($story) {
                            return !$story['is_viewed'];
                        }),
                    ];
                }
                $bootstrapData['stories'] = $formattedStories;
                
                // Get unread count for private messages
                $unreadCount = Cache::remember("private_unread_count_user_{$user->id}", 60, function () use ($user) {
                    return PrivateMessage::where('recipient_id', $user->id)
                        ->whereNull('read_at')
                        ->count();
                });
                $bootstrapData['unread_count'] = $unreadCount;
                
                // Get active users (users who are members of public rooms)
                $userIds = DB::table('room_user')
                    ->join('rooms', 'room_user.room_id', '=', 'rooms.id')
                    ->where('rooms.is_public', true)
                    ->distinct()
                    ->pluck('room_user.user_id')
                    ->toArray();
                
                $activeUsers = User::whereIn('id', $userIds)
                    ->with('media', 'roleGroups')
                    ->get();
                $bootstrapData['active_users'] = $activeUsers;
                
                // Get wall posts for general room (room 1 or first public room)
                $generalRoom = Room::where('id', 1)->first();
                if (!$generalRoom) {
                    $generalRoom = Room::where('is_public', true)
                        ->orderBy('id', 'asc')
                        ->first();
                }
                
                if ($generalRoom && $generalRoom->users()->where('user_id', $user->id)->exists()) {
                    $cacheVersion = Cache::get("wall_posts_cache_version_room_{$generalRoom->id}", 1);
                    $cacheKey = "wall_posts_room_{$generalRoom->id}_user_{$user->id}_page_1_v{$cacheVersion}";
                    
                    $wallPosts = Cache::remember($cacheKey, 3600, function () use ($generalRoom, $user) {
                        $posts = WallPost::where('room_id', $generalRoom->id)
                            ->with(['user.media', 'user.roleGroups'])
                            ->withCount('likes')
                            ->orderBy('created_at', 'desc')
                            ->limit(20)
                            ->get();
                        
                        $posts->transform(function ($post) use ($user) {
                            $post->is_liked = $post->isLikedBy($user->id);
                            if ($post->image) {
                                $post->image_url = \Illuminate\Support\Facades\Storage::url($post->image);
                            }
                            return $post;
                        });
                        
                        return $posts;
                    });
                    
                    $bootstrapData['wall_posts'] = $wallPosts->toArray();
                    
                    // Get wall creator for general room
                    $wallCreatorCacheKey = "wall_creator_room_{$generalRoom->id}";
                    $wallCreator = Cache::remember($wallCreatorCacheKey, 3600, function () use ($generalRoom) {
                        $topCreators = User::select('users.*', DB::raw('COUNT(wall_post_likes.id) as total_likes'))
                            ->join('wall_posts', 'users.id', '=', 'wall_posts.user_id')
                            ->join('wall_post_likes', 'wall_posts.id', '=', 'wall_post_likes.wall_post_id')
                            ->where('wall_posts.room_id', $generalRoom->id)
                            ->groupBy('users.id')
                            ->orderByRaw('COUNT(wall_post_likes.id) DESC')
                            ->limit(3)
                            ->get();
                        
                        if ($topCreators->isEmpty()) {
                            return [
                                'wall_creator' => null,
                                'total_likes' => 0,
                                'top_creators' => [],
                            ];
                        }
                        
                        // Load relationships and calculate total likes for each
                        $topCreators->each(function ($creator) use ($generalRoom) {
                            $creator->load('media', 'roleGroups');
                            $creator->total_likes = DB::table('wall_post_likes')
                                ->join('wall_posts', 'wall_post_likes.wall_post_id', '=', 'wall_posts.id')
                                ->where('wall_posts.room_id', $generalRoom->id)
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
                    
                    $bootstrapData['wall_creator'] = $wallCreator;
                } else {
                    $bootstrapData['wall_posts'] = [];
                    $bootstrapData['wall_creator'] = [
                        'wall_creator' => null,
                        'total_likes' => 0,
                        'top_creators' => [],
                    ];
                }
            } else {
                // For guests, add empty arrays/null values
                $bootstrapData['profile'] = null;
                $bootstrapData['stories'] = [];
                $bootstrapData['unread_count'] = 0;
                $bootstrapData['active_users'] = [];
                $bootstrapData['wall_posts'] = [];
                $bootstrapData['wall_creator'] = [
                    'wall_creator' => null,
                    'total_likes' => 0,
                    'top_creators' => [],
                ];
            }
            
            return $bootstrapData;
        });
        
        return response()->json($data);
    }
    
    /**
     * Clear bootstrap cache (useful for admin when data changes)
     */
    public function clearCache(): JsonResponse
    {
        // Clear all bootstrap caches
        Cache::flush(); // Or be more specific: Cache::forget('bootstrap_data_*')
        
        return response()->json(['message' => 'Bootstrap cache cleared']);
    }
}

