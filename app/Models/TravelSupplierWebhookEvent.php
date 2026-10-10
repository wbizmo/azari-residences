<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TravelSupplierWebhookEvent extends Model
{
    protected $guarded = [];
    protected $hidden = ['encrypted_body', 'body_sha256'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(TravelSupplier::class, 'travel_supplier_id');
    }

    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }
}
