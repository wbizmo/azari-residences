<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingHold;
use App\Models\BookingStatusHistory;
use App\Models\IdentityVerification;
use App\Services\Identity\GuestVerificationInvitationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingCreationService
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly AzariPricingEngine $pricing,
        private readonly GuestVerificationInvitationService $guestInvitations,
    ) {}

    public function create(Request $request): Booking
    {
        $user = $request->user();
        $holdToken = (string) $request->input('hold_token');

        if (! $user || ! IdentityVerification::userIsVerified((int) $user->id)) {
            throw ValidationException::withMessages([
                'identity' => 'Complete Dojah identity verification before creating a booking.',
            ]);
        }

        $existingBooking = Booking::query()
            ->where('hold_token', $holdToken)
            ->first();

        if ($existingBooking) {
            abort_unless((int) $existingBooking->user_id === (int) $user->id, 403);

            return $existingBooking;
        }

        $hold = BookingHold::query()
            ->with(['property', 'accommodationType', 'ratePlan.cancellationPolicy', 'ratePlan.paymentPolicy'])
            ->active()
            ->where('token', $holdToken)
            ->first();

        if (! $hold) {
            $existingBooking = Booking::query()
                ->where('hold_token', $holdToken)
                ->first();

            if ($existingBooking) {
                abort_unless((int) $existingBooking->user_id === (int) $user->id, 403);

                return $existingBooking;
            }

            throw ValidationException::withMessages([
                'hold_token' => 'Your reservation hold expired. Please search again.',
            ]);
        }

        if ($hold->user_id && (int) $hold->user_id !== (int) $user->id) {
            abort(403);
        }

        $data = $this->validateDraft($request, $hold);

        $booking = DB::transaction(function () use ($request, $user, $hold, $data): Booking {
            $lockedHold = BookingHold::query()
                ->with(['property', 'accommodationType', 'ratePlan.cancellationPolicy', 'ratePlan.paymentPolicy'])
                ->active()
                ->whereKey($hold->id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->first();

            if (! $lockedHold) {
                $existing = Booking::query()
                    ->where('hold_token', $hold->token)
                    ->first();

                if ($existing) {
                    abort_unless((int) $existing->user_id === (int) $user->id, 403);

                    return $existing;
                }

                throw ValidationException::withMessages([
                    'hold_token' => 'Your reservation hold expired. Please search again.',
                ]);
            }

            if ($lockedHold->user_id && (int) $lockedHold->user_id !== (int) $user->id) {
                abort(403);
            }

            $property = $lockedHold->property()
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            $accommodationType = $lockedHold->accommodationType;

            if ($accommodationType) {
                $accommodationType = $accommodationType->newQuery()
                    ->whereKey($accommodationType->getKey())
                    ->when(
                        DB::connection()->getDriverName() !== 'sqlite',
                        fn (Builder $query) => $query->lockForUpdate()
                    )
                    ->firstOrFail();

                $this->availability->lockInventoryRange(
                    $accommodationType,
                    $lockedHold->check_in,
                    $lockedHold->check_out
                );
            }

            if (! $this->availability->availableForProperty(
                $property,
                $lockedHold->check_in,
                $lockedHold->check_out,
                max(1, (int) $lockedHold->rooms),
                $accommodationType?->getKey(),
                null,
                $lockedHold->token
            )) {
                throw ValidationException::withMessages([
                    'hold_token' => 'This accommodation is no longer available in the requested quantity.',
                ]);
            }

            $ratePlan = $lockedHold->ratePlan;

            $quote = $this->pricing->quote(
                $property,
                $lockedHold->check_in,
                $lockedHold->check_out,
                [],
                $accommodationType,
                $ratePlan,
                max(1, (int) $lockedHold->rooms)
            );

            $booking = Booking::query()->create([
                'reference' => $this->reference(),
                'user_id' => $user->id,
                'property_id' => $lockedHold->property_id,
                'accommodation_type_id' => $accommodationType?->getKey(),
                'rate_plan_id' => $ratePlan?->getKey(),
                'hold_token' => $lockedHold->token,
                'guest_name' => $data['first_name'].' '.$data['last_name'],
                'guest_first_name' => $data['first_name'],
                'guest_last_name' => $data['last_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'],
                'nationality' => $data['nationality'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'arrival_time' => $data['arrival_time'] ?? null,
                'guest_notes' => $data['guest_notes'] ?? null,
                'check_in' => $lockedHold->check_in,
                'check_out' => $lockedHold->check_out,
                'adults' => $lockedHold->adults,
                'children' => $lockedHold->children,
                'rooms' => $lockedHold->rooms,
                'status' => 'pending_payment',
                'verification_status' => 'unverified',
                'currency' => $quote['currency'],
                'nightly_rate' => $quote['nightly_rate'],
                'nights' => $quote['nights'],
                'subtotal' => $quote['subtotal'],
                'fee_total' => $quote['fee_total'],
                'add_on_total' => 0,
                'tax_rate' => $quote['tax_rate'],
                'tax_total' => $quote['tax_total'],
                'total' => $quote['total'],
                'pricing_snapshot' => $quote,
                'policy_snapshot' => $quote['policy'] ?? [],
                'property_name_snapshot' => $property->name,
                'accommodation_type_name_snapshot' => $accommodationType?->name,
                'rate_plan_name_snapshot' => $ratePlan?->name,
                'property_formatted_address' => $property->formatted_address ?: $property->location,
                'property_latitude' => $property->latitude,
                'property_longitude' => $property->longitude,
                'expires_at' => now()->addMinutes(
                    (int) config('azari.booking.unpaid_booking_minutes', 60)
                ),
            ]);

            $verifiedUserIdentity = IdentityVerification::query()
                ->where('provider', IdentityVerification::PROVIDER_DOJAH)
                ->where('user_id', $user->id)
                ->whereNull('booking_guest_id')
                ->latest('id')
                ->first();

            if (! $verifiedUserIdentity?->isVerified()) {
                throw ValidationException::withMessages([
                    'identity' => 'Your Dojah verification is no longer valid. Verify again before continuing.',
                ]);
            }

            foreach ($data['adults'] as $index => $adult) {
                $guest = BookingGuest::query()->create([
                    'booking_id' => $booking->id,
                    'user_id' => $index === 0 ? $user->id : null,
                    'type' => 'adult',
                    'position' => $index + 1,
                    'first_name' => $adult['first_name'],
                    'last_name' => $adult['last_name'],
                    'email' => $index === 0
                        ? $data['guest_email']
                        : mb_strtolower((string) $adult['email']),
                    'is_lead' => $index === 0,
                ]);

                if ($index === 0) {
                    IdentityVerification::query()->create([
                        'user_id' => $user->id,
                        'booking_guest_id' => $guest->id,
                        'provider' => IdentityVerification::PROVIDER_DOJAH,
                        'reference' => (string) Str::uuid(),
                        'widget_id' => $verifiedUserIdentity->widget_id,
                        'status' => IdentityVerification::STATUS_VERIFIED,
                        'provider_status' => $verifiedUserIdentity->provider_status,
                        'verification_type' => $verifiedUserIdentity->verification_type,
                        'verification_mode' => $verifiedUserIdentity->verification_mode,
                        'verified_at' => $verifiedUserIdentity->verified_at ?: now(),
                        'metadata' => [
                            'source_user_verification_id' => $verifiedUserIdentity->id,
                        ],
                    ]);
                }
            }

            foreach (($data['children'] ?? []) as $index => $child) {
                BookingGuest::query()->create([
                    'booking_id' => $booking->id,
                    'type' => 'child',
                    'position' => $index + 1,
                    'first_name' => $child['first_name'],
                    'last_name' => $child['last_name'],
                    'is_lead' => false,
                ]);
            }

            BookingStatusHistory::query()->create([
                'booking_id' => $booking->id,
                'changed_by' => $user->id,
                'from_status' => null,
                'to_status' => 'pending_payment',
                'note' => 'Booking created and awaiting payment.',
                'metadata' => ['channel' => 'registered'],
            ]);

            $lockedHold->delete();

            return $booking->refresh();
        }, 5);

        $this->guestInvitations->sendForBooking(
            $booking,
            $request->getSchemeAndHttpHost()
        );

        return $booking;
    }

    public function validateDraft(Request $request, BookingHold $hold): array
    {
        $data = $request->validate($this->rules($hold));

        $leadEmail = mb_strtolower((string) $data['guest_email']);
        $seen = [];

        foreach (($data['adults'] ?? []) as $index => $adult) {
            if ($index === 0) {
                continue;
            }

            $email = mb_strtolower((string) ($adult['email'] ?? ''));

            if ($email === $leadEmail) {
                throw ValidationException::withMessages([
                    "adults.$index.email" => 'An additional adult must use their own email address.',
                ]);
            }

            if (isset($seen[$email])) {
                throw ValidationException::withMessages([
                    "adults.$index.email" => 'Each additional adult must use a different email address.',
                ]);
            }

            $seen[$email] = true;
            $data['adults'][$index]['email'] = $email;
        }

        $data['guest_email'] = $leadEmail;

        return $data;
    }

    private function rules(BookingHold $hold): array
    {
        $rules = [
            'hold_token' => ['nullable', 'uuid'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'guest_email' => ['required', 'email:rfc', 'max:190'],
            'guest_phone' => ['required', 'string', 'max:40'],
            'nationality' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'guest_notes' => ['nullable', 'string', 'max:3000'],
            'adults' => ['required', 'array', 'size:'.$hold->adults],
            'children' => ['nullable', 'array', 'size:'.$hold->children],
            'terms' => ['accepted'],
        ];

        for ($index = 0; $index < $hold->adults; $index++) {
            $rules["adults.$index.first_name"] = ['required', 'string', 'max:80'];
            $rules["adults.$index.last_name"] = ['required', 'string', 'max:80'];

            if ($index > 0) {
                $rules["adults.$index.email"] = [
                    'required',
                    'email:rfc',
                    'max:190',
                    'distinct',
                ];
            }
        }

        for ($index = 0; $index < $hold->children; $index++) {
            $rules["children.$index.first_name"] = ['required', 'string', 'max:80'];
            $rules["children.$index.last_name"] = ['required', 'string', 'max:80'];
        }

        return $rules;
    }

    private function reference(): string
    {
        do {
            $reference = 'RSV-'.now()->format('ymd').'-'.Str::upper(Str::random(7));
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
