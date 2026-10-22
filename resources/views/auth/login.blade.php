<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign in — Resavar</title>
    @vite(['resources/css/app.css','resources/js/app.js'])



        <!-- AZARI PWA HEAD START -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#052058">

        <!-- AZARI PWA HEAD END -->

</head>

<body class="resavar-auth-body">
<a href="#resavar-auth-form" class="skip-link">Skip to authentication form</a>
<main class="auth resavar-auth-unified">
    <section class="visual">
        <img src="{{ asset('images/azari-guest-auth-suite.png') }}" alt="Luxury Resavar suite">
        <a href="{{ route('home') }}" class="brand resavar-auth-logo" aria-label="Resavar home">
            <x-brand-logo variant="hero" dark />
        </a>
        <div class="resavar-auth-visual-copy">
            <span class="resavar-auth-visual-kicker">WELCOME TO RESAVAR</span>
            <h2>Every great stay<br>has a <em>beginning.</em></h2>
            <p>Thoughtfully selected hotels and apartments. A simpler way to book, stay and feel at home.</p>
            <span class="resavar-auth-visual-foot">DISCOVER &middot; REQUEST &middot; STAY</span>
        </div>
    </section>

    <section class="panel" id="resavar-auth-form">
        <div class="wrap">

            <p class="kicker">Guest access</p>
            <h1>Welcome back.</h1>
            <p class="sub">Sign in to manage your reservations and stay details.</p>

            @if(session('status'))<p class="resavar-auth-status" role="status">{{ session('status') }}</p>@endif
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">
                    <label for="company_website">Company website</label>
                    <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
                    <label for="contact_fax">Fax</label>
                    <input id="contact_fax" name="contact_fax" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        autofocus
                        required
                    >
                    @error('email')
                        <p class="err" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <x-azari-password-input
                        id="password"
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
                        <input name="remember" type="checkbox" value="1" @checked(old("remember"))>
                        Remember me
                    </label>

                    @if(Route::has('password.request'))
                        <a class="link" href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button class="submit" type="submit">Sign in<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></button>
            </form>

            <p class="switch">
                New to Resavar?
                <a class="link" href="{{ route('register') }}">Create an account</a>
            </p>
        </div>
    </section>
</main>

    <!-- AZARI PWA RUNTIME START -->
    <script src="/pwa-install.js" defer></script>
    <!-- AZARI PWA RUNTIME END -->

</body>
</html>
