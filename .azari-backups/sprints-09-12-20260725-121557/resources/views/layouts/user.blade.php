<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Guest area') | {{ $siteSettings['site_name'] ?? 'Azari Residences' }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @stack('head')
</head>
<body class="az-user-body">
<div class="az-user-shell">
    <aside class="az-user-sidebar" aria-label="Guest account navigation">
        <div class="az-user-brand-area"><x-brand-logo variant="guest-sidebar" :dark="true" /></div>
        <div class="az-user-sidebar-scroll">@include('user.partials.navigation')</div>
    </aside>

    <div class="az-user-mobile-backdrop" data-user-drawer-close></div>
    <aside class="az-user-mobile-drawer" aria-label="Mobile guest navigation">
        <div class="az-user-drawer-header"><div><x-brand-logo variant="guest-drawer" :dark="true" /></div><button class="az-user-drawer-close" type="button" data-user-drawer-close aria-label="Close navigation"><span class="material-symbols-outlined">close</span></button></div>
        @include('user.partials.navigation')
    </aside>

    <main class="az-user-main">
        <header class="az-user-topbar">
            <div class="az-user-topbar-left">
                <button class="az-user-menu-button" type="button" data-user-drawer-open aria-expanded="false" aria-label="Open guest navigation"><span class="material-symbols-outlined">menu</span></button>
                <div><div class="az-user-kicker">@yield('kicker','Guest area')</div><h1 class="az-user-page-title">@yield('page_title','Your Azari stay')</h1></div>
            </div>
            <a class="az-user-profile-chip" href="{{ route('user.profile.edit') }}" aria-label="Open profile">
                <span class="az-user-avatar">@if(auth()->user()->profile_photo_path)<img src="{{ \Storage::url(auth()->user()->profile_photo_path) }}" alt="">@else{{ collect(explode(' ',auth()->user()->name))->map(fn($n)=>mb_substr($n,0,1))->take(2)->implode('') }}@endif</span>
                <span class="az-user-profile-meta"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->currentIdentity()->exists() ? 'Identity on file' : 'Guest account' }}</span></span>
            </a>
        </header>
        <div class="az-user-content">
            @if(session('success'))<div class="az-user-flash az-user-flash--success"><span class="material-symbols-outlined">check_circle</span><span>{{ session('success') }}</span></div>@endif
            @if(session('warning'))<div class="az-user-flash az-user-flash--warning"><span class="material-symbols-outlined">warning</span><span>{{ session('warning') }}</span></div>@endif
            @if(session('error'))<div class="az-user-flash az-user-flash--error"><span class="material-symbols-outlined">error</span><span>{{ session('error') }}</span></div>@endif
            @if($errors->any())<div class="az-user-flash az-user-flash--error"><span class="material-symbols-outlined">error</span><div><strong>Please review the form.</strong><ul class="az-user-errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
            @yield('content')
        </div>
    </main>

    <nav class="az-user-mobile-bottom" aria-label="Guest quick navigation">
        <a class="{{ request()->routeIs('user.dashboard')?'is-active':'' }}" href="{{ route('user.dashboard') }}"><span class="material-symbols-outlined">space_dashboard</span><span>Home</span></a>
        <a class="{{ request()->routeIs('user.bookings.*')?'is-active':'' }}" href="{{ route('user.bookings.index') }}"><span class="material-symbols-outlined">calendar_month</span><span>Bookings</span></a>
        <a class="{{ request()->routeIs('user.payments.*')?'is-active':'' }}" href="{{ route('user.payments.index') }}"><span class="material-symbols-outlined">account_balance_wallet</span><span>Payments</span></a>
        <a class="{{ request()->routeIs('user.identity.*')?'is-active':'' }}" href="{{ route('user.identity.index') }}"><span class="material-symbols-outlined">badge</span><span>Identity</span></a>
        <a class="{{ request()->routeIs('user.profile.*')?'is-active':'' }}" href="{{ route('user.profile.edit') }}"><span class="material-symbols-outlined">person</span><span>Profile</span></a>
    </nav>
</div>
</body>
</html>
