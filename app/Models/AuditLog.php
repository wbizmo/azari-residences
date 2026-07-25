<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'metadata' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?int $actorId = null,
    ): self {
        $request = request();

        return self::query()->create([
            'actor_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'request_id' => $request?->headers->get('X-Request-ID') ?: $request?->attributes->get('request_id'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'old_values' => self::redact($oldValues),
            'new_values' => self::redact($newValues),
            'metadata' => self::redact($metadata),
        ]);
    }

    public static function redact(array $values): array
    {
        $sensitive = ['password', 'password_confirmation', 'token', 'secret', 'api_key', 'authorization', 'document', 'payload'];
        $walk = function (array $input) use (&$walk, $sensitive): array {
            foreach ($input as $key => $value) {
                $normalized = strtolower((string) $key);
                if (collect($sensitive)->contains(fn (string $needle) => str_contains($normalized, $needle))) {
                    $input[$key] = '[REDACTED]';
                } elseif (is_array($value)) {
                    $input[$key] = $walk($value);
                }
            }
            return $input;
        };

        return $walk($values);
    }
}
