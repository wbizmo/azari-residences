<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentityType extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public function userDocuments(): HasMany
    {
        return $this->hasMany(UserIdentityDocument::class);
    }
}
