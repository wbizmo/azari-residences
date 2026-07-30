@props([
    'title' => 'Azari Residences | Premium Serviced Residences',
    'description' => null,
    'keywords' => null,
    'canonical' => null,
    'image' => null,
    'type' => 'website',
    'bodyClass' => '',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('partials.material-symbols-preload')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.public-seo', [
        'seoTitle' => $title,
        'seoDescription' => $description,
        'seoKeywords' => $keywords,
        'seoCanonical' => $canonical,
        'seoImage' => $image,
        'seoType' => $type,
    ])

    @include('partials.azari-head-assets')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,400&family=IBM+Plex+Mono:wght@400;500&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="public-site {{ request()->routeIs('home') ? 'home-page azari-home-page' : 'inner-page azari-inner-page azari-solid-header' }} {{ $bodyClass ?? '' }}">
    <div class="azari-preloader" data-public-preloader role="status" aria-label="Loading Azari Residences">
        <span class="azari-preloader__mark" aria-hidden="true">
            <span class="azari-preloader__spinner"></span>
            <img src="{{ asset('images/azari-favicon.png') }}" alt="" width="42" height="42" loading="eager" decoding="sync">
        </span>
    </div>

    <a class="skip-link" href="#main-content">Skip to main content</a>

    @include('public.partials.navigation')

    <main id="main-content">
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>

    @include('public.partials.footer')

    <button type="button" class="back-to-top" data-back-to-top aria-label="Back to top" title="Back to top">
        <span class="material-symbols-outlined" aria-hidden="true">arrow_upward</span>
    </button>
@include('public.partials.drawer-root')

    @stack('scripts')
    <x-azari-feedback />
</body>
</html>
