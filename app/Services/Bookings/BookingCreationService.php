<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingHold;
use App\Models\BookingStatusHistory;
use App\Support\LocalDate;
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
    ) {}

    public function create(Request $request): Booking
    {
        $user = $request->user();
        $holdToken = (string) $request->input('hold_token');

        if (! $user) {
            abort(403);
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
                'property_timezone' => LocalDate::propertyTimezone($property),
                'booking_locale' => $user->locale ?: app()->getLocale(),
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

            foreach ($data['adults'] as $index => $adult) {
                $guest = BookingGuest::query()->create([
                    'booking_id' => $booking->id,
                    'user_id' => $index === 0 ? $user->id : null,
                    'type' => 'adult',
                    'position' => $index + 1,
                    'first_name' => $adult['first_name'],
                    'last_name' => $adult['last_name'],
                    'email' => $index === 0 ? $data['guest_email'] : null,
                    'is_lead' => $index === 0,
                ]);

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


        return $booking;
    }

    public function validateDraft(Request $request, BookingHold $hold): array
    {
        $data = $request->validate($this->rules($hold));

        $data['guest_email'] = mb_strtolower((string) $data['guest_email']);

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
            // Preserve additional adult email through validated checkout drafts.
            $rules["adults.$index.email"] = ['nullable', 'email:rfc', 'max:190'];
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
