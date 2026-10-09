<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use Illuminate\Http\Request;

class SecurityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SecurityLog::with('user:id,name,email,role')
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('event_type', $request->type);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->paginate(50);

        return response()->json($logs);
    }
}