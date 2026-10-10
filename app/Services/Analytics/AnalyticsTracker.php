<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AnalyticsTracker
{
    private const ALLOWED_EVENTS = [
        'homepage_viewed', 'search_started', 'search_submitted', 'results_viewed',
        'property_viewed', 'rate_selected', 'checkout_started', 'payment_started',
        'payment_failed', 'booking_confirmed', 'booking_cancelled',
        'booking_refunded', 'saved_search_alerted', 'recommendation_shown',
    ];

    private const BLOCKED_KEYS = [
        'name', 'first_name', 'last_name', 'email', 'phone', 'address', 'token',
        'password', 'document', 'identity', 'card', 'authorization', 'secret',
    ];

    public function track(string $event, array $context = [], ?string $idempotencyKey = null): AnalyticsEvent
    {
        if (! in_array($event, self::ALLOWED_EVENTS, true)) {
            throw new \InvalidArgumentException("Unsupported analytics event [{$event}].");
        }

        $request = request();
        $occurredAt = $context['occurred_at'] ?? now();
        $stableParts = [
            $event,
            $context['booking_id'] ?? null,
            $context['property_id'] ?? null,
            $context['accommodation_type_id'] ?? null,
            $context['rate_plan_id'] ?? null,
            $context['event_sequence'] ?? null,
        ];

        $key = $idempotencyKey ?: hash('sha256', json_encode($stableParts).':'.Str::uuid());

        return AnalyticsEvent::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'event' => $event,
                'user_id' => $context['user_id'] ?? auth()->id(),
                'booking_id' => $context['booking_id'] ?? null,
                'property_id' => $context['property_id'] ?? null,
                'accommodation_type_id' => $context['accommodation_type_id'] ?? null,
                'rate_plan_id' => $context['rate_plan_id'] ?? null,
                'anonymous_id_hash' => $this->anonymousHash($request),
                'session_hash' => $this->sessionHash($request),
                'source' => isset($context['source']) ? Str::limit((string) $context['source'], 120) : null,
                'payload' => $this->safePayload($context['payload'] ?? []),
                'occurred_at' => $occurredAt,
            ]
        );
    }

    private function safePayload(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);

            if (collect(self::BLOCKED_KEYS)->contains(fn (string $blocked) => str_contains($normalized, $blocked))) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? Str::limit($value, 250) : $value;
            }
        }

        return Arr::take($clean, 30);
    }

    private function anonymousHash(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        $seed = ($request->cookie('reserva_anon') ?: $request->userAgent() ?: '').'|'.($request->header('Accept-Language') ?: '');

        return trim($seed, '|') === '' ? null : hash_hmac('sha256', $seed, (string) config('app.key'));
    }

    private function sessionHash(?Request $request): ?string
    {
        if (! $request || ! $request->hasSession()) {
            return null;
        }

        return hash_hmac('sha256', $request->session()->getId(), (string) config('app.key'));
    }
}
