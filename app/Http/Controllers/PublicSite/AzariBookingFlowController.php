<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingHold;
use App\Models\BookingStatusHistory;
use App\Models\GuestIdentityDocument;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AzariBookingFlowController extends Controller
{
    public function checkout(string $token, AzariPricingEngine $pricing)
    {
        $hold = BookingHold::query()->with('property')->active()->where('token', $token)->firstOrFail();

        return view('public.bookings.checkout', ['hold' => $hold, 'quote' => $pricing->quote($hold->property, $hold->check_in, $hold->check_out)]);
    }

    public function store(Request $request, AzariAvailabilityEngine $availability, AzariPricingEngine $pricing)
    {
        $hold = BookingHold::query()->with('property')->active()->where('token', $request->input('hold_token'))->first();
        if (! $hold) {
            throw ValidationException::withMessages(['hold_token' => 'Your reservation hold expired. Please search again.']);
        }

        $rules = [
            'hold_token' => ['required', 'uuid'], 'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'guest_email' => ['required', 'email:rfc', 'max:190'], 'guest_phone' => ['required', 'string', 'max:40'],
            'nationality' => ['required', 'string', 'max:100'], 'address' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'], 'arrival_time' => ['nullable', 'date_format:H:i'], 'guest_notes' => ['nullable', 'string', 'max:3000'],
            'adults' => ['required', 'array', 'size:'.$hold->adults], 'children' => ['nullable', 'array', 'size:'.$hold->children], 'terms' => ['accepted'],
        ];
        foreach (range(0, max(0, $hold->adults - 1)) as $i) {
            $rules["adults.$i.first_name"] = ['required', 'string', 'max:80'];
            $rules["adults.$i.last_name"] = ['required', 'string', 'max:80'];
            $rules["adults.$i.document_type"] = ['required', Rule::in(['passport', 'national_id', 'drivers_licence'])];
            $rules["adults.$i.document"] = ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];
        }
        foreach (range(0, max(0, $hold->children - 1)) as $i) {
            $rules["children.$i.first_name"] = ['required', 'string', 'max:80'];
            $rules["children.$i.last_name"] = ['required', 'string', 'max:80'];
        }
        $data = $request->validate($rules);

        if (! $availability->available($hold->property_id, $hold->check_in, $hold->check_out, null, $hold->token)) {
            throw ValidationException::withMessages(['hold_token' => 'This residence is no longer available.']);
        }
        $quote = $pricing->quote($hold->property, $hold->check_in, $hold->check_out);

        $booking = DB::transaction(function () use ($request, $data, $hold, $quote): Booking {
            do {
                $reference = 'AZR-'.now()->format('ymd').'-'.Str::upper(Str::random(7));
            } while (Booking::where('reference', $reference)->exists());
            $booking = Booking::create([
                'reference' => $reference, 'user_id' => $request->user()?->id, 'property_id' => $hold->property_id, 'hold_token' => $hold->token,
                'guest_name' => $data['first_name'].' '.$data['last_name'], 'guest_first_name' => $data['first_name'], 'guest_last_name' => $data['last_name'],
                'guest_email' => $data['guest_email'], 'guest_phone' => $data['guest_phone'], 'nationality' => $data['nationality'], 'address' => $data['address'],
                'city' => $data['city'], 'country' => $data['country'], 'arrival_time' => $data['arrival_time'] ?? null, 'guest_notes' => $data['guest_notes'] ?? null,
                'check_in' => $hold->check_in, 'check_out' => $hold->check_out, 'adults' => $hold->adults, 'children' => $hold->children, 'rooms' => $hold->rooms,
                'status' => 'pending_payment', 'verification_status' => 'unverified', 'currency' => $quote['currency'], 'nightly_rate' => $quote['nightly_rate'], 'nights' => $quote['nights'],
                'subtotal' => $quote['subtotal'], 'fee_total' => $quote['fee_total'], 'add_on_total' => 0, 'tax_rate' => $quote['tax_rate'], 'tax_total' => $quote['tax_total'],
                'total' => $quote['total'], 'pricing_snapshot' => $quote, 'expires_at' => now()->addHours(24),
            ]);

            foreach ($data['adults'] as $index => $adult) {
                $guest = BookingGuest::create(['booking_id' => $booking->id, 'type' => 'adult', 'position' => $index + 1, 'first_name' => $adult['first_name'], 'last_name' => $adult['last_name'], 'is_lead' => $index === 0]);
                $file = $request->file("adults.$index.document");
                $path = $file->store("booking-identities/{$booking->reference}", 'local');
                GuestIdentityDocument::create(['booking_guest_id' => $guest->id, 'document_type' => $adult['document_type'], 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())]);
            }
            foreach (($data['children'] ?? []) as $index => $child) {
                BookingGuest::create(['booking_id' => $booking->id, 'type' => 'child', 'position' => $index + 1, 'first_name' => $child['first_name'], 'last_name' => $child['last_name'], 'is_lead' => false]);
            }
            BookingStatusHistory::create(['booking_id' => $booking->id, 'changed_by' => $request->user()?->id, 'from_status' => null, 'to_status' => 'pending_payment', 'note' => 'Booking created and awaiting payment.', 'metadata' => ['channel' => $request->user() ? 'registered' : 'guest']]);
            $hold->delete();

            return $booking;
        }, 3);

        return redirect()->route('azari.booking.review', $booking->reference)->with('success', 'Guest details saved. Review the booking before payment.');
    }

    public function review(string $reference)
    {
        $booking = Booking::with([
            'property',
            'guests.identityDocument',
        ])->where('reference', $reference)->firstOrFail();

        return view('public.bookings.review', compact('booking'));
    }

    public function confirm(string $reference)
    {
        $booking = Booking::where('reference', $reference)->firstOrFail();

        if ($booking->status === 'pending_payment') {
            $booking->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => 'DEMO-'.Str::upper(Str::random(14)),
                'receipt_number' => 'RCT-'.now()->format('Ymd').'-'.str_pad(
                    (string) $booking->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
                'expires_at' => null,
            ]);

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'changed_by' => auth()->id(),
                'from_status' => 'pending_payment',
                'to_status' => 'paid',
                'note' => 'Payment confirmed automatically and receipt generated.',
            ]);
        }

        return redirect()
            ->route('azari.booking.summary', $booking->reference)
            ->with('success', 'Payment confirmed. Your booking is secured.');
    }

    public function summary(string $reference)
    {
        $booking = Booking::with([
            'property',
            'guests',
        ])->where('reference', $reference)->firstOrFail();

        return view('public.bookings.summary', compact('booking'));
    }
}
