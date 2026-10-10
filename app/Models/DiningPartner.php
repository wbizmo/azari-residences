<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DiningPartner extends Model
{
    protected $fillable = ['name','integration_key','latitude','longitude','status','address','city','timezone','support_email','website',
        'dietary_options','accessibility','hours','disclosures','details_verified_at','reviewed_by'];

    protected function casts(): array
    {
        return ['dietary_options'=>'array','accessibility'=>'array','hours'=>'array',
            'latitude'=>'decimal:7','longitude'=>'decimal:7',
            'details_verified_at'=>'datetime'];
    }

    public function isDiscoverable(): bool
    {
        return $this->status === 'published'
            && $this->details_verified_at?->greaterThan(now()->subDays(90));
    }
}
