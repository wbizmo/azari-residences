<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#052058">
    <title>Resavar | Exceptional Stays, Everywhere.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="resavar-welcome-body">
    <main class="resavar-welcome">
        <section class="resavar-welcome__brand">
            <a href="{{ url('/') }}" aria-label="Resavar home">
                <img src="{{ asset('images/resavar-logo-dark.png') . '?v=20261004-4' }}" alt="Resavar">
            </a>
            <div>
                <span>Exceptional Stays, Everywhere.</span>
                <h1>Welcome to Resavar.</h1>
                <p>Discover thoughtfully managed stays, direct booking and dependable guest support.</p>
            </div>
        </section>

        <section class="resavar-welcome__actions">
            <div>
                <span>Resavar</span>
                <h2>Plan your next stay.</h2>
                <p>Search live availability or sign in to manage an existing booking.</p>

                <div class="resavar-welcome__buttons">
                    @if(Route::has('availability.index'))
                        <a class="button button-primary" href="{{ route('availability.index') }}">Check availability</a>
                    @endif
                    @auth
                        <a class="button button-secondary" href="{{ route('user.dashboard') }}">Open guest portal</a>
                    @else
                        <a class="button button-secondary" href="{{ route('login') }}">Sign in</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>
</body>
</html>
