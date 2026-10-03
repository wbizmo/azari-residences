<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#052058">
    <title>Sign in | Resavar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="resavar-auth-body">
<main class="resavar-auth-layout">
    <section class="resavar-auth-brand-panel" aria-label="Resavar">
        <a href="{{ url('/') }}" class="resavar-auth-logo-link" aria-label="Resavar home">
            <img src="{{ asset('images/logo-dark.png') }}" alt="Resavar" class="resavar-auth-logo">
        </a>

        <div class="resavar-auth-brand-copy">
            <span>Exceptional stays, everywhere.</span>
            <h1>Welcome back.</h1>
            <p>Access your bookings, guest details and stay information securely.</p>
        </div>
    </section>

    <section class="resavar-auth-form-panel">
        <div class="resavar-auth-card">
            <a class="resavar-auth-back" href="{{ url('/') }}">
                <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
                <span>Back to homepage</span>
            </a>

            <div class="resavar-auth-heading">
                <span>Guest access</span>
                <h2>Sign in</h2>
                <p>Use the email and password linked to your Resavar account.</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="resavar-auth-form">
                @csrf

                <label for="email">
                    <span>Email address</span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
                    @error('email')<small>{{ $message }}</small>@enderror
                </label>

                <label for="password">
                    <span>Password</span>
                    <x-azari-password-input id="password" name="password" autocomplete="current-password" label="password" />
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>

                <div class="resavar-auth-row">
                    <label class="resavar-auth-check">
                        <input name="remember" type="checkbox">
                        <span>Remember me</span>
                    </label>

                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button type="submit" class="resavar-auth-submit">Sign in</button>
            </form>

            <p class="resavar-auth-switch">
                New to Resavar?
                <a href="{{ route('register') }}">Create an account</a>
            </p>
        </div>
    </section>
</main>
<x-azari-toasts />
</body>
</html>
