<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticeController extends Controller
{
    // ── DEALER: get all active notices with read status ────────────
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $notices = Notice::active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notice) use ($userId) {
                $notice->is_read = DB::table('notice_reads')
                    ->where('notice_id', $notice->id)
                    ->where('user_id', $userId)
                    ->exists();
                return $notice;
            });

        return response()->json($notices);
    }

    // ── DEALER: mark a notice as read ──────────────────────────────
    public function markRead(Request $request, int $id)
    {
        $userId = $request->user()->id;

        Notice::findOrFail($id); // ensure it exists

        DB::table('notice_reads')->updateOrInsert(
            ['notice_id' => $id, 'user_id' => $userId],
            ['read_at'   => now()]
        );

        return response()->json(['message' => 'Marked as read.']);
    }

    // ── SUPER: get all notices including inactive ──────────────────
    public function superIndex()
    {
        $notices = Notice::orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notice) {
                $notice->read_count = DB::table('notice_reads')
                    ->where('notice_id', $notice->id)
                    ->count();
                return $notice;
            });

        return response()->json($notices);
    }

    // ── SUPER: create notice ───────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:200'],
            'body'       => ['required', 'string'],
            'type'       => ['sometimes', 'in:info,warning,action'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $notice = Notice::create($data);
        $notice->read_count = 0;

        return response()->json($notice, 201);
    }

    // ── SUPER: toggle active ───────────────────────────────────────
    public function toggle(int $id)
    {
        $notice = Notice::findOrFail($id);
        $notice->update(['is_active' => ! $notice->is_active]);

        return response()->json([
            'message'   => $notice->is_active ? 'Notice activated.' : 'Notice deactivated.',
            'is_active' => $notice->is_active,
        ]);
    }

    // ── SUPER: delete notice ───────────────────────────────────────
    public function destroy(int $id)
    {
        Notice::findOrFail($id)->delete(); // cascade deletes notice_reads
        return response()->json(['message' => 'Notice deleted.']);
    }
}
