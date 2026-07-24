<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $perPage = 10;

    protected $fillable = [
        'reference', 'user_id', 'property_id', 'hold_token', 'guest_name',
        'guest_first_name', 'guest_last_name', 'guest_email', 'guest_phone',
        'arrival_time', 'country', 'city', 'address', 'nationality', 'check_in',
        'check_out', 'adults', 'children', 'rooms', 'status', 'verification_status',
        'currency', 'nightly_rate', 'nights', 'subtotal', 'fee_total',
        'add_on_total', 'tax_rate', 'tax_total', 'total', 'pricing_snapshot',
        'guest_notes', 'admin_notes', 'paid_at', 'receipt_number', 'payment_reference',
        'cancelled_at', 'cancellation_reason', 'checked_in_at', 'completed_at',
        'room_assignment_locked_at', 'payment_transfer_locked_at', 'modified_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date', 'check_out' => 'date', 'arrival_time' => 'datetime:H:i',
            'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime', 'completed_at' => 'datetime',
            'room_assignment_locked_at' => 'datetime', 'payment_transfer_locked_at' => 'datetime',
            'modified_at' => 'datetime', 'expires_at' => 'datetime',
            'pricing_snapshot' => 'array', 'nightly_rate' => 'decimal:2',
            'subtotal' => 'decimal:2', 'fee_total' => 'decimal:2',
            'add_on_total' => 'decimal:2', 'tax_rate' => 'decimal:4',
            'tax_total' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function guests(): HasMany { return $this->hasMany(BookingGuest::class)->orderBy('type')->orderBy('position'); }
    public function statusHistory(): HasMany { return $this->hasMany(BookingStatusHistory::class)->latest(); }
    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(BookingAddOn::class)
            ->withPivot(['quantity', 'unit_price', 'line_total'])->withTimestamps();
    }

    public function scopeBlocksAvailability($query)
    {
        return $query->whereIn('status', ['paid', 'check_in']);
    }
}
