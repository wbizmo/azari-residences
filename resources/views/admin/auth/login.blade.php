<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in | {{ config('app.name', 'Azari Residences') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page auth-page--admin">
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="admin-login-title">
            <header class="auth-card__header">
                <h1 id="admin-login-title">Sign in</h1>
            </header>

            <form method="POST" action="{{ route('azari.admin.login.store') }}" class="azari-form" data-working-form>
                @csrf

                <div class="field-group">
                    <label for="admin-login">Email or username</label>
                    <input id="admin-login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" required autofocus>
                    @error('login')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field-group">
                    <label for="admin-password">Password</label>
                    <input id="admin-password" name="password" type="password" autocomplete="current-password" required>
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <label class="toggle-control" for="admin-remember">
                    <input id="admin-remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                    <span class="toggle-control__track" aria-hidden="true"><span class="toggle-control__thumb"></span></span>
                    <span class="toggle-control__label">Remember me</span>
                </label>

                <button type="submit" class="button button--primary button--block" data-working-text="Signing in…">
                    <span data-button-label>Sign in</span>
                </button>
            </form>
        </section>
    </main>
    @includeIf('components.azari-flash-toasts')
</body>
</html>
