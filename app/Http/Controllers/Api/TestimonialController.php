<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    // PUBLIC — approved only
    public function index()
    {
        return response()->json(
            Testimonial::where('status', 'approved')->latest()->get()
        );
    }

    // PUBLIC — submit new review
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'car_bought' => ['nullable', 'string', 'max:100'],
            'message'    => ['required', 'string', 'max:1000'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        Testimonial::create($data); // status defaults to pending
        return response()->json(['message' => 'Thank you! Your review is pending approval.'], 201);
    }

    // Super — all testimonials
    public function superIndex()
    {
        return response()->json(Testimonial::latest()->get());
    }

    // Super — approve or reject
    public function updateStatus(Request $request, Testimonial $testimonial)
    {
        $request->validate(['status' => ['required', 'in:approved,rejected']]);
        $testimonial->update(['status' => $request->status]);
        return response()->json($testimonial);
    }

    // Super — delete
    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}