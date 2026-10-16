<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    // ── PUBLIC: submit message ─────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:150'],
            'email'   => ['required', 'email', 'max:200'],
            'phone'   => ['required', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:3000'],
            'car_id'  => ['nullable', 'exists:cars,id'],
            'type'    => ['nullable', 'in:general,car_enquiry,import_request'],
        ]);

        $data['is_read'] = false;
        $data['type']    = $data['type'] ?? (!empty($data['car_id']) ? 'car_enquiry' : 'general');
        $data['subject'] = $data['subject'] ?? 'General enquiry';

        // Assign to the dealer who owns this car so they see it in their inbox
        if (!empty($data['car_id'])) {
            $car = Car::find($data['car_id']);
            if ($car) {
                $data['user_id'] = $car->user_id;
            }
        }
        // General / import messages: user_id stays null → super admin inbox only

        $message = Message::create($data);

        return response()->json([
            'message' => 'Your enquiry has been received. We will contact you within 24 hours.',
            'data'    => $message,
        ], 201);
    }

    // ── DEALER or SUPER: list messages ─────────────────────────────
    // Dealer: enquiries about their cars + messages assigned to them.  Super admin: everything.
    // Filters: ?type=  ?read=unread|read  ?search=  ?dealer=<user id> (super admin only)
    // Paging:  send ?page=1 to get { data, current_page, last_page, total }.
    //          Without ?page you get a plain list of the newest 100 (for the screens that are not updated yet).
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = $this->scopeFor($user);

        $type = $request->input('type');
        if (in_array($type, ['general', 'car_enquiry', 'import_request'], true)) {
            $query->where('type', $type);
        }

        $read = $request->input('read');
        if ($read === 'unread') {
            $query->where('is_read', false);
        } elseif ($read === 'read') {
            $query->where('is_read', true);
        }

        if ($request->filled('search')) {
            $like = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('subject', 'like', $like);
            });
        }

        if ($user->isSuper() && $request->filled('dealer')) {
            $dealerId = (int) $request->input('dealer');
            $query->where(function ($q) use ($dealerId) {
                $q->where('user_id', $dealerId)
                  ->orWhereIn('car_id', Car::select('id')->where('user_id', $dealerId));
            });
        }

        $query->with(['car:id,user_id,make,model,year', 'car.user:id,name'])
              ->orderByDesc('created_at')
              ->orderByDesc('id');

        if (! $request->has('page')) {
            return response()->json($query->limit(100)->get());
        }

        return response()->json($query->paginate(20)->withQueryString());
    }

    // ── DEALER or SUPER: how many unread (one cheap query, for the badge) ──
    public function unreadCount(Request $request)
    {
        return response()->json([
            'unread' => $this->scopeFor($request->user())->where('is_read', false)->count(),
        ]);
    }

    // ── DEALER or SUPER: single message ────────────────────────────
    public function show(Request $request, Message $message)
    {
        if (! $this->canAccess($request, $message)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($message->load('car:id,user_id,make,model,year'));
    }

    // ── DEALER or SUPER: mark read ─────────────────────────────────
    public function markRead(Request $request, Message $message)
    {
        if (! $this->canAccess($request, $message)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $message->update(['is_read' => true]);
        return response()->json($message);
    }

    // ── DEALER or SUPER: delete ────────────────────────────────────
    public function destroy(Request $request, Message $message)
    {
        if (! $this->canAccess($request, $message)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $message->delete();
        return response()->json(['message' => 'Message deleted.']);
    }

    // ── DEALER or SUPER: one action on many messages, in ONE database call ──
    // POST { "ids": [1,2,3], "action": "read" | "unread" | "delete" }
    // Only messages this person may see are touched; any other ID is silently ignored.
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1', 'max:100'],
            'ids.*'  => ['integer'],
            'action' => ['required', Rule::in(['read', 'unread', 'delete'])],
        ]);

        $query = $this->scopeFor($request->user())->whereIn('id', $data['ids']);

        $affected = match ($data['action']) {
            'read'   => $query->update(['is_read' => true]),
            'unread' => $query->update(['is_read' => false]),
            'delete' => $query->delete(),
        };

        return response()->json(['affected' => $affected]);
    }

    // The messages this person may see: super admin = all;
    // dealer = enquiries about their cars, plus messages assigned directly to them.
    private function scopeFor(User $user): Builder
    {
        $query = Message::query();

        if ($user->isSuper()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereIn('car_id', Car::select('id')->where('user_id', $user->id));
        });
    }

    // The ONE access rule for a single message (one small query, not "load every car ID").
    private function canAccess(Request $request, Message $message): bool
    {
        $user = $request->user();

        if ($user->isSuper() || $message->user_id === $user->id) {
            return true;
        }

        return $message->car_id
            && Car::whereKey($message->car_id)->where('user_id', $user->id)->exists();
    }
}
