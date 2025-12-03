<?php

namespace App\Http\Controllers;

use App\Models\ScheduledMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScheduledMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ScheduledMessage::query();

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by room_id if provided
        if ($request->has('room_id')) {
            $query->forRoom($request->room_id);
        }

        // Search by title or message
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('message', 'like', '%' . $search . '%');
            });
        }

        $messages = $query->with('room:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($messages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:daily,welcoming',
            'time_span' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'room_id' => 'nullable|exists:rooms,id',
            'is_active' => 'nullable|boolean',
        ]);

        $scheduledMessage = ScheduledMessage::create($validated);
        $scheduledMessage->load('room:id,name,slug');

        return response()->json($scheduledMessage, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $scheduledMessage = ScheduledMessage::with('room:id,name,slug')->findOrFail($id);
        return response()->json($scheduledMessage);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $scheduledMessage = ScheduledMessage::findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|in:daily,welcoming',
            'time_span' => 'sometimes|integer|min:1',
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string|max:5000',
            'room_id' => 'nullable|exists:rooms,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $scheduledMessage->update($validated);
        $scheduledMessage->load('room:id,name,slug');

        return response()->json($scheduledMessage);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $scheduledMessage = ScheduledMessage::findOrFail($id);
        $scheduledMessage->delete();

        return response()->json(['message' => 'Scheduled message deleted successfully']);
    }
}
