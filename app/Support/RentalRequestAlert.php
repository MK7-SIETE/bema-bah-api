<?php

namespace App\Support;

use App\Models\RentalRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the site owner that a new rental request arrived (email and/or Telegram).
 *
 * Rules:
 *  - An alert must NEVER break the customer's request, so every channel is wrapped in try/catch.
 *  - The text carries NO customer name or phone: alerts pass through third parties (mail and Telegram servers).
 *  - Failures are logged WITHOUT the exception message: the Telegram web address contains the bot token,
 *    and exception messages can include that address.
 */
class RentalRequestAlert
{
    public static function text(RentalRequest $request): string
    {
        $car = $request->car_label ?: 'a rental car';
        $days = (int) $request->days;
        $when = $request->pickup_date ? $request->pickup_date->format('d M Y') : '';

        return 'New rental request #' . $request->id . ': ' . $car . '.'
            . ($days > 0 ? ' ' . $days . ' ' . ($days === 1 ? 'day' : 'days') . ($when ? ' from ' . $when : '') . '.' : '')
            . ' Open the Super admin to view and forward it.';
    }

    public static function send(RentalRequest $request): void
    {
        $text = self::text($request);

        $to = config('notify.email');
        if ($to) {
            try {
                Mail::raw($text, function ($message) use ($to, $request) {
                    $message->to($to)->subject('New rental request #' . $request->id . ': ' . ($request->car_label ?: 'rental car'));
                });
            } catch (\Throwable $e) {
                Log::warning('Rental email alert failed', ['error' => class_basename($e)]);
            }
        }

        $token = config('notify.telegram_token');
        $chat  = config('notify.telegram_chat_id');
        if ($token && $chat) {
            try {
                $response = Http::connectTimeout(2)->timeout(3)->asForm()->post(
                    'https://api.telegram.org/bot' . $token . '/sendMessage',
                    ['chat_id' => $chat, 'text' => $text]
                );
                if ($response->failed()) {
                    Log::warning('Rental Telegram alert failed', ['status' => $response->status()]);
                }
            } catch (\Throwable $e) {
                Log::warning('Rental Telegram alert failed', ['error' => class_basename($e)]);
            }
        }
    }
}
