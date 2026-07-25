<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProviderStatus extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'last_successful_payment_at' => 'datetime',
        ];
    }
}
