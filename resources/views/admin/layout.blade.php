@extends('admin.layouts.app')

@section('content')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/azari-favicon.png') }}">
    
                
            <title>{{ $title ?? 'Azari Residences' }}</title>
        
            
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>document.fonts.load('24px Material Symbols Outlined').then(()=>document.documentElement.classList.add('az-icons-ready')).catch(()=>document.documentElement.classList.add('az-icons-ready'));</script>
</head>
<body class="admin-shell">
    <header class="admin-topbar">
        <a href="{{ route('azari.admin.dashboard') }}"><strong>Azari Administration</strong></a>
        <nav>
            <a href="{{ route('azari.admin.properties.index') }}">Properties</a>
            <a href="{{ route('azari.admin.content.index') }}">Content</a>
            
        <a href="{{ route('azari.admin.cms.index') }}">CMS</a>
        <a href="{{ route('azari.admin.inventory.index') }}">Inventory</a>
<a href="{{ route('azari.admin.settings.edit') }}">Branding</a>
    
            @if(auth()->user()->staff_role === 'administrator')
                <a href="{{ route('azari.admin.staff.index') }}">Staff</a>
            @endif
            <form method="POST" action="{{ route('azari.admin.logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
        <a href="{{ route('azari.admin.service-requests.index') }}"><span class="material-symbols-outlined">room_service</span><span>Service requests</span></a></nav>
    </header>

    <main class="admin-main">
        @if(session('status'))
            <div class="admin-alert">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>




@endsection
