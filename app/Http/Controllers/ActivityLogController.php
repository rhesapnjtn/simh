<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->with('user')
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $logs = $query->paginate(20);

        return response()->json($logs->through(fn (ActivityLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'user' => $log->user?->name,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->format('d M Y H:i:s'),
        ]));
    }
}