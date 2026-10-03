<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#052058">
    <title>{{ $title ?? 'Resavar' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="resavar-guest-body">
    <main class="resavar-guest-shell">
        <section class="resavar-guest-brand-panel" aria-label="Resavar">
            <a href="{{ url('/') }}" class="resavar-guest-brand-link" aria-label="Resavar home">
                <img src="{{ asset('images/logo-dark.png') }}" alt="Resavar" class="resavar-guest-brand-logo">
            </a>
            <div class="resavar-guest-brand-copy">
                <span>Exceptional stays, everywhere.</span>
                <h1>Welcome to Resavar</h1>
                <p>Manage your account, bookings and stay details securely.</p>
            </div>
        </section>

        <section class="resavar-guest-content">
            <div class="resavar-guest-card">
                {{ $slot }}
            </div>
        </section>
    </main>
    <x-azari-toasts />
</body>
</html>
