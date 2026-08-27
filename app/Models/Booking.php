<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Booking extends Model
{
    use HasFactory;

    protected $perPage = 10;

    protected $fillable = [
        'reference', 'user_id', 'property_id', 'voucher_id', 'voucher_code', 'discount_total', 'voucher_snapshot', 'hold_token', 'guest_name',
        'guest_first_name', 'guest_last_name', 'guest_email', 'guest_phone',
        'arrival_time', 'country', 'city', 'address', 'nationality', 'check_in',
        'check_out', 'adults', 'children', 'rooms', 'status', 'verification_status',
        'currency', 'nightly_rate', 'nights', 'subtotal', 'fee_total',
        'add_on_total', 'tax_rate', 'tax_total', 'total', 'pricing_snapshot',
        'property_name_snapshot', 'property_formatted_address',
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
            'pricing_snapshot' => 'array', 'voucher_snapshot' => 'array', 'discount_total' => 'decimal:2', 'nightly_rate' => 'decimal:2',
            'subtotal' => 'decimal:2', 'fee_total' => 'decimal:2',
            'add_on_total' => 'decimal:2', 'tax_rate' => 'decimal:4',
            'tax_total' => 'decimal:2', 'total' => 'decimal:2',
            'property_latitude' => 'decimal:7', 'property_longitude' => 'decimal:7',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
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

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(BookingAddOn::class)
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
        return ! $this->isCancelled()
            && in_array($this->status, ['paid', 'confirmed', 'check_in', 'checked_in', 'checked_out', 'completed'], true)
            && $this->paid_at !== null
            && (filled($this->payment_reference) || filled($this->receipt_number));
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

    public function isCheckInEligible(): bool
    {
        $baseEligible = in_array($this->status, ['confirmed', 'paid'], true)
            && $this->balanceDue() <= 0
            && ! $this->cancelled_at
            && ! $this->checked_in_at
            && now(config('azari.timezone', 'Africa/Lagos'))->toDateString() === optional($this->check_in)->toDateString();

        if (! $baseEligible || ! Schema::hasTable('identity_verifications')) {
            return false;
        }

        $adultIds = $this->guests()->where('type', 'adult')->pluck('id');

        return $adultIds->isNotEmpty()
            && $adultIds->every(fn ($guestId) => IdentityVerification::guestIsVerified((int) $guestId));
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
