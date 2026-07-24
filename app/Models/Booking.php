<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $perPage = 10;

    protected $fillable = [
        'reference',
        'user_id',
        'property_id',
        'guest_name',
        'arrival_time',
        'country',
        'city',
        'address',
        'nationality',
        'guest_last_name',
        'guest_first_name',
        'guest_email',
        'guest_phone',
        'check_in',
        'check_out',
        'adults',
        'children',
        'rooms',
        'status',
        'verification_status',
        'currency',
        'subtotal',
        'tax_total',
        'total',
        'guest_notes',
        'admin_notes',
        'approved_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function guests(): HasMany
    {
        return $this->hasMany(BookingGuest::class)->orderBy('type')->orderBy('position');
    }
}
