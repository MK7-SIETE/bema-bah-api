<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    // Public: subscribe
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:200'],
            'name'  => ['nullable', 'string', 'max:100'],
        ]);

        $existing = Subscriber::where('email', $data['email'])->first();

        if ($existing) {
            if ($existing->is_active) {
                return response()->json(['message' => 'You are already subscribed.'], 200);
            }
            // Re-subscribe
            $existing->update(['is_active' => true, 'name' => $data['name'] ?? $existing->name]);
            return response()->json(['message' => 'Welcome back! You have been re-subscribed.'], 200);
        }

        Subscriber::create([
            'email' => $data['email'],
            'name'  => $data['name'] ?? null,
            'confirmed_at' => now(),
        ]);

        return response()->json(['message' => 'Successfully subscribed! Thank you.'], 201);
    }

    // Public: unsubscribe via token link
    public function unsubscribe(string $token)
    {
        $subscriber = Subscriber::where('token', $token)->first();

        if (!$subscriber) {
            return response()->json(['message' => 'Invalid unsubscribe link.'], 404);
        }

        $subscriber->update(['is_active' => false]);

        return response()->json(['message' => 'You have been unsubscribed successfully.']);
    }

    // Super admin: list all subscribers
    public function index()
    {
        $subscribers = Subscriber::orderBy('created_at', 'desc')->get();
        $total  = $subscribers->count();
        $active = $subscribers->where('is_active', true)->count();

        return response()->json([
            'subscribers' => $subscribers,
            'total'       => $total,
            'active'      => $active,
        ]);
    }

    // Super admin: delete subscriber
    public function destroy(int $id)
    {
        $subscriber = Subscriber::findOrFail($id);
        $subscriber->delete();
        return response()->json(['message' => 'Subscriber removed.']);
    }
}
