<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#052058">
    <title>Administration | Resavar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="resavar-auth-body resavar-admin-auth-body">
<main class="resavar-auth-layout">
    <section class="resavar-auth-brand-panel" aria-label="Resavar administration">
        <a href="{{ url('/') }}" class="resavar-auth-logo-link" aria-label="Resavar home">
            <img src="{{ asset('images/logo-dark.png') }}" alt="Resavar" class="resavar-auth-logo">
        </a>

        <div class="resavar-auth-brand-copy">
            <span>Private administration</span>
            <h1>Resavar operations.</h1>
            <p>Secure access for authorised staff managing reservations, properties and guest operations.</p>
        </div>
    </section>

    <section class="resavar-auth-form-panel">
        <div class="resavar-auth-card">
            <a class="resavar-auth-back" href="{{ url('/') }}">
                <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
                <span>Return to public site</span>
            </a>

            <div class="resavar-auth-heading">
                <span>Authorised personnel</span>
                <h2>Administration</h2>
                <p>Use your administrator credentials to continue.</p>
            </div>

            <form method="POST" action="{{ route('azari.admin.login.store') }}" class="resavar-auth-form">
                @csrf

                <label for="admin-email">
                    <span>Email address</span>
                    <input id="admin-email" name="login" type="text" value="{{ old('login') }}" autocomplete="username" autofocus required>
                    @error('login')<small>{{ $message }}</small>@enderror
                </label>

                <label for="admin-password">
                    <span>Password</span>
                    <input id="admin-password" name="password" type="password" autocomplete="current-password" required>
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>

                <label class="resavar-auth-check">
                    <input name="remember" type="checkbox" value="1" @checked(old('remember'))>
                    <span>Remember me</span>
                </label>

                <button type="submit" class="resavar-auth-submit">Sign in</button>
            </form>
        </div>
    </section>
</main>
<x-azari-toasts />
</body>
</html>
