<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Traits\ChecksBans;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    use ChecksBans;
    /**
     * Get all reports (for admins).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Report::with(['reporter', 'reportedUser', 'resolvedBy'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by reported user
        if ($request->has('reported_user_id')) {
            $query->where('reported_user_id', $request->get('reported_user_id'));
        }

        // Filter by reporter
        if ($request->has('reporter_id')) {
            $query->where('reporter_id', $request->get('reporter_id'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhereHas('reporter', function ($q) use ($search) {
                      $q->where('username', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('reportedUser', function ($q) use ($search) {
                      $q->where('username', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 20);
        $reports = $query->paginate($perPage);

        return response()->json($reports);
    }

    /**
     * Create a new report.
     */
    public function store(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $validated = $request->validate([
            'reported_user_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|min:1|max:5000',
        ]);

        // Prevent users from reporting themselves
        if ($validated['reported_user_id'] === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot report yourself.',
            ], 400);
        }

        // Check if user already reported this user recently (within last 24 hours)
        $recentReport = Report::where('reporter_id', $request->user()->id)
            ->where('reported_user_id', $validated['reported_user_id'])
            ->where('created_at', '>', now()->subDay())
            ->first();

        if ($recentReport) {
            return response()->json([
                'message' => 'You have already reported this user recently. Please wait before reporting again.',
            ], 400);
        }

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $validated['reported_user_id'],
            'message' => $validated['message'],
            'status' => 'pending',
        ]);

        $report->load(['reporter', 'reportedUser']);

        return response()->json($report, 201);
    }

    /**
     * Get a specific report.
     */
    public function show(string $id): JsonResponse
    {
        $report = Report::with(['reporter', 'reportedUser', 'resolvedBy'])
            ->findOrFail($id);

        return response()->json($report);
    }

    /**
     * Update report status (resolve or dismiss).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:resolved,dismissed',
        ]);

        $report = Report::findOrFail($id);

        $report->update([
            'status' => $validated['status'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $report->load(['reporter', 'reportedUser', 'resolvedBy']);

        return response()->json($report);
    }

    /**
     * Delete a report.
     */
    public function destroy(string $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }

    /**
     * Get reports made by the current user.
     */
    public function myReports(Request $request): JsonResponse
    {
        // Check if user is banned
        $banCheck = $this->checkUserBan($request->user());
        if ($banCheck) {
            return $banCheck;
        }

        $query = Report::with(['reportedUser', 'resolvedBy'])
            ->where('reporter_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $perPage = $request->get('per_page', 20);
        $reports = $query->paginate($perPage);

        return response()->json($reports);
    }
}
