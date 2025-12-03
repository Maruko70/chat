<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\UserPresence;
use App\Models\Message;
use App\Models\Room;
use App\Models\Shortcut;
use App\Services\WordFilterService;
use App\Traits\ChecksBans;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MessageController extends Controller
{
    use ChecksBans;
    /**
     * Display a listing of messages for a room.
     */
    public function index(Request $request, string $roomId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);

        // For public rooms, automatically add user if not a member
        if ($room->is_public && !$room->users()->where('user_id', $request->user()->id)->exists()) {
            $room->users()->attach($request->user()->id, [
                'role' => 'member',
                'last_activity' => now()
            ]);
            // Broadcast user presence
            broadcast(new UserPresence($request->user(), 'online', $roomId))->toOthers();
        } else {
            // Update last_activity for existing member
            DB::table('room_user')
                ->where('room_id', $roomId)
                ->where('user_id', $request->user()->id)
                ->update(['last_activity' => now()]);
        }

        // Check if user is a member (after potential auto-add)
        if (!$room->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = Message::where('room_id', $roomId)
            ->with(['user.media', 'user.roleGroups'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        
        // Load reply_to user information if present in meta
        foreach ($messages->items() as $message) {
            if (isset($message->meta['reply_to']['user_id'])) {
                $replyUserId = $message->meta['reply_to']['user_id'];
                $replyUser = \App\Models\User::with('media')->find($replyUserId);
                if ($replyUser) {
                    // Get meta array, modify it, and set it back
                    $meta = $message->meta;
                    $meta['reply_to']['user'] = [
                        'id' => $replyUser->id,
                        'name' => $replyUser->name,
                        'username' => $replyUser->username,
                        'avatar_url' => $replyUser->avatar_url,
                    ];
                    $message->meta = $meta;
                }
            }
        }

        return response()->json($messages);
    }

    /**
     * Store a newly created message.
     */
    public function store(Request $request, string $roomId): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $room = Room::findOrFail($roomId);

        // For public rooms, automatically add user if not a member (including guests)
        if ($room->is_public && !$room->users()->where('user_id', $request->user()->id)->exists()) {
            // IMPORTANT: User can only be in ONE room at a time
            // Remove user from ALL other rooms before adding to this room
            $allUserRooms = DB::table('room_user')
                ->where('user_id', $request->user()->id)
                ->where('room_id', '!=', $roomId)
                ->get();
            
            foreach ($allUserRooms as $userRoom) {
                $previousRoom = \App\Models\Room::find($userRoom->room_id);
                if ($previousRoom) {
                    $previousRoom->users()->detach($request->user()->id);
                    // Broadcast user left message to previous room
                    broadcast(new \App\Events\SystemMessage($request->user(), (int)$userRoom->room_id, 'left'));
                    broadcast(new \App\Events\UserPresence($request->user(), 'offline', (int)$userRoom->room_id))->toOthers();
                }
            }
            
            $room->users()->attach($request->user()->id, [
                'role' => 'member',
                'last_activity' => now()
            ]);
            // Broadcast user presence
            broadcast(new UserPresence($request->user(), 'online', $roomId))->toOthers();
        }

        // Check if user is a member (after potential auto-add)
        if (!$room->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'meta' => 'nullable|array',
        ]);

        // Check for filtered words in chat messages
        if (WordFilterService::containsFilteredWords($validated['content'], 'chats')) {
            // Log the violation before rejecting
            WordFilterService::logViolation(
                $request->user()->id,
                $validated['content'],
                'chats'
            );
            
            return response()->json([
                'message' => 'Your message contains inappropriate content',
                'errors' => ['content' => ['Your message contains words that are not allowed']]
            ], 422);
        }

        // Expand shortcuts in message content
        $expandedContent = $this->expandShortcuts($validated['content']);
        
        // Filter the content before storing (in case any words slip through)
        $filteredContent = WordFilterService::filterText($expandedContent, 'chats');
        
        // If content was filtered, log it as a violation
        if ($filteredContent !== $expandedContent) {
            WordFilterService::logViolation(
                $request->user()->id,
                $expandedContent,
                'chats',
                null,
                $filteredContent
            );
        }
        
        $expandedContent = $filteredContent;

        $message = Message::create([
            'room_id' => $roomId,
            'user_id' => $request->user()->id,
            'content' => $expandedContent,
            'meta' => $validated['meta'] ?? null,
        ]);
        
        // Update violation with message_id if content was filtered
        if ($filteredContent !== $expandedContent) {
            \App\Models\FilteredWordViolation::where('user_id', $request->user()->id)
                ->where('content_type', 'chats')
                ->whereNull('message_id')
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->update(['message_id' => $message->id]);
        }

        // Update last_activity for user in this room
        DB::table('room_user')
            ->where('room_id', $roomId)
            ->where('user_id', $request->user()->id)
            ->update(['last_activity' => now()]);

        $message->load(['user.media', 'user.roleGroups']);

        // Broadcast the message immediately (synchronously, not queued)
        try {
            $event = new MessageSent($message);
            // Use broadcastNow() to send immediately without queuing
            broadcast($event)->toOthers();
            
            Log::info('Message broadcast attempt', [
                'message_id' => $message->id,
                'room_id' => $roomId,
                'channel' => 'room.' . $roomId,
                'event_name' => 'message.sent',
                'broadcast_driver' => config('broadcasting.default'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_id' => $message->id,
            ]);
            // Don't fail the request, just log the error
        }

        return response()->json($message, 201);
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

