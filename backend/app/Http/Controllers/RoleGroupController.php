<?php

namespace App\Http\Controllers;

use App\Models\RoleGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RoleGroup::query();

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Order by priority, then by name
        $roleGroups = $query->orderBy('priority', 'desc')
            ->orderBy('name')
            ->withCount('users')
            ->get();

        return response()->json($roleGroups);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:role_groups,slug',
            'banner' => 'nullable|string|max:500',
            'priority' => 'nullable|integer|min:0',
            'permissions' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            
            // Ensure uniqueness
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (RoleGroup::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        // Set defaults
        $validated['priority'] = $validated['priority'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $roleGroup = RoleGroup::create($validated);

        return response()->json($roleGroup->loadCount('users'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $roleGroup = RoleGroup::with('users:id,name,username,email')
            ->withCount('users')
            ->findOrFail($id);

        return response()->json($roleGroup);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $roleGroup = RoleGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('role_groups', 'slug')->ignore($roleGroup->id),
            ],
            'banner' => 'nullable|string|max:500',
            'priority' => 'nullable|integer|min:0',
            'permissions' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        // Generate slug if name changed and slug not provided
        if (isset($validated['name']) && !isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            
            // Ensure uniqueness
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (RoleGroup::where('slug', $validated['slug'])->where('id', '!=', $roleGroup->id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        $roleGroup->update($validated);
        $roleGroup->loadCount('users');

        return response()->json($roleGroup);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $roleGroup = RoleGroup::findOrFail($id);
        
        // Detach all users from this role group
        $roleGroup->users()->detach();
        
        $roleGroup->delete();

        return response()->json(['message' => 'Role group deleted successfully']);
    }

    /**
     * Assign users to a role group.
     */
    public function assignUsers(Request $request, string $id): JsonResponse
    {
        $roleGroup = RoleGroup::findOrFail($id);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'expires_at' => 'nullable|date|after:now',
            'expires_at_per_user' => 'nullable|array',
            'expires_at_per_user.*' => 'nullable|date|after:now',
        ]);

        $userIds = $validated['user_ids'];
        $expiresAt = isset($validated['expires_at']) ? $validated['expires_at'] : null;
        $expiresAtPerUser = $validated['expires_at_per_user'] ?? [];

        // Prepare sync data with expiration dates
        $syncData = [];
        foreach ($userIds as $userId) {
            $userExpiresAt = $expiresAtPerUser[$userId] ?? $expiresAt;
            $syncData[$userId] = [
                'expires_at' => $userExpiresAt ? date('Y-m-d H:i:s', strtotime($userExpiresAt)) : null,
            ];
        }

        // Use sync to update existing or create new with expiration dates
        foreach ($syncData as $userId => $pivotData) {
            if ($roleGroup->users()->where('user_id', $userId)->exists()) {
                // Update existing pivot with new expiration
                $roleGroup->users()->updateExistingPivot($userId, $pivotData);
            } else {
                // Attach new user with expiration
                $roleGroup->users()->attach($userId, $pivotData);
            }
        }

        return response()->json([
            'message' => 'Users assigned successfully',
            'role_group' => $roleGroup->loadCount('users'),
        ]);
    }

    /**
     * Remove users from a role group.
     */
    public function removeUsers(Request $request, string $id): JsonResponse
    {
        $roleGroup = RoleGroup::findOrFail($id);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $roleGroup->users()->detach($validated['user_ids']);

        return response()->json([
            'message' => 'Users removed successfully',
            'role_group' => $roleGroup->loadCount('users'),
        ]);
    }
}
