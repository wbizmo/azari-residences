<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#052058">
    <title>Create account | Resavar</title>
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
            <h1>Create your Resavar account.</h1>
            <p>Keep bookings, guest details and stay information together in one secure place.</p>
        </div>
    </section>

    <section class="resavar-auth-form-panel">
        <div class="resavar-auth-card">
            <a class="resavar-auth-back" href="{{ url('/') }}">
                <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
                <span>Back to homepage</span>
            </a>

            <div class="resavar-auth-heading">
                <span>Guest registration</span>
                <h2>Create account</h2>
                <p>Enter your details to start managing stays with Resavar.</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="resavar-auth-form">
                @csrf

                <label for="name">
                    <span>Full name</span>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" autofocus required>
                    @error('name')<small>{{ $message }}</small>@enderror
                </label>

                <label for="email">
                    <span>Email address</span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
                    @error('email')<small>{{ $message }}</small>@enderror
                </label>

                <div class="resavar-auth-grid">
                    <label for="password">
                        <span>Password</span>
                        <x-azari-password-input id="password" name="password" autocomplete="new-password" label="password" />
                        @error('password')<small>{{ $message }}</small>@enderror
                    </label>

                    <label for="password_confirmation">
                        <span>Confirm password</span>
                        <x-azari-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" label="password confirmation" />
                    </label>
                </div>

                <button type="submit" class="resavar-auth-submit">Create account</button>
            </form>

            <p class="resavar-auth-switch">
                Already registered?
                <a href="{{ route('login') }}">Sign in</a>
            </p>
        </div>
    </section>
</main>
<x-azari-toasts />
</body>
</html>
