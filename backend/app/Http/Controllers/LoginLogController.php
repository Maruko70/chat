<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LoginLogController extends Controller
{
    /**
     * Display a listing of login logs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoginLog::with(['user', 'room'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('is_guest')) {
            $query->where('is_guest', $request->boolean('is_guest'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('room_name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }
}
