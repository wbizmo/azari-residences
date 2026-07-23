<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    
                <title>('title', 'Azari Residences | Curated Serviced Apartments & Private Stays')</title>
            
    <link rel="icon" href="{{ !empty($siteSettings['favicon_url']) ? $siteSettings['favicon_url'] : route('public.favicon') }}">
    <meta name="description" content="{{ $description ?? 'Private, fully serviced residences in Lagos with direct booking and dedicated guest support.' }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,400&family=IBM+Plex+Mono:wght@400;500&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="public-site {{ $bodyClass }}">
    <div class="public-preloader" data-public-preloader aria-hidden="true">
        <div class="public-preloader-mark">
            <span class="public-preloader-ring"></span>
            
                <img src="{{ $siteSettings['logo_url'] }}" alt="">
            
                <span class="material-symbols-outlined">hotel_class</span>
            
        </div>
    </div>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    @include('public.partials.navigation')

    <main id="main-content">
        {{ $slot }}
    </main>

    @include('public.partials.footer')

    <button
        type="button"
        class="back-to-top"
        data-back-to-top
        aria-label="Back to top"
        title="Back to top"
    >
        <span class="material-symbols-outlined" aria-hidden="true">arrow_upward</span>
    </button>

    <div class="toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>

    @include('public.partials.modal-root')
    @include('public.partials.drawer-root')

    @stack('scripts')
</body>
</html>
