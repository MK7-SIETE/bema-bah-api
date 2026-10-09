<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterMail;
use App\Models\Car;
use App\Models\Newsletter;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    // List all newsletters
    public function index()
    {
        $newsletters = Newsletter::with('car')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($newsletters);
    }

    // Create / save draft
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'type'    => ['required', 'in:custom,new_car,promotion'],
            'body'    => ['nullable', 'string'],
            'car_id'  => ['nullable', 'exists:cars,id'],
        ]);

        $newsletter = Newsletter::create($data);
        $newsletter->load('car');

        return response()->json($newsletter, 201);
    }

    // Update draft
    public function update(Request $request, int $id)
    {
        $newsletter = Newsletter::findOrFail($id);

        if ($newsletter->status === 'sent') {
            return response()->json(['message' => 'Cannot edit a sent newsletter.'], 403);
        }

        $data = $request->validate([
            'subject' => ['sometimes', 'string', 'max:200'],
            'type'    => ['sometimes', 'in:custom,new_car,promotion'],
            'body'    => ['nullable', 'string'],
            'car_id'  => ['nullable', 'exists:cars,id'],
        ]);

        $newsletter->update($data);
        $newsletter->load('car');

        return response()->json($newsletter);
    }

    // Delete draft
    public function destroy(int $id)
    {
        $newsletter = Newsletter::findOrFail($id);

        if ($newsletter->status === 'sent') {
            return response()->json(['message' => 'Cannot delete a sent newsletter.'], 403);
        }

        $newsletter->delete();
        return response()->json(['message' => 'Draft deleted.']);
    }

    // Send newsletter to all active subscribers
    public function send(Request $request, int $id)
    {
        $newsletter = Newsletter::with('car')->findOrFail($id);

        if ($newsletter->status === 'sent') {
            return response()->json(['message' => 'Already sent.'], 400);
        }

        $subscribers = Subscriber::where('is_active', true)->get();

        if ($subscribers->isEmpty()) {
            return response()->json(['message' => 'No active subscribers to send to.'], 400);
        }

        // Check mail is configured
        $mailDriver = config('mail.default');
        if ($mailDriver === 'log') {
            // Still allow — just logs to laravel.log (useful for testing)
        }

        $sent = 0;
        $failed = 0;

        foreach ($subscribers as $subscriber) {
            try {
                Mail::to($subscriber->email)->send(new NewsletterMail($newsletter, $subscriber));
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                \Log::error("Newsletter send failed for {$subscriber->email}: " . $e->getMessage());
            }
        }

        $newsletter->update([
            'status'           => 'sent',
            'sent_at'          => now(),
            'recipients_count' => $sent,
        ]);

        return response()->json([
            'message' => "Sent to {$sent} subscribers." . ($failed > 0 ? " {$failed} failed — check logs." : ''),
            'sent'    => $sent,
            'failed'  => $failed,
        ]);
    }

    // Preview a newsletter (returns HTML)
    public function preview(int $id)
    {
        $newsletter = Newsletter::with('car')->findOrFail($id);
        // Return data for frontend to render preview
        return response()->json($newsletter);
    }
}
