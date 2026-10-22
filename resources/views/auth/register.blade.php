<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create account — Resavar</title>
    @vite(['resources/css/app.css','resources/js/app.js'])


</head>

<body class="resavar-auth-body">
<a href="#resavar-auth-form" class="skip-link">Skip to authentication form</a>
<main class="auth resavar-auth-unified">
    <section class="visual">
        <img src="{{ asset('images/azari-guest-auth-suite.png') }}" alt="Resavar accommodation interior">
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

            <p class="kicker">Guest registration</p>
            <h1>Create your account.</h1>
            <p class="sub">Keep reservations, guest details and stay information in one place.</p>

            <p class="resavar-auth-guidance">Choose a unique password with at least 12 characters, uppercase and lowercase letters, and numbers. You will verify your email before booking.</p>
            <form method="POST" action="{{ route('register') }}">
                @csrf
                <input type="hidden" name="_auth_form_token" value="{{ $authFormToken }}">
                <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">
                    <label for="company_website">Company website</label>
                    <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
                    <label for="contact_fax">Fax</label>
                    <input id="contact_fax" name="contact_fax" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="fields">
                    <div class="field wide">
                        <label for="name">Full name</label>
                        <input
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            autocomplete="name"
                            autofocus
                            required
                        >
                        @error('name')
                            <p class="err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field wide">
                        <label for="email">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="username"
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
                            autocomplete="new-password"
                            label="password"
                        />
                        @error('password')
                            <p class="err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm password</label>
                        <x-azari-password-input
                            id="password_confirmation"
                            name="password_confirmation"
                            autocomplete="new-password"
                            label="password confirmation"
                        />
                    </div>
                </div>

                <button class="submit" type="submit">Create account<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></button>
            </form>

            <p class="switch">
                Already registered?
                <a class="link" href="{{ route('login') }}">Sign in</a>
            </p>
        </div>
    </section>
</main>
</body>
</html>
