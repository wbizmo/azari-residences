<?php

namespace App\Services\Identity;

use App\Models\BookingGuest;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DojahService
{
    public function enabled(): bool
    {
        return (bool) config('azari.identity.dojah.enabled', false);
    }

    public function widgetConfigured(): bool
    {
        return $this->enabled() && $this->widgetId() !== '';
    }

    public function verificationForUser(User $user): IdentityVerification
    {
        abort_unless(
            Schema::hasTable('identity_verifications'),
            503,
            'Identity verification storage is not ready.'
        );

        return IdentityVerification::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'booking_guest_id' => null,
                'provider' => IdentityVerification::PROVIDER_DOJAH,
                'status' => IdentityVerification::STATUS_PENDING,
            ],
            [
                'reference' => (string) Str::uuid(),
                'widget_id' => $this->widgetId() ?: null,
            ]
        );
    }

    public function verificationForGuest(BookingGuest $guest): IdentityVerification
    {
        abort_unless(
            Schema::hasTable('identity_verifications'),
            503,
            'Identity verification storage is not ready.'
        );

        return IdentityVerification::query()->firstOrCreate(
            [
                'booking_guest_id' => $guest->id,
                'provider' => IdentityVerification::PROVIDER_DOJAH,
                'status' => IdentityVerification::STATUS_PENDING,
            ],
            [
                'user_id' => $guest->user_id,
                'reference' => (string) Str::uuid(),
                'widget_id' => $this->widgetId() ?: null,
            ]
        );
    }

    public function latestForUser(User $user): ?IdentityVerification
    {
        if (! Schema::hasTable('identity_verifications')) {
            return null;
        }

        return IdentityVerification::query()
            ->where('provider', IdentityVerification::PROVIDER_DOJAH)
            ->where('user_id', $user->id)
            ->whereNull('booking_guest_id')
            ->latest('id')
            ->first();
    }

    public function latestForGuest(BookingGuest $guest): ?IdentityVerification
    {
        if (! Schema::hasTable('identity_verifications')) {
            return null;
        }

        return IdentityVerification::query()
            ->where('provider', IdentityVerification::PROVIDER_DOJAH)
            ->where('booking_guest_id', $guest->id)
            ->latest('id')
            ->first();
    }

    public function widgetPayload(IdentityVerification $verification, array $userData = []): array
    {
        $widgetId = $this->widgetId();
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
            'launch_url' => $widgetId !== ''
                ? 'https://identity.dojah.io/?'.$query
                : null,
        ];
    }

    public function verifyWebhookSignature(
        string $rawBody,
        ?string $signature,
        ?string $signatureV2 = null
    ): bool {
        $secret = (string) config('azari.identity.dojah.secret_key');

        if ($secret === '') {
            return false;
        }

        if (filled($signature)) {
            $expected = hash_hmac('sha256', $rawBody, $secret);

            return hash_equals(
                $expected,
                strtolower(trim((string) $signature))
            );
        }

        if (filled($signatureV2)) {
            $expected = hash('sha256', $secret);

            return hash_equals(
                $expected,
                strtolower(trim((string) $signatureV2))
            );
        }

        return false;
    }

    public function processWebhook(array $payload, string $rawBody): ?IdentityVerification
    {
        if (! Schema::hasTable('identity_verifications')) {
            return null;
        }

        $reference = $this->extractReference($payload);

        if (! $reference) {
            return null;
        }

        $payloadHash = hash('sha256', $rawBody);
        $providerStatus = $this->extractStatus($payload);
        $outcome = $this->resolveOutcome($payload, $providerStatus);

        $verification = DB::transaction(function () use ($reference, $payloadHash, $payload, $providerStatus, $outcome): ?IdentityVerification {
            $verification = IdentityVerification::query()
                ->where('provider', IdentityVerification::PROVIDER_DOJAH)
                ->where('reference', $reference)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )
                ->first();

            if (! $verification) {
                return null;
            }

            if (
                $verification->last_payload_hash
                && hash_equals($verification->last_payload_hash, $payloadHash)
            ) {
                return $verification;
            }

            // A delayed/duplicate provider event must never downgrade a
            // completed verification. Re-verification creates a newer record.
            if ($verification->isVerified() && $outcome['status'] !== IdentityVerification::STATUS_VERIFIED) {
                return $verification;
            }

            $verification->fill([
            'provider_event_id' => $this->stringValue(
                Arr::get($payload, 'event_id')
                ?? Arr::get($payload, 'id')
            ),
            'provider_status' => $providerStatus,
            'verification_type' => $this->stringValue(
                Arr::get($payload, 'verification_type')
                ?? Arr::get($payload, 'data.verification_type')
            ),
            'verification_mode' => $this->stringValue(
                Arr::get($payload, 'verification_mode')
                ?? Arr::get($payload, 'data.verification_mode')
            ),
            'verification_url' => $this->stringValue(
                Arr::get($payload, 'verification_url')
                ?? Arr::get($payload, 'data.verification_url')
            ),
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
        } else {
            $verification->verified_at = null;

            if ($outcome['status'] !== IdentityVerification::STATUS_FAILED) {
                $verification->failed_at = null;
            }
        }

            $verification->save();

            return $verification->refresh();
        }, 5);

        if (! $verification) {
            return null;
        }

        if ($verification->user_id && $verification->booking_guest_id === null) {
            $this->syncUserVerificationToLeadGuests($verification);
        }

        if (
            $verification->booking_guest_id
            && $verification->isVerified()
        ) {
            $guest = BookingGuest::query()->find($verification->booking_guest_id);

            if ($guest?->user_id) {
                $user = User::query()->find($guest->user_id);

                if ($user) {
                    $this->syncVerifiedGuestToUser($guest, $user);
                }
            }
        }

        return $verification;
    }

    public function syncVerifiedUserToGuest(
        User $user,
        BookingGuest $guest
    ): ?IdentityVerification {
        $source = $this->latestForUser($user);

        if (! $source?->isVerified()) {
            return null;
        }

        $latestGuest = $this->latestForGuest($guest);

        if ($latestGuest?->isVerified()) {
            return $latestGuest;
        }

        return IdentityVerification::query()->create([
            'user_id' => $user->id,
            'booking_guest_id' => $guest->id,
            'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => (string) Str::uuid(),
            'widget_id' => $source->widget_id,
            'status' => IdentityVerification::STATUS_VERIFIED,
            'provider_status' => $source->provider_status,
            'verification_type' => $source->verification_type,
            'verification_mode' => $source->verification_mode,
            'verified_at' => $source->verified_at ?: now(),
            'metadata' => [
                'source_user_verification_id' => $source->id,
            ],
        ]);
    }

    public function syncVerifiedGuestToUser(
        BookingGuest $guest,
        User $user
    ): ?IdentityVerification {
        $source = $this->latestForGuest($guest);

        if (! $source?->isVerified()) {
            return null;
        }

        $latestUser = $this->latestForUser($user);

        if ($latestUser?->isVerified()) {
            return $latestUser;
        }

        $created = IdentityVerification::query()->create([
            'user_id' => $user->id,
            'booking_guest_id' => null,
            'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => (string) Str::uuid(),
            'widget_id' => $source->widget_id,
            'status' => IdentityVerification::STATUS_VERIFIED,
            'provider_status' => $source->provider_status,
            'verification_type' => $source->verification_type,
            'verification_mode' => $source->verification_mode,
            'verified_at' => $source->verified_at ?: now(),
            'metadata' => [
                'source_guest_verification_id' => $source->id,
            ],
        ]);

        $this->syncUserVerificationToLeadGuests($created);

        return $created;
    }

    private function syncUserVerificationToLeadGuests(
        IdentityVerification $source
    ): void {
        $leadGuests = BookingGuest::query()
            ->where('type', 'adult')
            ->where('is_lead', true)
            ->whereHas(
                'booking',
                fn ($query) => $query->where('user_id', $source->user_id)
            )
            ->get();

        foreach ($leadGuests as $guest) {
            $mirror = IdentityVerification::query()
                ->where('provider', IdentityVerification::PROVIDER_DOJAH)
                ->where('booking_guest_id', $guest->id)
                ->latest('id')
                ->first();

            if (! $mirror) {
                $mirror = new IdentityVerification([
                    'provider' => IdentityVerification::PROVIDER_DOJAH,
                    'reference' => (string) Str::uuid(),
                    'booking_guest_id' => $guest->id,
                ]);
            }

            $mirror->fill([
                'user_id' => $source->user_id,
                'widget_id' => $source->widget_id,
                'status' => $source->status,
                'provider_status' => $source->provider_status,
                'verification_type' => $source->verification_type,
                'verification_mode' => $source->verification_mode,
                'verified_at' => $source->isVerified()
                    ? ($source->verified_at ?: now())
                    : null,
                'failed_at' => $source->status === IdentityVerification::STATUS_FAILED
                    ? ($source->failed_at ?: now())
                    : null,
                'failure_reason' => $source->failure_reason,
                'last_event_at' => $source->last_event_at,
                'metadata' => [
                    'source_user_verification_id' => $source->id,
                ],
            ]);

            $mirror->save();
        }
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

        return filled($value)
            ? strtolower(trim((string) $value))
            : null;
    }

    private function resolveOutcome(
        array $payload,
        ?string $providerStatus
    ): array {
        $checks = $this->extractChecks($payload);

        $required = collect(
            config('azari.identity.dojah.required_steps', [])
        )
            ->map(fn ($step) => strtolower(trim((string) $step)))
            ->filter()
            ->values();

        $failedStatuses = [
            'failed',
            'failure',
            'declined',
            'rejected',
            'invalid',
            'cancelled',
            'canceled',
            'abandoned',
        ];

        if (
            $providerStatus
            && in_array($providerStatus, $failedStatuses, true)
        ) {
            return [
                'status' => IdentityVerification::STATUS_FAILED,
                'reason' => 'Dojah reported a failed identity verification.',
                'checks' => $checks,
            ];
        }

        if ($required->isNotEmpty()) {
            foreach ($required as $requiredStep) {
                if (
                    ! array_key_exists($requiredStep, $checks)
                    || $checks[$requiredStep] !== true
                ) {
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

        $verifiedStatuses = [
            'verified',
            'approved',
            'successful',
            'success',
            'passed',
        ];

        if (
            $providerStatus
            && in_array($providerStatus, $verifiedStatuses, true)
        ) {
            return [
                'status' => IdentityVerification::STATUS_VERIFIED,
                'reason' => null,
                'checks' => $checks,
            ];
        }

        if (
            $providerStatus === 'completed'
            && $checks !== []
            && ! in_array(false, $checks, true)
        ) {
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

            $checkName = strtolower((string) (
                $value['type']
                ?? $value['name']
                ?? $value['verification_type']
                ?? $name
                ?? ''
            ));

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

        return in_array(
            strtolower(trim((string) $value)),
            [
                'true',
                '1',
                'passed',
                'pass',
                'verified',
                'approved',
                'successful',
                'success',
                'completed',
            ],
            true
        );
    }

    private function widgetId(): string
    {
        $widgetId = trim((string) config('azari.identity.dojah.widget_id'));
        $tokenId = trim((string) config('azari.identity.dojah.token_id'));

        if (
            $widgetId === ''
            || ($tokenId !== '' && hash_equals($tokenId, $widgetId))
        ) {
            return '';
        }

        return $widgetId;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && filled($value)
            ? (string) $value
            : null;
    }
}
