<?php

namespace App\Services\Identity;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingGuestVerificationInvite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class GuestVerificationInvitationService
{
    public function sendForBooking(Booking $booking, string $origin): void
    {
        $booking->loadMissing('guests');

        foreach ($booking->guests as $guest) {
            if ($guest->type !== 'adult' || $guest->is_lead || blank($guest->email)) {
                continue;
            }

            try {
                $this->sendInvite($guest, $origin);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function sendInvite(BookingGuest $guest, string $origin): void
    {
        abort_unless($guest->type === 'adult' && ! $guest->is_lead && filled($guest->email), 422);

        $guest->loadMissing('booking.property');

        $invite = BookingGuestVerificationInvite::query()->firstOrNew([
            'booking_guest_id' => $guest->id,
        ]);

        $invite->fill([
            'email' => $guest->email,
            'invite_sent_at' => now(),
            'invite_count' => ((int) $invite->invite_count) + 1,
        ])->save();

        $verificationUrl = $this->publicUrl($guest, $origin);

        Mail::send('emails.guest-verification-invitation', [
            'guest' => $guest,
            'booking' => $guest->booking,
            'verificationUrl' => $verificationUrl,
        ], function ($message) use ($guest): void {
            $message
                ->to($guest->email)
                ->subject('Verify your identity for your Azari booking');
        });
    }

    public function sendCode(BookingGuest $guest): void
    {
        abort_unless($guest->type === 'adult' && ! $guest->is_lead && filled($guest->email), 422);

        $invite = BookingGuestVerificationInvite::query()->firstOrCreate(
            ['booking_guest_id' => $guest->id],
            ['email' => $guest->email]
        );

        if ($invite->locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Please wait before requesting another code.',
            ]);
        }

        if ($invite->code_sent_at?->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages([
                'code' => 'A verification code was sent recently. Please wait a moment before requesting another.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $invite->fill([
            'email' => $guest->email,
            'code_hash' => Hash::make($code),
            'code_expires_at' => now()->addMinutes(10),
            'code_sent_at' => now(),
            'code_attempts' => 0,
            'locked_until' => null,
        ])->save();

        try {
            Mail::send('emails.guest-verification-code', [
                'guest' => $guest,
                'booking' => $guest->booking,
                'code' => $code,
            ], function ($message) use ($guest): void {
                $message
                    ->to($guest->email)
                    ->subject('Your Azari guest verification code');
            });
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'code' => 'We could not send the verification code. Please try again.',
            ]);
        }
    }

    public function verifyCode(BookingGuest $guest, string $code): bool
    {
        $invite = BookingGuestVerificationInvite::query()
            ->where('booking_guest_id', $guest->id)
            ->first();

        if (! $invite) {
            return false;
        }

        if ($invite->locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Please wait before trying again.',
            ]);
        }

        if (
            blank($invite->code_hash)
            || ! $invite->code_expires_at
            || $invite->code_expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'code' => 'This verification code has expired. Request a new one.',
            ]);
        }

        if (! Hash::check($code, $invite->code_hash)) {
            $attempts = ((int) $invite->code_attempts) + 1;

            $invite->fill([
                'code_attempts' => $attempts,
                'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null,
            ])->save();

            return false;
        }

        $invite->fill([
            'email_verified_at' => now(),
            'code_hash' => null,
            'code_expires_at' => null,
            'code_attempts' => 0,
            'locked_until' => null,
        ])->save();

        return true;
    }

    public function publicUrl(BookingGuest $guest, string $origin): string
    {
        $guest->loadMissing('booking');

        return rtrim($origin, '/')
            .'/guest-verification/'
            .rawurlencode($guest->booking->reference)
            .'/'
            .$guest->position;
    }
}
