<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Message;
use Illuminate\Http\Request;

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

    // ── DEALER: list messages ──────────────────────────────────────
    // Shows: (a) enquiries about this dealer's cars, (b) messages assigned directly to them
    public function index(Request $request)
    {
        $userId       = $request->user()->id;
        $dealerCarIds = Car::where('user_id', $userId)->pluck('id');

        $messages = Message::with('car')
            ->where(function ($q) use ($dealerCarIds, $userId) {
                $q->whereIn('car_id', $dealerCarIds)   // car enquiries for their cars
                  ->orWhere('user_id', $userId);        // messages assigned directly
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($messages);
    }

    // ── DEALER: single message ─────────────────────────────────────
    public function show(Request $request, Message $message)
    {
        $userId       = $request->user()->id;
        $dealerCarIds = Car::where('user_id', $userId)->pluck('id');

        $allowed = $dealerCarIds->contains($message->car_id)
                || $message->user_id === $userId;

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($message->load('car'));
    }

    // ── DEALER: mark read ──────────────────────────────────────────
    public function markRead(Request $request, Message $message)
    {
        $userId       = $request->user()->id;
        $dealerCarIds = Car::where('user_id', $userId)->pluck('id');

        $allowed = $dealerCarIds->contains($message->car_id)
                || $message->user_id === $userId;

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $message->update(['is_read' => true]);
        return response()->json($message);
    }

    // ── DEALER: delete ─────────────────────────────────────────────
    public function destroy(Request $request, Message $message)
    {
        $userId       = $request->user()->id;
        $dealerCarIds = Car::where('user_id', $userId)->pluck('id');

        $allowed = $dealerCarIds->contains($message->car_id)
                || $message->user_id === $userId;

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $message->delete();
        return response()->json(['message' => 'Message deleted.']);
    }

    // ── SUPER: all messages across all dealers ─────────────────────
    public function superIndex()
    {
        return response()->json(
            Message::with(['car.user:id,name,email'])
                   ->orderBy('created_at', 'desc')
                   ->get()
        );
    }

    // ── SUPER: mark message read ───────────────────────────────────
public function superMarkRead(Message $message)
{
    $message->update(['is_read' => true]);
    return response()->json($message);
}
}
