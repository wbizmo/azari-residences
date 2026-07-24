<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in | {{ config('app.name', 'Azari Residences') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="az-admin-auth-page">
<main class="az-admin-auth-shell"><section class="az-admin-auth-card" aria-labelledby="admin-login-title">
    <h1 id="admin-login-title">Sign in</h1>
    <form method="POST" action="{{ route('azari.admin.login.store') }}" class="az-form" data-az-working-form>
        @csrf
        <div class="az-field"><label for="admin-login">Email or username</label><input id="admin-login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" required autofocus>@error('login')<p class="az-field-error">{{ $message }}</p>@enderror</div>
        <div class="az-field"><label for="admin-password">Password</label><input id="admin-password" name="password" type="password" autocomplete="current-password" required>@error('password')<p class="az-field-error">{{ $message }}</p>@enderror</div>
        <label class="az-switch" for="admin-remember"><input id="admin-remember" name="remember" type="checkbox" value="1" @checked(old('remember'))><span class="az-switch__track" aria-hidden="true"><span class="az-switch__thumb"></span></span><span>Remember me</span></label>
        <button type="submit" class="button button--primary button--block" data-az-working-text="Signing in…"><span data-az-button-label>Sign in</span></button>
    </form>
</section></main>
@includeIf
</body></html>
