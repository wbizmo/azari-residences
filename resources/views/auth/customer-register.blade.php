{{-- Compatibility view: use the single secured registration form, not an unguarded
    alternative missing the anti-abuse token and honeypot. --}}
@include('auth.register', [
    'authFormToken' => $authFormToken
        ?? app(\App\Support\AuthAbuseGuard::class)->formToken('register'),
])
