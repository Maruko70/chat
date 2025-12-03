<?php

namespace App\Http\Controllers;

use App\Models\MembershipDesign;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MembershipDesignController extends Controller
{
    /**
     * Display a listing of membership designs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MembershipDesign::query();

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status if provided
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        } else {
            // By default, only show active designs for non-admin users
            if (!$request->user() || !$request->user()->hasPermission('manage_membership_designs')) {
                $query->where('is_active', true);
            }
        }

        $designs = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($designs);
    }

    /**
     * Store a newly created membership design.
     */
    public function store(Request $request): JsonResponse
    {
        // Check permission - allow users with dashboard access or manage_membership_designs permission
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $user = $request->user();
        if (!$user->hasPermission('manage_membership_designs') && !$user->hasPermission('dashboard')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'required|string|in:background,frame',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'is_active' => 'sometimes',
        ]);

        // Convert is_active to boolean
        $isActive = true;
        if (isset($validated['is_active'])) {
            $isActive = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        // Upload image
        $file = $request->file('image');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::slug(trim($validated['name'])) . '-' . time() . '.' . $extension;
        $path = $file->storeAs('public/membership-designs', $filename);
        $url = url(Storage::url($path));

        $design = MembershipDesign::create([
            'name' => isset($validated['name']) && trim($validated['name']) ? trim($validated['name']) : 'تصميم بدون اسم',
            'type' => $validated['type'],
            'image_url' => $url,
            'description' => null,
            'is_active' => $isActive,
            'priority' => 0,
        ]);

        return response()->json($design, 201);
    }

    /**
     * Display the specified membership design.
     */
    public function show(MembershipDesign $membershipDesign): JsonResponse
    {
        return response()->json($membershipDesign);
    }

    /**
     * Update the specified membership design.
     */
    public function update(Request $request, MembershipDesign $membershipDesign): JsonResponse
    {
        // Check permission - allow users with dashboard access or manage_membership_designs permission
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $user = $request->user();
        if (!$user->hasPermission('manage_membership_designs') && !$user->hasPermission('dashboard')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|required|string|in:background,frame',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_active' => 'sometimes',
        ]);

        // Handle image upload if provided
        if ($request->hasFile('image')) {
            // Delete old image
            if ($membershipDesign->image_url) {
                $oldPath = str_replace(url('/'), '', $membershipDesign->image_url);
                $oldPath = str_replace('/storage/', 'public/', $oldPath);
                if (Storage::exists($oldPath)) {
                    Storage::delete($oldPath);
                }
            }

            // Upload new image
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(trim($validated['name'] ?? $membershipDesign->name)) . '-' . time() . '.' . $extension;
            $path = $file->storeAs('public/membership-designs', $filename);
            $validated['image_url'] = url(Storage::url($path));
        }

        // Prepare update data
        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = trim($validated['name']) ?: 'تصميم بدون اسم';
        }
        if (isset($validated['type'])) {
            $updateData['type'] = $validated['type'];
        }
        if (isset($validated['image_url'])) {
            $updateData['image_url'] = $validated['image_url'];
        }
        if (isset($validated['is_active'])) {
            $updateData['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        
        $membershipDesign->update($updateData);

        return response()->json($membershipDesign);
    }

    /**
     * Remove the specified membership design.
     */
    public function destroy(Request $request, MembershipDesign $membershipDesign): JsonResponse
    {
        // Check permission - allow users with dashboard access or manage_membership_designs permission
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $user = $request->user();
        if (!$user->hasPermission('manage_membership_designs') && !$user->hasPermission('dashboard')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete image file
        if ($membershipDesign->image_url) {
            $oldPath = str_replace(url('/'), '', $membershipDesign->image_url);
            $oldPath = str_replace('/storage/', 'public/', $oldPath);
            if (Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }
        }

        $membershipDesign->delete();

        return response()->json(['message' => 'Design deleted successfully']);
    }
}

