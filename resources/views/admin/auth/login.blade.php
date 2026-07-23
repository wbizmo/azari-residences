<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrator Login | Azari Residences</title>
    <link rel="stylesheet" href="{{ asset('css/azari-admin-extension.css') }}">
</head>
<body class="az-admin-login-page">
<main class="az-login-shell">
    <section class="az-login-card">
        <a href="{{ url('/') }}" class="az-brand">Azari Residences</a>
        <p class="az-eyebrow">Secure administrator access</p>
        <h1>Welcome back</h1>
        <p class="az-muted">Sign in with your administrator username or email address.</p>

        <form method="POST" action="{{ route('azari.admin.login.store') }}" class="az-form-stack">
            @csrf
            <label>Username or email
                <input name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
            </label>
            @error('login')<div class="az-error">{{ $message }}</div>@enderror
            <label>Password
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <label class="az-check"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label>
            <button type="submit" class="az-button">Sign in to administration</button>
        </form>
    </section>
</main>
</body>
</html>
