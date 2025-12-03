<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('username', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // Filter by guest status
        if ($request->has('is_guest')) {
            $query->where('is_guest', $request->boolean('is_guest'));
        }


        // Pagination
        $perPage = $request->get('per_page', 20);
        $users = $query->with('media', 'roleGroups')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform the response to convert roleGroups to role_groups
        $transformedData = $users->getCollection()->map(function ($user) {
            $userArray = $user->toArray();
            if (isset($userArray['role_groups'])) {
                // Already converted by Laravel
            } elseif (isset($userArray['roleGroups'])) {
                $userArray['role_groups'] = $userArray['roleGroups'];
                unset($userArray['roleGroups']);
            }
            return $userArray;
        });

        // Rebuild pagination response with transformed data
        $response = $users->toArray();
        $response['data'] = $transformedData->toArray();

        return response()->json($response);
    }

    /**
     * Search users by name or username.
     */
    public function search(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('username', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // Limit results
        $limit = $request->get('limit', 20);
        $users = $query->with('media', 'roleGroups')
            ->limit($limit)
            ->get();

        // Transform the response to convert roleGroups to role_groups
        $transformedUsers = $users->map(function ($user) {
            $userArray = $user->toArray();
            if (isset($userArray['role_groups'])) {
                // Already converted by Laravel
            } elseif (isset($userArray['roleGroups'])) {
                $userArray['role_groups'] = $userArray['roleGroups'];
                unset($userArray['roleGroups']);
            }
            return $userArray;
        });

        return response()->json($transformedUsers);
    }

    /**
     * Display the specified user.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = request()->user();
        
        // For admin users, load all role groups (including expired/inactive)
        // For regular users, use the filtered roleGroups relationship
        if ($currentUser && $currentUser->hasPermission('site_administration')) {
            // Load all role groups without filters for admin editing
            $user->load(['media', 'roleGroups' => function ($query) {
                $query->withPivot('expires_at')->withTimestamps();
            }]);
            
            // Also load all role groups (including inactive/expired) via direct relationship
            $allRoleGroups = $user->belongsToMany(\App\Models\RoleGroup::class, 'role_group_user')
                ->withPivot('expires_at')
                ->withTimestamps()
                ->orderBy('role_groups.priority', 'desc')
                ->get();
            
            // Replace the filtered roleGroups with all role groups
            $user->setRelation('roleGroups', $allRoleGroups);
        } else {
            // Regular users get filtered role groups
            $user->load('media', 'roleGroups');
        }
        
        // Transform the response to convert roleGroups to role_groups
        $userArray = $user->toArray();
        if (isset($userArray['role_groups'])) {
            // Already converted by Laravel
        } elseif (isset($userArray['roleGroups'])) {
            $userArray['role_groups'] = $userArray['roleGroups'];
            unset($userArray['roleGroups']);
        }
        
        return response()->json($userArray);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // Check permissions - only users with site_administration permission can manage all users
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
            'email' => 'sometimes|nullable|email|max:255|unique:users,email,' . $id,
            'phone' => 'sometimes|nullable|string|max:20',
            'country_flag' => 'sometimes|nullable|string|max:10',
            'bio' => 'sometimes|nullable|string|max:1000',
            'password' => 'sometimes|string|min:1',
            'is_guest' => 'sometimes|boolean',
            'premium_entry' => 'sometimes|boolean',
            'designed_membership' => 'sometimes|boolean',
            'verify_membership' => 'sometimes|boolean',
            'is_blocked' => 'sometimes|boolean',
            'name_color' => 'sometimes|nullable|array',
            'message_color' => 'sometimes|nullable|array',
            'name_bg_color' => 'sometimes|nullable',
            'image_border_color' => 'sometimes|nullable|array',
            'bio_color' => 'sometimes|nullable|array',
            'room_font_size' => 'sometimes|nullable|integer|min:10|max:24',
            'gifts' => 'sometimes|nullable|array',
        ]);

        // Check for filtered words in name
        if (isset($validated['name']) && \App\Services\WordFilterService::containsFilteredWords($validated['name'], 'names')) {
            return response()->json([
                'message' => 'The name contains inappropriate content',
                'errors' => ['name' => ['The name contains words that are not allowed']]
            ], 422);
        }

        // Check for filtered words in bio
        if (isset($validated['bio']) && $validated['bio'] && \App\Services\WordFilterService::containsFilteredWords($validated['bio'], 'bios')) {
            return response()->json([
                'message' => 'The bio contains inappropriate content',
                'errors' => ['bio' => ['The bio contains words that are not allowed']]
            ], 422);
        }

        // Filter the content before storing
        if (isset($validated['name'])) {
            $validated['name'] = \App\Services\WordFilterService::filterText($validated['name'], 'names');
        }
        if (isset($validated['bio']) && $validated['bio']) {
            $validated['bio'] = \App\Services\WordFilterService::filterText($validated['bio'], 'bios');
        }

        // Handle password separately
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Handle name_bg_color specially
        if (isset($validated['name_bg_color'])) {
            if ($validated['name_bg_color'] === 'transparent' || $validated['name_bg_color'] === null) {
                $validated['name_bg_color'] = null;
            }
        }

        $user->update($validated);
        $user->load('media', 'roleGroups');

        // Transform the response to convert roleGroups to role_groups
        $userArray = $user->toArray();
        if (isset($userArray['role_groups'])) {
            // Already converted by Laravel
        } elseif (isset($userArray['roleGroups'])) {
            $userArray['role_groups'] = $userArray['roleGroups'];
            unset($userArray['roleGroups']);
        }

        return response()->json($userArray);
    }

    /**
     * Remove the specified user (soft delete or ban).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // Check permissions - only users with site_administration permission can delete users
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent self-deletion
        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'Cannot delete yourself'], 400);
        }

        // Delete user (hard delete)
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Block or unblock a user.
     */
    public function toggleBlock(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // Check permissions - only users with site_administration permission can block users
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent self-blocking
        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'Cannot block yourself'], 400);
        }

        $user->update([
            'is_blocked' => !$user->is_blocked,
        ]);

        $user->load('media', 'roleGroups');

        // Transform the response
        $userArray = $user->toArray();
        if (isset($userArray['role_groups'])) {
            // Already converted by Laravel
        } elseif (isset($userArray['roleGroups'])) {
            $userArray['role_groups'] = $userArray['roleGroups'];
            unset($userArray['roleGroups']);
        }

        return response()->json([
            'message' => $user->is_blocked ? 'User blocked successfully' : 'User unblocked successfully',
            'user' => $userArray,
        ]);
    }

    /**
     * Update user's role groups with expiration dates.
     */
    public function updateRoleGroups(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // Check permissions - only users with site_administration permission can manage role groups
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'role_groups' => 'required|array',
            'role_groups.*.role_group_id' => 'required|exists:role_groups,id',
            'role_groups.*.expires_at' => 'nullable|date|after:now',
        ]);

        // Prepare sync data with expiration dates
        $syncData = [];
        foreach ($validated['role_groups'] as $roleGroupData) {
            $roleGroupId = $roleGroupData['role_group_id'];
            $expiresAt = isset($roleGroupData['expires_at']) && $roleGroupData['expires_at'] 
                ? date('Y-m-d H:i:s', strtotime($roleGroupData['expires_at'])) 
                : null;
            
            $syncData[$roleGroupId] = [
                'expires_at' => $expiresAt,
            ];
        }

        // Sync role groups with expiration dates
        $user->roleGroups()->sync($syncData);

        $user->load('media', 'roleGroups');

        // Transform the response
        $userArray = $user->toArray();
        if (isset($userArray['role_groups'])) {
            // Already converted by Laravel
        } elseif (isset($userArray['roleGroups'])) {
            $userArray['role_groups'] = $userArray['roleGroups'];
            unset($userArray['roleGroups']);
        }

        return response()->json([
            'message' => 'Role groups updated successfully',
            'user' => $userArray,
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // Check permissions - only users with site_administration permission can change passwords
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:1',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    /**
     * Get users with premium entry backgrounds.
     */
    public function getPremiumEntryUsers(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions - only users with site_administration permission can view this
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = User::query()
            ->where('premium_entry', true)
            ->whereNotNull('premium_entry_background');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('username', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $users = $query->with('media', 'roleGroups')
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        // Transform the response
        $transformedData = $users->getCollection()->map(function ($user) {
            $userArray = $user->toArray();
            if (isset($userArray['roleGroups'])) {
                $userArray['role_groups'] = $userArray['roleGroups'];
                unset($userArray['roleGroups']);
            }
            return $userArray;
        });

        // Rebuild pagination response with transformed data
        $response = $users->toArray();
        $response['data'] = $transformedData->toArray();

        return response()->json($response);
    }

    /**
     * Upload premium entry background for a user (admin only).
     */
    public function uploadPremiumEntryBackground(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();

        // Check permissions - only users with site_administration permission can do this
        if (!$currentUser->hasPermission('site_administration')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);

        // Ensure user has premium_entry enabled
        if (!$user->premium_entry) {
            return response()->json(['message' => 'User must have premium_entry enabled'], 400);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        // Store image using Spatie Media Library for the target user
        $user->clearMediaCollection('premium_entry_background');
        $media = $user->addMediaFromRequest('image')
            ->toMediaCollection('premium_entry_background');
        
        // Get the URL and update the premium_entry_background field
        $url = $media->getUrl();
        $user->update(['premium_entry_background' => $url]);

        // Load relationships
        $user->load('media', 'roleGroups');

        return response()->json([
            'premium_entry_background' => $url,
            'user' => $user,
        ]);
    }
}
