<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-admin-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | Azari Residences Administration</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="az-admin-body">
    <a class="az-skip-link" href="#az-admin-content">Skip to main content</a>

    <div class="az-admin-backdrop" data-sidebar-backdrop hidden></div>

    <div class="az-admin-app">
        @include('admin.partials.sidebar')

        <div class="az-admin-main">
            @include('admin.partials.topbar')

            <main id="az-admin-content" class="az-admin-content" tabindex="-1">
                @if (session('status'))
                    <div class="az-alert az-alert--success" role="status">
                        <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="az-alert az-alert--danger" role="alert">
                        <span class="material-symbols-outlined" aria-hidden="true">error</span>
                        <div>
                            <strong>Please review the highlighted information.</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
    <x-azari-feedback />
    
    <x-azari-toasts />
</body>
</html>



