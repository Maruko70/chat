<?php

namespace App\Http\Controllers;

use App\Events\PrivateMessageSent;
use App\Models\PrivateMessage;
use App\Models\Shortcut;
use App\Models\User;
use App\Services\WordFilterService;
use App\Traits\ChecksBans;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrivateMessageController extends Controller
{
    use ChecksBans;

    /**
     * Get conversations list for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        
        // Cache key specific to user (conversations are user-specific)
        $cacheKey = "private_conversations_user_{$userId}";
        
        // Cache for 2 minutes (conversations change frequently with new messages)
        $conversationsData = Cache::remember($cacheKey, 120, function () use ($user, $userId) {
            // Get all conversations (users the current user has exchanged messages with)
            // Using a subquery approach that's PostgreSQL-compliant
            $conversations = DB::select("
                SELECT 
                    other_user_id,
                    MAX(created_at) as last_message_at,
                    COUNT(*) as message_count,
                    SUM(CASE WHEN recipient_id = ? AND read_at IS NULL THEN 1 ELSE 0 END) as unread_count
                FROM (
                    SELECT 
                        CASE 
                            WHEN sender_id = ? THEN recipient_id 
                            ELSE sender_id 
                        END as other_user_id,
                        created_at,
                        recipient_id,
                        read_at
                    FROM private_messages
                    WHERE sender_id = ? OR recipient_id = ?
                ) as pm
                GROUP BY other_user_id
                ORDER BY last_message_at DESC
            ", [$userId, $userId, $userId, $userId]);
            
            // Convert to collection for consistency
            $conversations = collect($conversations);

            // Load user details for each conversation
            return $conversations->map(function ($conversation) use ($user) {
                $otherUser = User::with('media', 'roleGroups')->find($conversation->other_user_id);
                
                if (!$otherUser) {
                    return null;
                }

                // Get the last message
                $lastMessage = PrivateMessage::where(function ($query) use ($user, $otherUser) {
                    $query->where(function ($q) use ($user, $otherUser) {
                        $q->where('sender_id', $user->id)
                          ->where('recipient_id', $otherUser->id);
                    })->orWhere(function ($q) use ($user, $otherUser) {
                        $q->where('sender_id', $otherUser->id)
                          ->where('recipient_id', $user->id);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->first();

                return [
                    'user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'username' => $otherUser->username,
                        'avatar_url' => $otherUser->avatar_url,
                        'country_code' => $otherUser->country_code,
                        'name_color' => $otherUser->name_color,
                        'message_color' => $otherUser->message_color,
                        'name_bg_color' => $otherUser->name_bg_color,
                        'image_border_color' => $otherUser->image_border_color,
                        'bio_color' => $otherUser->bio_color,
                        'role_groups' => $otherUser->roleGroups->map(function ($roleGroup) {
                            return [
                                'id' => $roleGroup->id,
                                'name' => $roleGroup->name,
                                'banner' => $roleGroup->banner,
                                'priority' => $roleGroup->priority,
                                'permissions' => $roleGroup->permissions,
                            ];
                        })->toArray(),
                        'role_group_banner' => $otherUser->role_group_banner,
                        'all_permissions' => $otherUser->all_permissions,
                        'private_messages_enabled' => $otherUser->private_messages_enabled ?? true,
                    ],
                    'last_message' => $lastMessage ? [
                        'id' => $lastMessage->id,
                        'content' => $lastMessage->content,
                        'sender_id' => $lastMessage->sender_id,
                        'created_at' => $lastMessage->created_at->toISOString(),
                        'read_at' => $lastMessage->read_at?->toISOString(),
                    ] : null,
                    'unread_count' => (int) $conversation->unread_count,
                    'message_count' => (int) $conversation->message_count,
                    'last_message_at' => $conversation->last_message_at,
                ];
            })->filter()->values()->toArray();
        });

        return response()->json($conversationsData);
    }
    
    /**
     * Clear conversations cache for users involved in a message.
     */
    private function clearConversationsCache(int $senderId, int $recipientId): void
    {
        Cache::forget("private_conversations_user_{$senderId}");
        Cache::forget("private_conversations_user_{$recipientId}");
    }

    /**
     * Get messages between the authenticated user and another user.
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();
        $otherUser = User::findOrFail($userId);

        // Check if recipient has private messages enabled
        if (!$otherUser->private_messages_enabled && $otherUser->id !== $user->id) {
            return response()->json([
                'message' => 'This user has private messages disabled'
            ], 403);
        }

        // Check if sender has private messages enabled
        if (!$user->private_messages_enabled) {
            return response()->json([
                'message' => 'You have private messages disabled'
            ], 403);
        }

        $messages = PrivateMessage::where(function ($query) use ($user, $otherUser) {
            $query->where(function ($q) use ($user, $otherUser) {
                $q->where('sender_id', $user->id)
                  ->where('recipient_id', $otherUser->id);
            })->orWhere(function ($q) use ($user, $otherUser) {
                $q->where('sender_id', $otherUser->id)
                  ->where('recipient_id', $user->id);
            });
        })
        ->with(['sender.media', 'sender.roleGroups', 'recipient.media', 'recipient.roleGroups'])
        ->orderBy('created_at', 'desc')
        ->paginate(50);

        // Mark messages as read if they are from the other user
        PrivateMessage::where('sender_id', $otherUser->id)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($messages);
    }

    /**
     * Send a private message.
     */
    public function store(Request $request, int $userId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $user = $request->user();
        $recipient = User::findOrFail($userId);

        // Check if recipient has private messages enabled
        if (!$recipient->private_messages_enabled) {
            return response()->json([
                'message' => 'This user has private messages disabled'
            ], 403);
        }

        // Check if sender has private messages enabled
        if (!$user->private_messages_enabled) {
            return response()->json([
                'message' => 'You have private messages disabled'
            ], 403);
        }

        // Prevent users from messaging themselves
        if ($user->id === $recipient->id) {
            return response()->json([
                'message' => 'You cannot send a message to yourself'
            ], 422);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'meta' => 'nullable|array',
        ]);

        // Expand shortcuts in message content
        $expandedContent = $this->expandShortcuts($validated['content']);

        // Check for filtered words in private messages
        if (WordFilterService::containsFilteredWords($expandedContent, 'chats')) {
            // Log the violation before rejecting
            WordFilterService::logViolation(
                $user->id,
                $expandedContent,
                'chats'
            );
            
            return response()->json([
                'message' => 'Your message contains inappropriate content',
                'errors' => ['content' => ['Your message contains words that are not allowed']]
            ], 422);
        }

        // Filter the content before storing (in case any words slip through)
        $filteredContent = WordFilterService::filterText($expandedContent, 'chats');
        
        // If content was filtered, log it as a violation
        if ($filteredContent !== $expandedContent) {
            WordFilterService::logViolation(
                $user->id,
                $expandedContent,
                'chats',
                null,
                $filteredContent
            );
        }

        $message = PrivateMessage::create([
            'sender_id' => $user->id,
            'recipient_id' => $recipient->id,
            'content' => $filteredContent,
            'meta' => $validated['meta'] ?? null,
        ]);

        $message->load(['sender.media', 'sender.roleGroups', 'recipient.media', 'recipient.roleGroups']);

        // Clear conversations cache for both users
        $this->clearConversationsCache($user->id, $recipient->id);
        
        // Clear unread count cache
        Cache::forget("private_unread_count_user_{$recipient->id}");

        // Broadcast the message
        try {
            broadcast(new PrivateMessageSent($message))->toOthers();
            
            Log::info('Private message broadcast attempt', [
                'message_id' => $message->id,
                'sender_id' => $user->id,
                'recipient_id' => $recipient->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast private message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_id' => $message->id,
            ]);
        }

        return response()->json($message, 201);
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();

        $updated = PrivateMessage::where('sender_id', $userId)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Clear conversations cache and unread count cache
        $this->clearConversationsCache($user->id, $userId);
        Cache::forget("private_unread_count_user_{$user->id}");

        return response()->json([
            'message' => 'Messages marked as read',
            'updated_count' => $updated
        ]);
    }

    /**
     * Get unread count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        
        // Cache unread count for 1 minute (changes frequently)
        $cacheKey = "private_unread_count_user_{$userId}";
        
        $count = Cache::remember($cacheKey, 60, function () use ($userId) {
            return PrivateMessage::where('recipient_id', $userId)
                ->whereNull('read_at')
                ->count();
        });

        return response()->json(['count' => $count]);
    }

    /**
     * Expand shortcuts in message content.
     */
    private function expandShortcuts(string $text): string
    {
        // Get active shortcuts from cache (cache for 5 minutes)
        $shortcuts = Cache::remember('active_shortcuts', 300, function () {
            return Shortcut::where('is_active', true)
                ->orderByRaw('LENGTH(key) DESC') // Longer keys first to avoid partial matches
                ->get(['key', 'value']);
        });

        if ($shortcuts->isEmpty()) {
            return $text;
        }

        $expanded = $text;

        foreach ($shortcuts as $shortcut) {
            // Escape special regex characters in the key
            $escapedKey = preg_quote($shortcut->key, '/');
            
            // Use word boundaries to match whole words only
            // \b matches between word (\w) and non-word (\W) characters
            $pattern = '/\b' . $escapedKey . '\b/u';
            
            $expanded = preg_replace($pattern, $shortcut->value, $expanded);
        }

        return $expanded;
    }
}

