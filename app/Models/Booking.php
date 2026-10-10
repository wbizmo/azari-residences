<?php

namespace App\Models;

use App\Support\LocalDate;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $perPage = 10;

    protected $fillable = [
        'reference', 'user_id', 'property_id', 'accommodation_type_id', 'rate_plan_id', 'voucher_id', 'voucher_code', 'discount_total', 'voucher_snapshot', 'hold_token', 'guest_name',
        'guest_first_name', 'guest_last_name', 'guest_email', 'guest_phone',
        'arrival_time', 'country', 'city', 'address', 'nationality', 'check_in',
        'check_out', 'adults', 'children', 'rooms', 'status', 'verification_status',
        'currency', 'property_timezone', 'booking_locale', 'nightly_rate', 'nights', 'subtotal', 'fee_total',
        'add_on_total', 'tax_rate', 'tax_total', 'total', 'pricing_snapshot',
        'property_name_snapshot', 'accommodation_type_name_snapshot', 'rate_plan_name_snapshot', 'policy_snapshot', 'property_formatted_address',
        'property_latitude', 'property_longitude',
        'guest_notes', 'admin_notes', 'paid_at', 'receipt_number', 'payment_reference',
        'cancelled_at', 'cancellation_reason', 'cancellation_internal_note',
        'cancellation_payment_note', 'external_refund_reference', 'cancelled_by',
        'checked_in_at', 'checked_out_at', 'no_show_at', 'check_in_reversed_at', 'completed_at', 'room_assignment_locked_at',
        'payment_transfer_locked_at', 'modified_at', 'expires_at', 'payment_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date', 'check_out' => 'date', 'arrival_time' => 'datetime:H:i',
            'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'no_show_at' => 'datetime', 'check_in_reversed_at' => 'datetime', 'completed_at' => 'datetime',
            'room_assignment_locked_at' => 'datetime', 'payment_transfer_locked_at' => 'datetime',
            'modified_at' => 'datetime', 'expires_at' => 'datetime', 'payment_reminder_sent_at' => 'datetime',
            'pricing_snapshot' => 'array', 'policy_snapshot' => 'array', 'voucher_snapshot' => 'array', 'discount_total' => 'decimal:2', 'nightly_rate' => 'decimal:2',
            'subtotal' => 'decimal:2', 'fee_total' => 'decimal:2',
            'add_on_total' => 'decimal:2', 'tax_rate' => 'decimal:4',
            'tax_total' => 'decimal:2', 'total' => 'decimal:2',
            'property_latitude' => 'decimal:7', 'property_longitude' => 'decimal:7',
        ];
    }

    public function tripItinerary(): BelongsTo { return $this->belongsTo(TripItinerary::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function accommodationType(): BelongsTo { return $this->belongsTo(AccommodationType::class); }
    public function ratePlan(): BelongsTo { return $this->belongsTo(RatePlan::class); }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function guests(): HasMany { return $this->hasMany(BookingGuest::class)->orderBy('type')->orderBy('position'); }
    public function statusHistory(): HasMany { return $this->hasMany(BookingStatusHistory::class)->latest(); }
    public function payments(): HasMany { return $this->hasMany(Payment::class)->latest(); }
    public function identityLinks(): HasMany { return $this->hasMany(BookingIdentityLink::class); }
    public function lifecycleEvents(): HasMany { return $this->hasMany(StayLifecycleEvent::class)->latest(); }
    public function serviceRequests(): HasMany { return $this->hasMany(ServiceRequest::class)->latest(); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class)->latest(); }
    public function review(): HasOne { return $this->hasOne(Review::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class)->latest(); }
    public function cancellationOverrides(): HasMany { return $this->hasMany(BookingCancellationOverride::class)->latest(); }
    public function modificationRequests(): HasMany { return $this->hasMany(BookingModificationRequest::class)->latest(); }
    public function operationalNotes(): HasMany { return $this->hasMany(BookingOperationalNote::class)->latest(); }
    public function analyticsEvents(): HasMany { return $this->hasMany(AnalyticsEvent::class); }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(BookingAddOn::class, 'booking_add_on_booking')
            ->withPivot(['quantity', 'unit_price', 'line_total'])->withTimestamps();
    }

    public function scopeBlocksAvailability($query)
    {
        return $query->whereIn('status', ['paid', 'confirmed', 'check_in', 'checked_in']);
    }

    public function successfulPayment(): ?Payment
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments
                ->where('status', Payment::SUCCESSFUL)
                ->sortByDesc(fn (Payment $payment) => $payment->paid_at ?: $payment->created_at)
                ->first();
        }

        return $this->payments()
            ->where('status', Payment::SUCCESSFUL)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->first();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || $this->cancelled_at !== null;
    }

    public function hasLegacyPaidRecord(): bool
    {
        // Only *legacy* bookings without verified Payment rows may use old
        // receipt fields as evidence of full settlement. Modern bookings must
        // use the actual sum of verified payments, including after amendments.
        return ! $this->isCancelled()
            && in_array($this->status, ['paid', 'confirmed', 'check_in', 'checked_in', 'checked_out', 'completed'], true)
            && $this->paid_at !== null
            && (filled($this->payment_reference) || filled($this->receipt_number))
            && ! $this->payments()->where('status', Payment::SUCCESSFUL)->exists();
    }

    public function isPaid(): bool
    {
        $successfulTotal = (float) $this->payments()
            ->where('status', Payment::SUCCESSFUL)
            ->sum('amount');

        return ! $this->isCancelled()
            && ($successfulTotal + 0.009 >= (float) $this->total || $this->hasLegacyPaidRecord());
    }

    public function receiptAvailable(): bool
    {
        return $this->isPaid();
    }

    public function canAcceptPayment(): bool
    {
        return ! $this->isCancelled() && ! $this->isPaid() && $this->balanceDue() > 0;
    }

    public function successfulPaymentsTotal(): float
    {
        $total = (float) $this->payments()
            ->where('status', Payment::SUCCESSFUL)
            ->sum('amount');

        return $total <= 0 && $this->hasLegacyPaidRecord()
            ? (float) $this->total
            : $total;
    }

    public function successfulRefundsTotal(): float
    {
        // Refunds of verified-but-unallocated amendment topups are not
        // booking revenue reversals. Only refunds against payments actually
        // allocated to this booking reduce its settled balance.
        return (float) $this->refunds()
            ->where('status', 'successful')
            ->whereHas('payment', fn ($q) => $q->where('status', Payment::SUCCESSFUL))
            ->sum('amount');
    }

    public function netPaidTotal(): float
    {
        return max(0, round($this->successfulPaymentsTotal() - $this->successfulRefundsTotal(), 2));
    }

    public function documentPayment(): ?Payment
    {
        if ($payment = $this->successfulPayment()) {
            return $payment;
        }

        if (! $this->hasLegacyPaidRecord()) {
            return null;
        }

        return new Payment([
            'reference' => $this->payment_reference ?: 'LEGACY-'.$this->reference,
            'provider_reference' => $this->payment_reference,
            'provider' => 'manual',
            'payment_method' => 'Recorded payment',
            'amount' => (float) $this->total,
            'currency' => $this->currency,
            'status' => Payment::SUCCESSFUL,
            'receipt_number' => $this->receipt_number,
            'paid_at' => $this->paid_at,
            'verified_at' => $this->paid_at,
        ]);
    }

    /** Reasons self check-in is not yet safe. Owners can still use staffed check-in. */
    public function selfCheckInBlockers(): array
    {
        $reasons = [];

        if (! in_array($this->status, ['confirmed', 'paid'], true)
            || $this->cancelled_at !== null || $this->checked_in_at !== null) {
            $reasons[] = 'This reservation is not in an eligible confirmed state.';
        }

        if ($this->balanceDue() > 0) {
            $reasons[] = 'Outstanding payment must be settled and verified.';
        }

        $timezone = $this->property_timezone ?: LocalDate::propertyTimezone($this->property);
        if (now($timezone)->toDateString() !== $this->check_in?->toDateString()) {
            $reasons[] = 'Online check-in is available only on your arrival date.';
        }

        if (! $this->user_id || ! IdentityVerification::userIsVerified((int) $this->user_id)) {
            $reasons[] = 'The booking guest must complete identity verification.';
        }

        $ready = PropertyOperationsTask::query()
            ->where('property_id', $this->property_id)
            ->where('booking_id', $this->getKey())
            ->whereIn('type', ['housekeeping', 'inspection'])
            ->where('status', 'completed')
            ->exists();

        $unfinished = PropertyOperationsTask::query()
            ->where('property_id', $this->property_id)
            ->where('booking_id', $this->getKey())
            ->whereIn('type', ['housekeeping', 'inspection', 'maintenance'])
            ->whereIn('status', ['open', 'in_progress', 'blocked'])
            ->exists();

        if (! $ready || $unfinished) {
            $reasons[] = 'Your room has not yet been marked ready by the property team.';
        }

        return $reasons;
    }

    public function isCheckInEligible(): bool
    {
        return $this->selfCheckInBlockers() === [];
    }

    public function directionsUrl(): ?string
    {
        $destination = null;

        if ($this->property_latitude !== null && $this->property_longitude !== null) {
            $destination = $this->property_latitude.','.$this->property_longitude;
        } elseif (filled($this->property_formatted_address)) {
            $destination = $this->property_formatted_address;
        }

        if (! $destination) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query([
            'api' => 1,
            'destination' => $destination,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function balanceDue(): float
    {
        if ($this->isPaid()) {
            return 0.0;
        }

        return max(0, round((float) $this->total - $this->successfulPaymentsTotal(), 2));
    }
}
