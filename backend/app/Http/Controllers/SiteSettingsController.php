<?php

namespace App\Http\Controllers;

use App\Models\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SiteSettingsController extends Controller
{
    /**
     * Display a listing of site settings.
     * Cached for 1 hour to reduce database queries.
     */
    public function index(): JsonResponse
    {
        $settings = Cache::remember('site_settings_all', 3600, function () {
            return SiteSettings::orderBy('key')->get();
        });
        
        // Transform settings into a key-value object for easier access
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
        
        return response()->json($settingsObject);
    }

    /**
     * Get a specific setting by key.
     */
    public function show(string $key): JsonResponse
    {
        $setting = SiteSettings::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json(['message' => 'Setting not found'], 404);
        }
        
        return response()->json([
            'id' => $setting->id,
            'key' => $setting->key,
            'value' => $setting->value,
            'type' => $setting->type,
            'description' => $setting->description,
            'updated_at' => $setting->updated_at,
        ]);
    }

    /**
     * Update or create a setting.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'nullable',
            'type' => 'sometimes|string|in:image,text,textarea,color,boolean,number',
            'description' => 'nullable|string',
        ]);

        // Convert value based on type
        $value = $validated['value'] ?? null;
        $type = $validated['type'] ?? 'text';
        
        // Handle boolean values - convert to '1' or '0'
        if ($type === 'boolean') {
            $value = ($value === 'true' || $value === true || $value === '1' || $value === 1) ? '1' : '0';
        } elseif ($type === 'number') {
            // Ensure number is stored as string
            $value = $value !== null ? (string) $value : null;
        } else {
            // For other types, ensure it's a string
            $value = $value !== null ? (string) $value : null;
        }

        $setting = SiteSettings::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $validated['description'] ?? null,
            ]
        );

        // Clear cache when settings are updated
        Cache::forget('site_settings_all');
        Cache::forget('bootstrap_data');

        return response()->json([
            'id' => $setting->id,
            'key' => $setting->key,
            'value' => $setting->value,
            'type' => $setting->type,
            'description' => $setting->description,
            'updated_at' => $setting->updated_at,
        ]);
    }

    /**
     * Upload an image for a specific setting key.
     */
    public function uploadImage(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp,ico|max:2048',
        ]);

        $file = $request->file('image');
        $extension = $file->getClientOriginalExtension();
        
        // Generate filename based on key
        $filename = Str::slug($key) . '.' . $extension;
        $path = 'public/site-settings/' . $filename;
        
        // Ensure unique filename
        $counter = 1;
        while (Storage::exists($path)) {
            $filename = Str::slug($key) . '-' . $counter . '.' . $extension;
            $path = 'public/site-settings/' . $filename;
            $counter++;
        }

        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        $file->storeAs(dirname($path), basename($path));
        
        $url = url(Storage::url($path));

        // Update or create the setting
        $setting = SiteSettings::updateOrCreate(
            ['key' => $key],
            [
                'value' => $url,
                'type' => 'image',
                'description' => $validated['description'] ?? null,
            ]
        );

        // Clear cache when settings are updated
        Cache::forget('site_settings_all');
        Cache::forget('bootstrap_data');

        return response()->json([
            'id' => $setting->id,
            'key' => $setting->key,
            'value' => $setting->value,
            'type' => $setting->type,
            'description' => $setting->description,
            'url' => $url,
            'updated_at' => $setting->updated_at,
        ]);
    }

    /**
     * Delete an image setting.
     */
    public function deleteImage(string $key): JsonResponse
    {
        $setting = SiteSettings::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        // Delete file from storage if it exists
        if ($setting->value) {
            // Extract path from URL
            $url = $setting->value;
            if (str_contains($url, '/storage/')) {
                $pathAfterStorage = substr($url, strpos($url, '/storage/') + strlen('/storage/'));
                $path = 'public/' . $pathAfterStorage;
                if (Storage::exists($path)) {
                    Storage::delete($path);
                }
            }
        }

        // Delete setting or set value to null
        $setting->value = null;
        $setting->save();

        // Clear cache when settings are updated
        Cache::forget('site_settings_all');
        Cache::forget('bootstrap_data');

        return response()->json(['message' => 'Image deleted successfully']);
    }
}
