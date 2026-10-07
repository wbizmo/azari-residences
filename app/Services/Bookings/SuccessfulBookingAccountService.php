<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingAccountActivationNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SuccessfulBookingAccountService
{
    /**
     * @return array{
     *     user: User,
     *     booking: Booking,
     *     created: bool
     * }
     */
    public function provision(Booking $booking): array
    {
        if ($booking->user_id) {
            $user = $booking->user()->firstOrFail();

            return [
                'user' => $user,
                'booking' => $booking->refresh(),
                'created' => false,
            ];
        }

        $email = Str::lower(
            trim(
                (string) $booking->guest_email
            )
        );

        if ($email === '') {
            throw new \RuntimeException(
                'A successful guest booking cannot be provisioned without an email address.'
            );
        }

        $user = User::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [
                    $email,
                ]
            )
            ->first();

        $created = false;

        if (! $user) {
            $name = trim(
                (string) (
                    $booking->guest_name
                    ?: trim(
                        ($booking->guest_first_name ?? '')
                        .' '
                        .($booking->guest_last_name ?? '')
                    )
                )
            );

            if ($name === '') {
                $name = 'Azari Guest';
            }

            try {
                $user = new User;
                $user->fill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'phone' => $booking->guest_phone,
                    'timezone' => config('azari.timezone', 'Africa/Lagos'),
                ]);
                $user->forceFill([
                    'account_type' => 'customer',
                    'status' => 'pending_verification',
                    'is_active' => true,
                    'email_verified_at' => null,
                ])->save();

                $created = true;
            } catch (QueryException $exception) {
                /*
                 * Handle a concurrent successful booking using the same
                 * email without ever creating duplicate accounts.
                 */
                $user = User::query()
                    ->whereRaw(
                        'LOWER(email) = ?',
                        [
                            $email,
                        ]
                    )
                    ->first();

                if (! $user) {
                    throw $exception;
                }
            }
        }

        /*
         * Link the current booking AND previous guest bookings using the
         * same email so the customer's portal immediately contains their
         * booking history.
         */
        Booking::query()
            ->whereNull('user_id')
            ->whereNotNull(
                'guest_email'
            )
            ->whereRaw(
                'LOWER(guest_email) = ?',
                [
                    $email,
                ]
            )
            ->update([
                'user_id' =>
                    $user->getKey(),
            ]);

        return [
            'user' => $user->refresh(),
            'booking' => $booking->refresh(),
            'created' => $created,
        ];
    }

    public function sendActivation(
        User $user,
        Booking $booking
    ): void {
        $token = Password::broker()
            ->createToken(
                $user
            );

        $activationUrl = route(
            'password.reset',
            [
                'token' =>
                    $token,
                'email' =>
                    $user->email,
            ]
        );

        $user->notify(
            new BookingAccountActivationNotification(
                $activationUrl,
                $booking->reference
            )
        );
    }
}
