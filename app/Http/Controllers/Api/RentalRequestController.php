<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\RentalRequest;
use App\Models\User;
use App\Support\RentalRequestAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class RentalRequestController extends Controller
{
    // ── PUBLIC: customer submits a rental request ──────
    public function store(Request $request)
    {
        $data = $request->validate([
            'car_id'           => ['required', 'integer', 'exists:cars,id'],
            'customer_name'    => ['required', 'string', 'max:150'],
            'customer_phone'   => ['required', 'string', 'max:30', 'regex:/^\+?\(?[0-9][0-9\s\-()]{5,28}$/'],
            'customer_email'   => ['nullable', 'email', 'max:150'],
            'pickup_date'      => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'days'             => ['required', 'integer', 'min:1', 'max:60'],
            'delivery_type'    => ['required', Rule::in(RentalRequest::DELIVERY_TYPES)],
            'delivery_address' => ['required_if:delivery_type,delivery', 'nullable', 'string', 'max:200'],
            'with_driver'      => ['required', 'boolean'],
            'trip_type'        => ['required', Rule::in(RentalRequest::TRIP_TYPES)],
            'destination'      => ['nullable', 'string', 'max:150'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        // Same public-visibility rule as the website: switched on AND owner account active.
        $car = Car::visible()
            ->where('id', $data['car_id'])
            ->where('listing_type', 'rental')
            ->first();

        if (! $car) {
            return response()->json(['message' => 'This car is not available for rent.'], 422);
        }

        // The customer gives a start date and a number of days; the return date is worked out here.
        // Example: 3 days from 5 Nov means the car is back on 8 Nov.
        $data['return_date'] = Carbon::createFromFormat('Y-m-d', $data['pickup_date'])
            ->addDays((int) $data['days'])
            ->toDateString();

        // Keep only what applies: no address for a pick-up, no destination for a trip inside the city.
        if ($data['delivery_type'] !== 'delivery') $data['delivery_address'] = null;
        if ($data['trip_type'] !== 'out_of_city')  $data['destination'] = null;

        $rentalRequest = RentalRequest::create($data + [
            'rental_admin_id' => $car->user_id,
            'car_label'       => trim($car->vehicle_code . ' ' . $car->make . ' ' . $car->model . ' ' . $car->year),
            'status'          => 'new',
        ]);

        // Alert the site owner AFTER the answer has been prepared. The request is already saved,
        // and a failing alert can never undo it (see RentalRequestAlert).
        app()->terminating(fn () => RentalRequestAlert::send($rentalRequest));

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

    // ── SUPER ADMIN: how many requests are waiting for you (the badge) ──
    public function newCount()
    {
        return response()->json(['count' => RentalRequest::where('status', 'new')->count()]);
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
