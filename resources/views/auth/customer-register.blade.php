<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#052058">
    <title>Create guest account | Resavar</title>
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
            <h1>Your stays in one secure space.</h1>
            <p>Create a guest account to manage bookings, guest details and stay information.</p>
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
                <p>Enter your details to continue with Resavar.</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="resavar-auth-form">
                @csrf
                <label>
                    <span>Full name</span>
                    <input name="name" value="{{ old('name') }}" autocomplete="name" required>
                </label>
                <label>
                    <span>Email address</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
                </label>
                <div class="resavar-auth-grid">
                    <label>
                        <span>Password</span>
                        <x-azari-password-input id="customer_password" name="password" autocomplete="new-password" label="password" />
                    </label>
                    <label>
                        <span>Confirm password</span>
                        <x-azari-password-input id="customer_password_confirmation" name="password_confirmation" autocomplete="new-password" label="password confirmation" />
                    </label>
                </div>
                <button type="submit" class="resavar-auth-submit">Create guest account</button>
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
