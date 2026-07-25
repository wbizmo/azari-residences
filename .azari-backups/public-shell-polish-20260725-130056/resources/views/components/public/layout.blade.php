@props([
    'title' => 'Azari Residences',
    'description' => null,
    'bodyClass' => '',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/azari-favicon.png') }}">

    
                
            
            
        <title>{{ $title ?? 'Azari Residences' }}</title>
    
        
        
            
    @include('partials.azari-head-assets')
    <meta name="description" content="{{ $description ?? 'Private, fully serviced residences in Lagos with direct booking and dedicated guest support.' }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,400&family=IBM+Plex+Mono:wght@400;500&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    <script>document.fonts.load('24px Material Symbols Outlined').then(()=>document.documentElement.classList.add('az-icons-ready')).catch(()=>document.documentElement.classList.add('az-icons-ready'));</script>
</head>

<body class="public-site {{ request()->routeIs('home') ? 'home-page azari-home-page' : 'inner-page azari-inner-page azari-solid-header' }} {{ $bodyClass ?? '' }}">

    <!-- AZARI_PRELOADER_START -->
    
<div
        class="azari-preloader"
        data-public-preloader
        role="status"
        aria-label="Loading"
    >
        <span class="azari-preloader__spinner" aria-hidden="true"></span>
</div>
    
    <!-- AZARI_PRELOADER_END -->
    
    
            

            <a class="skip-link"
         href="#main-content">Skip to main content</a>

    @include('public.partials.navigation')

    <main id="main-content">
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
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
    @include('public.partials.drawer-root')

    @stack('scripts')
    <x-azari-feedback />
    
</body>
</html>
