<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingHold;
use App\Models\BookingStatusHistory;
use App\Services\Identity\IdentityDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingCreationService
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly AzariPricingEngine $pricing,
        private readonly IdentityDocumentService $identityService,
    ) {}

    public function create(Request $request): Booking
    {
        $hold = BookingHold::query()
            ->with('property')
            ->active()
            ->where('token', $request->input('hold_token'))
            ->first();

        if (! $hold) {
            throw ValidationException::withMessages([
                'hold_token' => 'Your reservation hold expired. Please search again.',
            ]);
        }

        $hasAccountIdentity = (bool) $request->user()?->currentIdentity()->exists();
        $data = $request->validate($this->rules($hold, $hasAccountIdentity));

        return DB::transaction(function () use ($request, $hold, $data, $hasAccountIdentity): Booking {
            $lockedHold = BookingHold::query()
                ->with('property')
                ->active()
                ->whereKey($hold->id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->first();

            if (! $lockedHold) {
                throw ValidationException::withMessages([
                    'hold_token' => 'Your reservation hold expired. Please search again.',
                ]);
            }

            $lockedHold->property()
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            if (! $this->availability->available(
                $lockedHold->property_id,
                $lockedHold->check_in,
                $lockedHold->check_out,
                null,
                $lockedHold->token
            )) {
                throw ValidationException::withMessages([
                    'hold_token' => 'This residence is no longer available.',
                ]);
            }

            $quote = $this->pricing->quote(
                $lockedHold->property,
                $lockedHold->check_in,
                $lockedHold->check_out
            );

            $booking = Booking::query()->create([
                'reference' => $this->reference(),
                'user_id' => $request->user()?->id,
                'property_id' => $lockedHold->property_id,
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
                'expires_at' => now()->addMinutes((int) config('azari.booking.unpaid_booking_minutes', 60)),
            ]);

            foreach ($data['adults'] as $index => $adult) {
                $guest = BookingGuest::query()->create([
                    'booking_id' => $booking->id,
                    'type' => 'adult',
                    'position' => $index + 1,
                    'first_name' => $adult['first_name'],
                    'last_name' => $adult['last_name'],
                    'is_lead' => $index === 0,
                ]);

                if ($index === 0 && $hasAccountIdentity && $request->user()) {
                    $this->identityService->attachOwnerIdentity($booking, $request->user(), $guest);
                } else {
                    $this->identityService->storeGuestIdentity(
                        $booking,
                        $guest,
                        $adult['document_type'],
                        $request->file("adults.$index.document"),
                        $request->user()?->id
                    );
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
                'changed_by' => $request->user()?->id,
                'from_status' => null,
                'to_status' => 'pending_payment',
                'note' => 'Booking created and awaiting payment.',
                'metadata' => ['channel' => $request->user() ? 'registered' : 'guest'],
            ]);

            $lockedHold->delete();

            return $booking->refresh();
        }, 5);
    }

    private function rules(BookingHold $hold, bool $hasAccountIdentity): array
    {
        $rules = [
            'hold_token' => ['required', 'uuid'],
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
            $rules["adults.$index.document_type"] = [
                $index === 0 && $hasAccountIdentity ? 'nullable' : 'required',
                Rule::in(['passport', 'national_id', 'drivers_licence', 'other_government_id']),
            ];
            $rules["adults.$index.document"] = [
                $index === 0 && $hasAccountIdentity ? 'nullable' : 'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'max:10240',
            ];
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
            $reference = 'AZR-'.now()->format('ymd').'-'.Str::upper(Str::random(7));
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
