<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administrator Login | Azari Residences</title>
    <link rel="stylesheet" href="{{ asset('css/azari-admin-extension.css') }}">
</head>
<body class="az-admin-login-page">
<main class="az-login-shell">
    <section class="az-login-card">
        <a href="{{ route('home') }}" class="az-brand">Azari Residences</a>
        <p class="az-eyebrow">Secure administrator access</p>
        <h1>Welcome back</h1>
        <p class="az-muted">Sign in with your administrator username or email address.</p>

        @if ($errors->any())
            <div class="az-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('azari.admin.login.store') }}" class="az-form-stack">
            @csrf

            <label for="admin-login">Username or email
                <input id="admin-login" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
            </label>

            <label for="admin-password">Password
                <input id="admin-password" type="password" name="password" autocomplete="current-password" required>
            </label>

            <label class="az-check" for="admin-remember">
                <input id="admin-remember" type="checkbox" name="remember" value="1">
                <span>Keep me signed in</span>
            </label>

            <button type="submit" class="az-button">Sign in to administration</button>
        </form>
    </section>
</main>
</body>
</html>
