<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-admin-theme="light">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | Azari Residences Administration</title>

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include("partials.azari-shell-layout")

    @stack('head')
</head>

<body class="az-admin-body">
    <a class="az-skip-link" href="#az-admin-content">Skip to main content</a>

    <div
        class="az-admin-backdrop"
        data-sidebar-backdrop
        hidden
    ></div>

    <div class="az-admin-app">
        @include('admin.partials.sidebar')

        <div class="az-admin-main">
            @include('admin.partials.topbar')

            <main
                id="az-admin-content"
                class="az-admin-content"
                tabindex="-1"
            >
                @foreach (['status' => 'success', 'success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $flashKey => $flashTone)
                    @if (session($flashKey))
                        <div
                            class="az-alert az-alert--{{ $flashTone }}"
                            role="status"
                        >
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >
                                {{ $flashTone === 'danger' ? 'error' : ($flashTone === 'warning' ? 'warning' : 'check_circle') }}
                            </span>

                            <span>{{ session($flashKey) }}</span>
                        </div>
                    @endif
                @endforeach

                @if ($errors->any())
                    <div
                        class="az-alert az-alert--danger"
                        role="alert"
                    >
                        <span
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >
                            error
                        </span>

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