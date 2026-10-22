<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.material-symbols-preload')
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administration — Resavar</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="resavar-auth-body">
    <a href="#resavar-auth-form" class="skip-link">Skip to staff sign-in</a>
    <main class="auth resavar-auth-unified resavar-auth-staff">
        <section class="visual" aria-label="Resavar administration">
            <img src="{{ asset('images/azari-admin-reception.png') }}" alt="" loading="lazy">
            <a class="brand resavar-auth-logo" href="{{ route('home') }}" aria-label="Resavar homepage">
                <x-brand-logo variant="hero" dark />
            </a>
            <div class="resavar-auth-visual-copy">
                <span class="resavar-auth-visual-kicker">PRIVATE OPERATIONS</span>
                <h2>Considered stays. <em>Careful service.</em></h2>
                <p>Resavar staff workspace for verified accommodation operations. Access is restricted to authorised personnel.</p>
                <span class="resavar-auth-visual-foot">SECURE · ACCOUNTABLE · PROFESSIONAL</span>
            </div>
        </section>
        <section class="panel" id="resavar-auth-form" aria-labelledby="admin-auth-title">
            <div class="wrap">
                <p class="kicker">Authorised personnel</p>
                <h1 id="admin-auth-title">Staff sign in.</h1>
                <p class="sub">Enter your assigned username or email and password to access administration.</p>
                @if(session('status'))
                    <p class="resavar-auth-status" role="status">{{ session('status') }}</p>
                @endif
                <form method="POST" action="{{ route('azari.admin.login.store') }}">
                    @csrf
                    <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">
                        <label for="company_website">Company website</label>
                        <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
                        <label for="contact_fax">Fax</label>
                        <input id="contact_fax" name="contact_fax" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="admin-email">Username or email</label>
                        <input id="admin-email" name="login" type="text" value="{{ old('login') }}"
                            autocomplete="username" autofocus required
                            @if($errors->has('login')) aria-invalid="true" aria-describedby="staff-login-error" @endif>
                        @error('login')
                            <p class="err" role="alert" id="staff-login-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="admin-password">Password</label>
                        <x-azari-password-input
                            id="admin-password"
                            name="password"
                            autocomplete="current-password"
                            label="password"
                        />
                        @error('password')
                            <p class="err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="row">
                        <label class="check">
                            <input name="remember" type="checkbox" value="1" @checked(old('remember'))>
                            Remember this device
                        </label>
                    </div>
                    <button class="submit" type="submit">
                        Sign in securely
                        <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                    </button>
                </form>
                <p class="switch"><a class="link" href="{{ route('home') }}">Return to Resavar</a></p>
            </div>
        </section>
    </main>
</body>
</html>
