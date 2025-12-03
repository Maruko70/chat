<?php

namespace App\Http\Controllers;

use App\Models\SiteSettings;
use App\Models\Room;
use App\Models\Shortcut;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

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
            
            // Get active shortcuts
            $shortcuts = Shortcut::where('is_active', true)
                ->orderBy('created_at', 'asc')
                ->get();
            
            return [
                'site_settings' => $settingsObject,
                'rooms' => $rooms,
                'shortcuts' => $shortcuts,
                'timestamp' => now()->toIso8601String(),
            ];
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

