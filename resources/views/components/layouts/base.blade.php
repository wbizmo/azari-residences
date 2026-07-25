<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    
                
            
    
    
    
            <title>{{ $title ?? 'Azari Residences' }}</title>
        



        
            

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <div class="azari-page">
        {{ $slot }}
    </div>

    @stack('scripts')
    <x-azari-toasts />
</body>
</html>
