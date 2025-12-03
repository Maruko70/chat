<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Events\ProfileUpdated;
use App\Events\UserStatusUpdated;
use App\Services\WordFilterService;
use App\Services\UserStatusService;
use App\Traits\ChecksBans;

class ProfileController extends Controller
{
    use ChecksBans;
    /**
     * Update user profile.
     */
    public function update(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:users,email,' . $request->user()->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'country_flag' => 'sometimes|nullable|string|max:10',
            'bio' => 'sometimes|nullable|string|max:1000',
            'social_media_type' => 'sometimes|nullable|string|in:youtube,instagram,tiktok,x',
            'social_media_url' => 'sometimes|nullable|string|url|max:500',
            'name_color' => 'sometimes|nullable|array',
            'message_color' => 'sometimes|nullable|array',
            'name_bg_color' => 'sometimes|nullable',
            'image_border_color' => 'sometimes|nullable|array',
            'bio_color' => 'sometimes|nullable|array',
            'room_font_size' => 'sometimes|nullable|integer|min:10|max:24',
            'incognito_mode_enabled' => 'sometimes|boolean',
            'private_messages_enabled' => 'sometimes|boolean',
        ]);

        // Check for filtered words in name
        if (isset($validated['name']) && WordFilterService::containsFilteredWords($validated['name'], 'names')) {
            // Log the violation
            WordFilterService::logViolation(
                $request->user()->id,
                $validated['name'],
                'names'
            );
            
            return response()->json([
                'message' => 'Your name contains inappropriate content',
                'errors' => ['name' => ['Your name contains words that are not allowed']]
            ], 422);
        }

        // Check for filtered words in bio
        if (isset($validated['bio']) && $validated['bio'] && WordFilterService::containsFilteredWords($validated['bio'], 'bios')) {
            // Log the violation
            WordFilterService::logViolation(
                $request->user()->id,
                $validated['bio'],
                'bios'
            );
            
            return response()->json([
                'message' => 'Your bio contains inappropriate content',
                'errors' => ['bio' => ['Your bio contains words that are not allowed']]
            ], 422);
        }

        // Filter the content before storing
        if (isset($validated['name'])) {
            $originalName = $validated['name'];
            $validated['name'] = WordFilterService::filterText($validated['name'], 'names');
            
            // If content was filtered, log it
            if ($validated['name'] !== $originalName) {
                WordFilterService::logViolation(
                    $request->user()->id,
                    $originalName,
                    'names',
                    null,
                    $validated['name']
                );
            }
        }
        if (isset($validated['bio']) && $validated['bio']) {
            $originalBio = $validated['bio'];
            $validated['bio'] = WordFilterService::filterText($validated['bio'], 'bios');
            
            // If content was filtered, log it
            if ($validated['bio'] !== $originalBio) {
                WordFilterService::logViolation(
                    $request->user()->id,
                    $originalBio,
                    'bios',
                    null,
                    $validated['bio']
                );
            }
        }

        $user = $request->user();
        
        // Track if privacy settings changed (for status broadcast)
        $privacySettingsChanged = false;
        $oldIncognitoMode = $user->incognito_mode_enabled ?? false;
        $oldPrivateMessages = $user->private_messages_enabled ?? true;
        
        // Handle name_bg_color specially (can be 'transparent' or RGB object)
        if (isset($validated['name_bg_color'])) {
            if ($validated['name_bg_color'] === 'transparent' || $validated['name_bg_color'] === null) {
                $validated['name_bg_color'] = null; // Store null for transparent
            } elseif (is_array($validated['name_bg_color'])) {
                // Keep as array
            } else {
                // If it's a string like 'transparent', set to null
                $validated['name_bg_color'] = $validated['name_bg_color'] === 'transparent' ? null : $validated['name_bg_color'];
            }
        }
        
        // Check if privacy settings are being changed
        if (isset($validated['incognito_mode_enabled']) && $validated['incognito_mode_enabled'] !== $oldIncognitoMode) {
            $privacySettingsChanged = true;
        }
        if (isset($validated['private_messages_enabled']) && $validated['private_messages_enabled'] !== $oldPrivateMessages) {
            $privacySettingsChanged = true;
        }
        
        $user->update($validated);

        // Load media relationship and role groups for avatar_url and permissions
        $user->load('media', 'roleGroups');
        
        // Broadcast profile update event
        broadcast(new ProfileUpdated($user))->toOthers();
        
        // If privacy settings changed, broadcast status update to all users via global channel
        if ($privacySettingsChanged) {
            $statusService = app(UserStatusService::class);
            $statusData = $statusService->getStatus($user->id);
            broadcast(new UserStatusUpdated($user, $statusData['status']));
        }

        return response()->json($user);
    }

    /**
     * Upload profile picture using Spatie Media Library.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $user = $request->user();
        
        // Clear existing avatar and add new one
        $user->clearMediaCollection('avatar');
        $media = $user->addMediaFromRequest('avatar')
            ->toMediaCollection('avatar');

        // Load media relationship and role groups
        $user->load('media', 'roleGroups');
        
        // Broadcast profile update event
        broadcast(new ProfileUpdated($user))->toOthers();

        return response()->json([
            'avatar_url' => $user->avatar_url,
            'user' => $user,
        ]);
    }

    /**
     * Delete profile picture.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $user = $request->user();
        
        // Clear avatar media collection
        $user->clearMediaCollection('avatar');

        // Load media relationship and role groups
        $user->load('media', 'roleGroups');
        
        // Broadcast profile update event
        broadcast(new ProfileUpdated($user))->toOthers();

        return response()->json($user);
    }

    /**
     * Get user profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('media', 'roleGroups');
        
        // Ensure color settings have defaults if null
        if (!$user->name_color) {
            $user->name_color = ['r' => 69, 'g' => 9, 'b' => 36];
        }
        if (!$user->message_color) {
            $user->message_color = ['r' => 69, 'g' => 9, 'b' => 36];
        }
        if (!$user->name_bg_color) {
            $user->name_bg_color = null; // transparent
        }
        if (!$user->image_border_color) {
            $user->image_border_color = ['r' => 69, 'g' => 9, 'b' => 36];
        }
        if (!$user->bio_color) {
            $user->bio_color = ['r' => 107, 'g' => 114, 'b' => 128];
        }
        if (!$user->room_font_size) {
            $user->room_font_size = 14;
        }
        
        return response()->json($user);
    }

    /**
     * Upload premium entry background image.
     */
    public function uploadPremiumEntryBackground(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        // Only allow users with premium_entry enabled
        if (!$user->premium_entry) {
            return response()->json(['message' => 'Premium entry is required'], 403);
        }
        
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max, allow GIF for animations
        ]);

        // Store image using Spatie Media Library
        $user->clearMediaCollection('premium_entry_background');
        $media = $user->addMediaFromRequest('image')
            ->toMediaCollection('premium_entry_background');
        
        // Get the URL and update the premium_entry_background field
        $url = $media->getUrl();
        $user->update(['premium_entry_background' => $url]);

        // Load relationships
        $user->load('media', 'roleGroups', 'membershipBackground', 'membershipFrame');
        
        // Broadcast profile update event
        broadcast(new ProfileUpdated($user))->toOthers();

        return response()->json([
            'premium_entry_background' => $url,
            'user' => $user,
        ]);
    }

    /**
     * Delete premium entry background image.
     */
    public function deletePremiumEntryBackground(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        // Clear premium entry background media collection
        $user->clearMediaCollection('premium_entry_background');
        $user->update(['premium_entry_background' => null]);

        // Load relationships
        $user->load('media', 'roleGroups', 'membershipBackground', 'membershipFrame');
        
        // Broadcast profile update event
        broadcast(new ProfileUpdated($user))->toOthers();

        return response()->json($user);
    }

    /**
     * Update user membership design selections.
     */
    public function updateDesigns(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $validated = $request->validate([
            'membership_background_id' => 'sometimes|nullable|exists:membership_designs,id',
            'membership_frame_id' => 'sometimes|nullable|exists:membership_designs,id',
            'premium_entry_background' => 'sometimes|nullable|string|url',
        ]);
        
        // Only allow users with designed_membership to select designs
        if (!$user->designed_membership && (isset($validated['membership_background_id']) || isset($validated['membership_frame_id']))) {
            return response()->json(['message' => 'Designed membership is required to select designs'], 403);
        }

        $user->update($validated);

        // Load relationships
        $user->load('media', 'roleGroups', 'membershipBackground', 'membershipFrame');
        
        // Broadcast profile update event
        broadcast(new ProfileUpdated($user))->toOthers();

        return response()->json($user);
    }
}

