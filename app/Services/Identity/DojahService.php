<?php

namespace App\Services\Identity;

use App\Models\BookingGuest;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DojahService
{
    public function enabled(): bool
    {
        return (bool) config('azari.identity.dojah.enabled', false);
    }

    public function widgetConfigured(): bool
    {
        return $this->enabled() && filled(config('azari.identity.dojah.widget_id'));
    }

    public function verificationForUser(User $user): IdentityVerification
    {
        return IdentityVerification::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'booking_guest_id' => null,
                'provider' => IdentityVerification::PROVIDER_DOJAH,
                'status' => IdentityVerification::STATUS_PENDING,
            ],
            [
                'reference' => (string) Str::uuid(),
                'widget_id' => config('azari.identity.dojah.widget_id'),
            ]
        );
    }

    public function verificationForGuest(BookingGuest $guest): IdentityVerification
    {
        return IdentityVerification::query()->firstOrCreate(
            [
                'user_id' => null,
                'booking_guest_id' => $guest->id,
                'provider' => IdentityVerification::PROVIDER_DOJAH,
                'status' => IdentityVerification::STATUS_PENDING,
            ],
            [
                'reference' => (string) Str::uuid(),
                'widget_id' => config('azari.identity.dojah.widget_id'),
            ]
        );
    }

    public function latestForUser(User $user): ?IdentityVerification
    {
        return IdentityVerification::query()
            ->where('provider', IdentityVerification::PROVIDER_DOJAH)
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    public function latestForGuest(BookingGuest $guest): ?IdentityVerification
    {
        return IdentityVerification::query()
            ->where('provider', IdentityVerification::PROVIDER_DOJAH)
            ->where('booking_guest_id', $guest->id)
            ->latest('id')
            ->first();
    }

    public function widgetPayload(IdentityVerification $verification, array $userData = []): array
    {
        $widgetId = (string) config('azari.identity.dojah.widget_id');
        $widgetType = (string) config('azari.identity.dojah.widget_type', 'custom');

        $query = http_build_query([
            'widget_type' => $widgetType,
            'widget_id' => $widgetId,
            'reference_id' => $verification->reference,
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'widget_id' => $widgetId,
            'type' => $widgetType,
            'reference_id' => $verification->reference,
            'launch_url' => 'https://identity.dojah.io/?'.$query,
        ];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature, ?string $signatureV2 = null): bool
    {
        $secret = (string) config('azari.identity.dojah.secret_key');
        if ($secret === '') {
            return false;
        }

        if (filled($signature)) {
            $expected = hash_hmac('sha256', $rawBody, $secret);
            return hash_equals($expected, strtolower(trim((string) $signature)));
        }

        if (filled($signatureV2)) {
            $expected = hash('sha256', $secret);
            return hash_equals($expected, strtolower(trim((string) $signatureV2)));
        }

        return false;
    }

    public function processWebhook(array $payload, string $rawBody): ?IdentityVerification
    {
        $reference = $this->extractReference($payload);
        if (! $reference) {
            return null;
        }

        $verification = IdentityVerification::query()
            ->where('provider', IdentityVerification::PROVIDER_DOJAH)
            ->where('reference', $reference)
            ->first();

        if (! $verification) {
            return null;
        }

        $payloadHash = hash('sha256', $rawBody);
        if ($verification->last_payload_hash && hash_equals($verification->last_payload_hash, $payloadHash)) {
            return $verification;
        }

        $providerStatus = $this->extractStatus($payload);
        $outcome = $this->resolveOutcome($payload, $providerStatus);

        $verification->fill([
            'provider_event_id' => $this->stringValue(Arr::get($payload, 'event_id') ?? Arr::get($payload, 'id')),
            'provider_status' => $providerStatus,
            'verification_type' => $this->stringValue(Arr::get($payload, 'verification_type') ?? Arr::get($payload, 'data.verification_type')),
            'verification_mode' => $this->stringValue(Arr::get($payload, 'verification_mode') ?? Arr::get($payload, 'data.verification_mode')),
            'verification_url' => $this->stringValue(Arr::get($payload, 'verification_url') ?? Arr::get($payload, 'data.verification_url')),
            'status' => $outcome['status'],
            'failure_reason' => $outcome['reason'],
            'last_event_at' => now(),
            'last_payload_hash' => $payloadHash,
            'metadata' => [
                'service' => $this->stringValue(Arr::get($payload, 'service')),
                'environment' => $this->stringValue(Arr::get($payload, 'environment')),
                'checks' => $outcome['checks'],
            ],
        ]);

        if ($outcome['status'] === IdentityVerification::STATUS_VERIFIED) {
            $verification->verified_at ??= now();
            $verification->failed_at = null;
        } elseif ($outcome['status'] === IdentityVerification::STATUS_FAILED) {
            $verification->failed_at ??= now();
            $verification->verified_at = null;
        }

        $verification->save();

        return $verification;
    }

    private function extractReference(array $payload): ?string
    {
        $reference = Arr::get($payload, 'reference_id')
            ?? Arr::get($payload, 'data.reference_id')
            ?? Arr::get($payload, 'reference');

        return filled($reference) ? (string) $reference : null;
    }

    private function extractStatus(array $payload): ?string
    {
        $value = Arr::get($payload, 'verification_status')
            ?? Arr::get($payload, 'data.verification_status')
            ?? Arr::get($payload, 'status');

        return filled($value) ? strtolower(trim((string) $value)) : null;
    }

    private function resolveOutcome(array $payload, ?string $providerStatus): array
    {
        $checks = $this->extractChecks($payload);
        $required = collect(config('azari.identity.dojah.required_steps', []))
            ->map(fn ($step) => strtolower(trim((string) $step)))
            ->filter()
            ->values();

        $failedStatuses = ['failed', 'failure', 'declined', 'rejected', 'invalid', 'cancelled', 'canceled', 'abandoned'];
        if ($providerStatus && in_array($providerStatus, $failedStatuses, true)) {
            return [
                'status' => IdentityVerification::STATUS_FAILED,
                'reason' => 'Dojah reported a failed identity verification.',
                'checks' => $checks,
            ];
        }

        if ($required->isNotEmpty()) {
            foreach ($required as $requiredStep) {
                if (! array_key_exists($requiredStep, $checks) || $checks[$requiredStep] !== true) {
                    return [
                        'status' => IdentityVerification::STATUS_NEEDS_REVIEW,
                        'reason' => 'A required Dojah verification step is incomplete or did not pass.',
                        'checks' => $checks,
                    ];
                }
            }
        }

        if (in_array(false, $checks, true)) {
            return [
                'status' => IdentityVerification::STATUS_NEEDS_REVIEW,
                'reason' => 'One or more Dojah verification checks did not pass.',
                'checks' => $checks,
            ];
        }

        $verifiedStatuses = ['verified', 'approved', 'successful', 'success', 'passed'];
        if ($providerStatus && in_array($providerStatus, $verifiedStatuses, true)) {
            return [
                'status' => IdentityVerification::STATUS_VERIFIED,
                'reason' => null,
                'checks' => $checks,
            ];
        }

        if ($providerStatus === 'completed' && $checks !== [] && ! in_array(false, $checks, true)) {
            return [
                'status' => IdentityVerification::STATUS_VERIFIED,
                'reason' => null,
                'checks' => $checks,
            ];
        }

        if ($providerStatus === 'completed') {
            return [
                'status' => IdentityVerification::STATUS_NEEDS_REVIEW,
                'reason' => 'Dojah completed the flow without a conclusive verified result.',
                'checks' => $checks,
            ];
        }

        return [
            'status' => IdentityVerification::STATUS_PENDING,
            'reason' => null,
            'checks' => $checks,
        ];
    }

    private function extractChecks(array $payload): array
    {
        $checks = [];
        $data = Arr::get($payload, 'data', []);

        if (! is_array($data)) {
            return $checks;
        }

        foreach ($data as $key => $value) {
            $name = is_string($key) ? strtolower($key) : null;

            if (is_bool($value) && $name) {
                $checks[$name] = $value;
                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $checkName = strtolower((string) ($value['type'] ?? $value['name'] ?? $value['verification_type'] ?? $name ?? ''));
            if ($checkName === '') {
                continue;
            }

            if (array_key_exists('status', $value)) {
                $checks[$checkName] = $this->toBooleanStatus($value['status']);
            } elseif (array_key_exists('verified', $value)) {
                $checks[$checkName] = $this->toBooleanStatus($value['verified']);
            } elseif (array_key_exists('success', $value)) {
                $checks[$checkName] = $this->toBooleanStatus($value['success']);
            }
        }

        return $checks;
    }

    private function toBooleanStatus(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['true', '1', 'passed', 'pass', 'verified', 'approved', 'successful', 'success', 'completed'], true);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && filled($value) ? (string) $value : null;
    }
}
