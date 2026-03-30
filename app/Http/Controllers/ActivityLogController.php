<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs.
     * Supports: role filter, activity_type filter, keyword search, sorting, pagination (10/page).
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        // Filter by role (default: admin & superadmin only)
        $query->whereHas('user', function ($q) use ($request) {
            if ($request->filled('role')) {
                $q->where('role', $request->role);
            } else {
                $q->whereIn('role', ['admin', 'superadmin']);
            }
        });

        // Filter by activity_type
        $allowedTypes = ['authentication', 'crud', 'transaction', 'system', 'other'];
        if ($request->filled('activity_type') && in_array($request->activity_type, $allowedTypes)) {
            $query->where('activity_type', $request->activity_type);
        }

        // Keyword search on description or related user name
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('description', 'like', "%{$keyword}%")
                  ->orWhereHas('user', function ($uq) use ($keyword) {
                      $uq->where('name', 'like', "%{$keyword}%");
                  });
            });
        }

        // Sorting
        $allowedSorts = ['id', 'user_id', 'description', 'activity_type', 'created_at', 'updated_at'];
        $sortField    = $request->input('sort_by', 'created_at');
        $sortDir      = strtolower($request->input('sort_dir', 'desc'));

        if (in_array($sortField, $allowedSorts) && in_array($sortDir, ['asc', 'desc'])) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $activityLogs = $query->paginate(10);

        return response()->json($activityLogs);
    }
}
