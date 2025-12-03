<?php

namespace App\Http\Controllers;

use App\Models\Symbol;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SymbolController extends Controller
{
    /**
     * Display a listing of symbols.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Symbol::query();

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Order by priority, then by name
        $symbols = $query->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($symbols);
    }

    /**
     * Store a newly uploaded symbol.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'name' => 'nullable|string|max:255',
            'type' => 'required|string|in:emoji,role_group_banner,gift_banner,name_banner,user_frame',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $name = $validated['name'] ?? $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::slug(pathinfo($name, PATHINFO_FILENAME)) . '.' . $extension;

        // Ensure unique filename
        $path = 'public/symbols/' . $validated['type'] . '/' . $filename;
        $counter = 1;
        while (Storage::exists($path)) {
            $filename = Str::slug(pathinfo($name, PATHINFO_FILENAME)) . '-' . $counter . '.' . $extension;
            $path = 'public/symbols/' . $validated['type'] . '/' . $filename;
            $counter++;
        }

        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        $file->storeAs(dirname($path), basename($path));

        $symbol = Symbol::create([
            'name' => $validated['name'] ?? pathinfo($filename, PATHINFO_FILENAME),
            'type' => $validated['type'],
            'path' => $path,
            'url' => url(Storage::url($path)),
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($symbol, 201);
    }

    /**
     * Display the specified symbol.
     */
    public function show(string $id): JsonResponse
    {
        $symbol = Symbol::findOrFail($id);
        return response()->json($symbol);
    }

    /**
     * Update the specified symbol.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $symbol = Symbol::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:emoji,role_group_banner,gift_banner,name_banner,user_frame',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $symbol->update($validated);

        return response()->json($symbol);
    }

    /**
     * Remove the specified symbol.
     */
    public function destroy(string $id): JsonResponse
    {
        $symbol = Symbol::findOrFail($id);
        
        // Delete file from storage
        if (Storage::exists($symbol->path)) {
            Storage::delete($symbol->path);
        }

        // Delete from database
        $symbol->delete();

        return response()->json(['message' => 'Symbol deleted successfully']);
    }
}

