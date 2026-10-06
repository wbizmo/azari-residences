<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationPreference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'email_transactional' => 'boolean',
            'sms_transactional' => 'boolean',
            'whatsapp_transactional' => 'boolean',
            'in_app_transactional' => 'boolean',
            'email_marketing' => 'boolean',
            'sms_marketing' => 'boolean',
            'whatsapp_marketing' => 'boolean',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function allows(string $channel, string $classification = 'transactional'): bool
    {
        $key = $channel.'_'.($classification === 'marketing' ? 'marketing' : 'transactional');

        return array_key_exists($key, $this->attributes)
            ? (bool) $this->getAttribute($key)
            : $classification !== 'marketing';
    }
}
