<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    // One message row is shared, but each side keeps its OWN state on it:
    //   dealer_is_read / super_is_read          - has this side opened it?
    //   dealer_deleted_at / super_deleted_at    - has this side deleted it? (the other side still sees it)
    // The row is removed for good only when both sides have deleted it,
    // or when it has no dealer at all (general / import messages: only the super admin ever sees them).
    // The JSON we send back still says "is_read" - it is simply THIS person's own flag,
    // so the frontend does not need to change.
    private const PARTY_COLUMNS = ['dealer_is_read', 'super_is_read', 'dealer_deleted_at', 'super_deleted_at'];

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

        // dealer_is_read and super_is_read start as false (the column default), nothing is deleted yet.
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
        $party = $this->party($user);
        $query = $this->scopeFor($user);

        $type = $request->input('type');
        if (in_array($type, ['general', 'car_enquiry', 'import_request'], true)) {
            $query->where('type', $type);
        }

        $read = $request->input('read');
        if ($read === 'unread') {
            $query->where($party . '_is_read', false);
        } elseif ($read === 'read') {
            $query->where($party . '_is_read', true);
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
            return response()->json(
                $query->limit(100)->get()->map(fn (Message $m) => $this->forParty($m, $party))
            );
        }

        return response()->json(
            $query->paginate(20)
                  ->through(fn (Message $m) => $this->forParty($m, $party))
                  ->withQueryString()
        );
    }

    // ── DEALER or SUPER: how many unread (one cheap query, for the badge) ──
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'unread' => $this->scopeFor($user)->where($this->party($user) . '_is_read', false)->count(),
        ]);
    }

    // ── DEALER or SUPER: single message ────────────────────────────
    public function show(Request $request, Message $message)
    {
        if (! $this->canAccess($request, $message)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $message->load('car:id,user_id,make,model,year');

        return response()->json($this->forParty($message, $this->party($request->user())));
    }

    // ── DEALER or SUPER: mark read (only for the person asking) ────
    public function markRead(Request $request, Message $message)
    {
        if (! $this->canAccess($request, $message)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $party = $this->party($request->user());
        $message->forceFill([$party . '_is_read' => true])->save();

        return response()->json($this->forParty($message, $party));
    }

    // ── DEALER or SUPER: delete (only for the person asking) ───────
    public function destroy(Request $request, Message $message)
    {
        if (! $this->canAccess($request, $message)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user = $request->user();
        $this->removeFor($this->scopeFor($user)->whereKey($message->id), $this->party($user));

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

        $user  = $request->user();
        $party = $this->party($user);
        $query = $this->scopeFor($user)->whereIn('id', $data['ids']);

        $affected = match ($data['action']) {
            'read'   => $query->update([$party . '_is_read' => true]),
            'unread' => $query->update([$party . '_is_read' => false]),
            'delete' => $this->removeFor($query, $party),
        };

        return response()->json(['affected' => $affected]);
    }

    // Which side is asking: the super admin, or a dealer.
    private function party(User $user): string
    {
        return $user->isSuper() ? 'super' : 'dealer';
    }

    // The messages this person may see: super admin = all;
    // dealer = enquiries about their cars, plus messages assigned directly to them.
    // Anything THIS person has already deleted is left out.
    private function scopeFor(User $user): Builder
    {
        $query = Message::query()->whereNull($this->party($user) . '_deleted_at');

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

        // Already deleted by this person: it is gone for them.
        if ($message->{$this->party($user) . '_deleted_at'} !== null) {
            return false;
        }

        if ($user->isSuper() || $message->user_id === $user->id) {
            return true;
        }

        return $message->car_id
            && Car::whereKey($message->car_id)->where('user_id', $user->id)->exists();
    }

    // Delete for ONE side. The row disappears for good only when nobody can see it any more:
    // both sides deleted it, or it has no dealer (no user and no car), so only the super admin had it.
    // $visible is already limited to messages this person may see.
    private function removeFor(Builder $visible, string $party): int
    {
        $ids = $visible->pluck('id')->all();

        if (empty($ids)) {
            return 0;
        }

        DB::transaction(function () use ($ids, $party) {
            Message::whereIn('id', $ids)->update([$party . '_deleted_at' => now()]);

            Message::whereIn('id', $ids)
                ->where(function ($q) {
                    $q->where(function ($both) {
                        $both->whereNotNull('dealer_deleted_at')->whereNotNull('super_deleted_at');
                    })->orWhere(function ($noDealer) {
                        $noDealer->whereNull('user_id')->whereNull('car_id');
                    });
                })
                ->delete();
        });

        return count($ids);
    }

    // Make the JSON show THIS person's own read flag as "is_read", and hide the four per-side columns.
    private function forParty(Message $message, string $party): Message
    {
        $message->setAttribute('is_read', (bool) $message->{$party . '_is_read'});

        return $message->makeHidden(self::PARTY_COLUMNS);
    }
}
