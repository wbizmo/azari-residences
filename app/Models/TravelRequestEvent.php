<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelRequestEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'travel_request_id', 'actor_id', 'event_type',
        'previous_status', 'new_status', 'details', 'created_at',
    ];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }
}
