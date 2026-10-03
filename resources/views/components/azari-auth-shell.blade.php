@props([
    'title',
    'eyebrow' => 'Guest access',
    'heading',
    'description' => null,
])

<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#052058">
    <title>{{ $title }} | Resavar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="resavar-auth-body">
<main class="resavar-auth-layout">
    <section class="resavar-auth-brand-panel" aria-label="Resavar">
        <a href="{{ url('/') }}" class="resavar-auth-logo-link" aria-label="Resavar home">
            <img src="{{ asset('images/logo-dark.png') }}" alt="Resavar" class="resavar-auth-logo">
        </a>

        <div class="resavar-auth-brand-copy">
            <span>Exceptional stays, everywhere.</span>
            <h1>{{ $heading }}</h1>
            @if($description)
                <p>{{ $description }}</p>
            @endif
        </div>
    </section>

    <section class="resavar-auth-form-panel">
        <div class="resavar-auth-card">
            <a class="resavar-auth-back" href="{{ url('/') }}">
                <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
                <span>Back to homepage</span>
            </a>

            <div class="resavar-auth-heading">
                <span>{{ $eyebrow }}</span>
                <h2>{{ $title }}</h2>
                @if($description)
                    <p>{{ $description }}</p>
                @endif
            </div>

            <x-azari-toasts />
            {{ $slot }}
        </div>
    </section>
</main>
</body>
</html>
