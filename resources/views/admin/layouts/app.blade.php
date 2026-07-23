<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Administration') | Azari Residences</title>
    <link rel="stylesheet" href="{{ asset('css/azari-admin-extension.css') }}">
</head>
<body class="az-admin-body">
<header class="az-admin-topbar">
    <button class="az-menu-button" type="button" data-admin-menu aria-label="Toggle navigation">☰</button>
    <a href="{{ route('azari.admin.dashboard') }}" class="az-admin-logo">Azari Admin</a>
    <div class="az-admin-profile">
        <div class="az-avatar">
            @if(auth()->user()->avatar_path)
                <img src="{{ asset('storage/'.auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}">
            @else
                {{ strtoupper(mb_substr(auth()->user()->name ?: auth()->user()->username, 0, 1)) }}
            @endif
        </div>
        <div class="az-profile-copy"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
        <form method="POST" action="{{ route('azari.admin.logout') }}">@csrf<button class="az-link-button">Sign out</button></form>
    </div>
</header>
<div class="az-admin-shell">
    <aside class="az-admin-sidebar" data-admin-sidebar>
        <nav>
            <a href="{{ route('azari.admin.dashboard') }}">Dashboard</a>
            <a href="{{ route('azari.admin.users.index') }}">Users</a>
            <a href="{{ route('azari.admin.settings.integrations') }}">Payments & integrations</a>
            <a href="{{ url('/azari-admin/cms') }}">CMS</a>
            <a href="{{ url('/azari-admin/inventory') }}">Inventory</a>
        </nav>
    </aside>
    <main class="az-admin-content">
        @if(session('status'))<div class="az-notice">{{ session('status') }}</div>@endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/azari-admin-extension.js') }}"></script>
</body>
</html>
