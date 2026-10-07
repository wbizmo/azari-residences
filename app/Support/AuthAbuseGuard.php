<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AuthAbuseGuard
{
    public function formToken(string $context): string
    {
        return Crypt::encryptString($context.'|'.now()->timestamp.'|'.Str::random(32));
    }

    public function assertHumanForm(
        Request $request,
        string $context,
        ?string $identity = null,
        int $maxPerWindow = 5,
        int $decaySeconds = 600,
        int $minimumAgeSeconds = 2
    ): void {
        $this->assertHoneypot($request, $context);
        $this->assertTimedToken($request, $context, $minimumAgeSeconds);
        $this->assertRateLimits($request, $context, $identity, $maxPerWindow, $decaySeconds);
    }

    public function assertHoneypot(Request $request, string $context): void
    {
        if (filled($request->input('company_website')) || filled($request->input('contact_fax'))) {
            RateLimiter::hit($this->key($context, 'trap', $this->remoteAddress($request)), 3600);

            throw ValidationException::withMessages([
                'email' => 'We could not process this request. Please try again later.',
            ]);
        }

        $userAgent = trim((string) $request->userAgent());
        if ($userAgent === '' || mb_strlen($userAgent) > 1000) {
            throw ValidationException::withMessages([
                'email' => 'We could not process this request. Please try again later.',
            ]);
        }
    }

    public function assertRateLimits(
        Request $request,
        string $context,
        ?string $identity = null,
        int $maxPerWindow = 5,
        int $decaySeconds = 600
    ): void {
        $clientIp = (string) $request->ip();
        $remoteIp = $this->remoteAddress($request);

        $this->hitOrFail(
            $this->key($context, 'client-ip', $clientIp),
            $maxPerWindow,
            $decaySeconds
        );

        // REMOTE_ADDR cannot be supplied directly by a browser. Keep this
        // ceiling much higher because multiple visitors may share one proxy.
        $this->hitOrFail(
            $this->key($context, 'edge-ip', $remoteIp),
            max(60, $maxPerWindow * 12),
            $decaySeconds
        );

        if (filled($identity)) {
            $normalized = Str::lower(trim((string) $identity));
            $this->hitOrFail(
                $this->key($context, 'identity', $normalized),
                max(3, min($maxPerWindow, 5)),
                max($decaySeconds, 900)
            );
        }
    }

    private function assertTimedToken(
        Request $request,
        string $context,
        int $minimumAgeSeconds
    ): void {
        $token = (string) $request->input('_auth_form_token');

        try {
            $payload = Crypt::decryptString($token);
            [$tokenContext, $timestamp] = array_pad(explode('|', $payload, 3), 3, null);
            $age = now()->timestamp - (int) $timestamp;
        } catch (Throwable) {
            $this->reject();
        }

        if (
            ! hash_equals($context, (string) $tokenContext)
            || $age < $minimumAgeSeconds
            || $age > 7200
        ) {
            $this->reject();
        }
    }

    private function hitOrFail(string $key, int $maxAttempts, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again later.',
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'email' => 'We could not process this request. Please reload the page and try again.',
        ]);
    }

    private function key(string $context, string $dimension, string $value): string
    {
        return 'auth-abuse:'.$context.':'.$dimension.':'.hash('sha256', $value);
    }

    private function remoteAddress(Request $request): string
    {
        return (string) ($request->server('REMOTE_ADDR') ?: 'unknown');
    }
}
