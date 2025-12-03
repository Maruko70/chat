<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RoleGroup;
use App\Models\LoginLog;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * Get all users with subscriptions (role groups priority > 0).
     */
    public function index(Request $request): JsonResponse
    {
        // Get users who have role groups with priority > 0
        $query = User::whereHas('roleGroups', function ($q) {
            $q->where('role_groups.priority', '>', 0)
              ->where('role_groups.is_active', true)
              ->where(function ($query) {
                  $query->whereNull('role_group_user.expires_at')
                        ->orWhere('role_group_user.expires_at', '>', now());
              });
        })
        ->with(['roleGroups' => function ($q) {
            $q->where('role_groups.priority', '>', 0)
              ->where('role_groups.is_active', true)
              ->where(function ($query) {
                  $query->whereNull('role_group_user.expires_at')
                        ->orWhere('role_group_user.expires_at', '>', now());
              })
              ->orderBy('role_groups.priority', 'desc')
              ->withPivot('expires_at', 'created_at');
        }])
        ->with(['rooms' => function ($q) {
            $q->orderBy('room_user.created_at', 'desc')->limit(1);
        }]);

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by role group
        if ($request->has('role_group_id')) {
            $query->whereHas('roleGroups', function ($q) use ($request) {
                $q->where('role_groups.id', $request->get('role_group_id'));
            });
        }

        $perPage = $request->get('per_page', 20);
        $users = $query->orderBy('username')->paginate($perPage);

        // Enrich with login log data and format response
        $subscriptions = $users->getCollection()->map(function ($user) {
            // Get latest login log
            $latestLogin = LoginLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Get primary role group (highest priority)
            $primaryRoleGroup = $user->roleGroups->first();
            
            // Get role group assignment date from pivot
            $roleGroupPivot = null;
            if ($primaryRoleGroup) {
                $roleGroupPivot = DB::table('role_group_user')
                    ->where('user_id', $user->id)
                    ->where('role_group_id', $primaryRoleGroup->id)
                    ->first();
            }

            // Get current room (user can only be in one room at a time, so get the first one)
            $currentRoom = $user->rooms->first();
            
            // If no room from relationship, try to get from room_user table directly
            if (!$currentRoom) {
                $roomUser = DB::table('room_user')
                    ->where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($roomUser) {
                    $currentRoom = Room::find($roomUser->room_id);
                }
            }

            return [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'chat_name' => $currentRoom ? $currentRoom->name : null,
                'ip_address' => $latestLogin ? $latestLogin->ip_address : $user->ip_address,
                'device' => $latestLogin ? $this->parseUserAgent($latestLogin->user_agent) : null,
                'role_group' => $primaryRoleGroup ? [
                    'id' => $primaryRoleGroup->id,
                    'name' => $primaryRoleGroup->name,
                    'priority' => $primaryRoleGroup->priority,
                ] : null,
                'expire_date' => $primaryRoleGroup && $primaryRoleGroup->pivot ? $primaryRoleGroup->pivot->expires_at : null,
                'last_login_date' => $latestLogin ? $latestLogin->created_at : null,
                'role_given_at' => $roleGroupPivot ? $roleGroupPivot->created_at : null,
                'user' => $user, // Include full user object for reference
            ];
        });

        $response = $users->toArray();
        $response['data'] = $subscriptions->toArray();

        return response()->json($response);
    }

    /**
     * Update user's role group subscription.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'role_group_id' => 'nullable|integer|exists:role_groups,id',
            'expires_at' => 'nullable|date|after:now',
            'remove_subscription' => 'boolean', // If true, remove all role groups with priority > 0
        ]);

        if ($validated['remove_subscription'] ?? false) {
            // Remove all role groups with priority > 0
            $roleGroupsToRemove = RoleGroup::where('priority', '>', 0)
                ->where('is_active', true)
                ->pluck('id');
            
            $user->roleGroups()->detach($roleGroupsToRemove);

            // Ensure user has default role group (priority 0)
            $defaultRoleGroup = RoleGroup::where('priority', 0)
                ->where('is_active', true)
                ->first();
            
            if ($defaultRoleGroup && !$user->roleGroups()->where('role_group_id', $defaultRoleGroup->id)->exists()) {
                $user->roleGroups()->attach($defaultRoleGroup->id);
            }
        } else if (isset($validated['role_group_id'])) {
            // Assign or update role group
            $roleGroup = RoleGroup::findOrFail($validated['role_group_id']);
            
            // Remove all other role groups with priority > 0
            $otherRoleGroups = RoleGroup::where('priority', '>', 0)
                ->where('id', '!=', $roleGroup->id)
                ->where('is_active', true)
                ->pluck('id');
            
            $user->roleGroups()->detach($otherRoleGroups);

            // Attach or update the new role group
            $pivotData = [];
            if (isset($validated['expires_at'])) {
                $pivotData['expires_at'] = $validated['expires_at'];
            }

            if ($user->roleGroups()->where('role_group_id', $roleGroup->id)->exists()) {
                $user->roleGroups()->updateExistingPivot($roleGroup->id, $pivotData);
            } else {
                $user->roleGroups()->attach($roleGroup->id, $pivotData);
            }
        }

        $user->load('roleGroups');
        
        return response()->json([
            'message' => 'Subscription updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Parse user agent to get device name.
     */
    private function parseUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'chrome') !== false && strpos($userAgent, 'edg') === false && strpos($userAgent, 'opr') === false) {
            if (strpos($userAgent, 'wv') !== false) {
                return 'Android WebView';
            }
            return 'Chrome';
        }

        if (strpos($userAgent, 'edg') !== false || strpos($userAgent, 'edge') !== false) {
            return 'Edge';
        }

        if (strpos($userAgent, 'opr') !== false || strpos($userAgent, 'opera') !== false) {
            return 'Opera';
        }

        if (strpos($userAgent, 'firefox') !== false) {
            return 'Firefox';
        }

        if (strpos($userAgent, 'safari') !== false && strpos($userAgent, 'chrome') === false) {
            return 'Safari';
        }

        if (strpos($userAgent, 'msie') !== false || strpos($userAgent, 'trident') !== false) {
            return 'Internet Explorer';
        }

        if (strpos($userAgent, 'samsungbrowser') !== false) {
            return 'Samsung Internet';
        }

        return 'Unknown';
    }
}
