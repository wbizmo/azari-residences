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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | Resarva</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="az-standalone-auth-body az-auth-page">
    <main class="az-standalone-auth-shell az-auth-shell az-auth-main">
        <section class="az-standalone-auth-card az-auth-card" aria-labelledby="az-auth-heading">

            <span class="eyebrow">{{ $eyebrow }}</span>
            <h1 id="az-auth-heading">{{ $heading }}</h1>

            @if($description)
                <p class="az-standalone-auth-description">{{ $description }}</p>
            @endif

            <x-azari-toasts />

            {{ $slot }}
        </section>
    </main>
</body>
</html>
