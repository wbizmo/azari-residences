@props([
    'title',
    'eyebrow' => 'Guest access',
    'heading',
    'description' => null,
])

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.material-symbols-preload')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#052058">
    <title>{{ $title }} — Resavar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="resavar-auth-body">
    <a class="skip-link" href="#resavar-auth-form">Skip to account form</a>
    <main class="auth resavar-auth-unified resavar-auth-recovery">
        <section class="visual" aria-label="Resavar stays">
            <img src="{{ asset('images/azari-guest-auth-suite.png') }}" alt="" loading="lazy">
            <a href="{{ route('home') }}" class="brand resavar-auth-logo" aria-label="Resavar homepage">
                <x-brand-logo variant="hero" dark />
            </a>
            <div class="resavar-auth-visual-copy">
                <span class="resavar-auth-visual-kicker">RESAVAR · YOUR STAYS</span>
                <h2>Welcome back <em>to remarkable.</em></h2>
                <p>Your hotel stays, serviced apartments and reservations remain yours to manage securely.</p>
                <span class="resavar-auth-visual-foot">DISCOVER · REQUEST · STAY</span>
            </div>
        </section>

        <section class="panel" id="resavar-auth-form" aria-labelledby="resavar-auth-heading">
            <div class="wrap">
                <p class="kicker">{{ $eyebrow }}</p>
                <h1 id="resavar-auth-heading">{{ $heading }}</h1>
                @if($description)
                    <p class="sub">{{ $description }}</p>
                @endif

                @if(session('status'))
                    <p role="status" class="resavar-auth-status">{{ session('status') }}</p>
                @endif
                @if($errors->any())
                    <div class="resavar-auth-errors" role="alert">
                        <strong>Please correct the following:</strong>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <x-azari-toasts />
                {{ $slot }}
            </div>
        </section>
    </main>
</body>
</html>
