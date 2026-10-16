<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\RentalRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RentalRequestController extends Controller
{
    // ── PUBLIC: customer submits a rental request ──────
    public function store(Request $request)
    {
        $data = $request->validate([
            'car_id'          => ['required', 'integer', 'exists:cars,id'],
            'customer_name'   => ['required', 'string', 'max:150'],
            'customer_phone'  => ['required', 'string', 'max:30'],
            'customer_email'  => ['nullable', 'email', 'max:150'],
            'pickup_date'     => ['required', 'date', 'after_or_equal:today'],
            'return_date'     => ['required', 'date', 'after_or_equal:pickup_date'],
            'pickup_location' => ['nullable', 'string', 'max:150'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ]);

        // Same public-visibility rule as the website: switched on AND owner account active.
        $car = Car::visible()
            ->where('id', $data['car_id'])
            ->where('listing_type', 'rental')
            ->first();

        if (! $car) {
            return response()->json(['message' => 'This car is not available for rent.'], 422);
        }

        $rentalRequest = RentalRequest::create($data + [
            'rental_admin_id' => $car->user_id,
            'car_label'       => trim($car->vehicle_code . ' ' . $car->make . ' ' . $car->model . ' ' . $car->year),
            'status'          => 'new',
        ]);

        return response()->json([
            'message' => 'Request received. We will contact you shortly.',
            'id'      => $rentalRequest->id,
        ], 201);
    }

    // ── SUPER ADMIN: list requests (paged, newest first, optional ?status=) ──
    public function superIndex(Request $request)
    {
        $query = RentalRequest::with([
            'car:id,make,model,year,location,rental_price_per_day',
            'rentalAdmin:id,name,email,phone',
        ])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20)->withQueryString());
    }

    // ── SUPER ADMIN: forward a request to the rental admin ──
    public function forward(RentalRequest $rentalRequest)
    {
        if (! in_array($rentalRequest->status, ['new', 'forwarded'], true)) {
            return response()->json(['message' => 'This request has already been handled.'], 422);
        }

        $admin = $rentalRequest->rentalAdmin;
        if (! $admin) {
            return response()->json(['message' => 'No rental admin is linked to this request.'], 422);
        }

        $text = $rentalRequest->messageText();

        $rentalRequest->update([
            'status'       => 'forwarded',
            'forwarded_at' => now(),
        ]);

        $number = $admin->whatsappNumber();

        return response()->json([
            'message'         => 'Request forwarded.',
            'request'         => $rentalRequest->fresh(),
            'email_status'    => $this->sendEmail($admin, $rentalRequest, $text),
            'whatsapp_text'   => $text,
            'whatsapp_url'    => $number ? 'https://wa.me/' . $number . '?text=' . rawurlencode($text) : null,
        ]);
    }

    // ── SUPER ADMIN: change status ─────────────────────
    public function superStatus(Request $request, RentalRequest $rentalRequest)
    {
        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', RentalRequest::STATUSES)],
        ]);

        $rentalRequest->update($data);
        return response()->json($rentalRequest);
    }

    // ── RENTAL ADMIN: requests forwarded to me (paged) ──
    public function rentalIndex(Request $request)
    {
        return response()->json(
            RentalRequest::with('car:id,make,model,year,location,rental_price_per_day')
                ->where('rental_admin_id', $request->user()->id)
                ->whereIn('status', ['forwarded', 'confirmed', 'declined', 'completed'])
                ->latest()
                ->paginate(20)
                ->withQueryString()
        );
    }

    // ── RENTAL ADMIN: update my request's status ───────
    public function rentalStatus(Request $request, RentalRequest $rentalRequest)
    {
        if ($rentalRequest->rental_admin_id !== $request->user()->id || $rentalRequest->status === 'new') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:confirmed,declined,completed'],
        ]);

        $rentalRequest->update($data);
        return response()->json($rentalRequest);
    }

    private function sendEmail(User $admin, RentalRequest $rentalRequest, string $text): string
    {
        try {
            Mail::raw($text, function ($message) use ($admin, $rentalRequest) {
                $message->to($admin->email)
                        ->subject('New rental request: ' . $rentalRequest->car_label);
            });

            // While MAIL_MAILER=log, nothing is really sent; it is only written to the log file.
            return config('mail.default') === 'log' ? 'logged_only' : 'sent';
        } catch (\Throwable $e) {
            report($e);
            return 'failed';
        }
    }
}
