<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DiningPartner extends Model
{
    protected $fillable = ['name','status','address','city','timezone','support_email','website',
        'dietary_options','accessibility','hours','disclosures','details_verified_at','reviewed_by'];

    protected function casts(): array
    {
        return ['dietary_options'=>'array','accessibility'=>'array','hours'=>'array',
            'details_verified_at'=>'datetime'];
    }

    public function isDiscoverable(): bool
    {
        return $this->status === 'published'
            && $this->details_verified_at?->greaterThan(now()->subDays(90));
    }
}
