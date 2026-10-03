<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign in | Resavar</title>
    @vite(['resources/css/app.css','resources/js/app.js'])

    <style>
        :root{--ink:#17140f;--muted:#746d62;--line:rgba(23,20,15,.14);--paper:#f8f5ef;--gold:#8a6232}
        *{box-sizing:border-box}
        html,body{margin:0;min-height:100%}
        body{font-family:Inter,system-ui,sans-serif;background:var(--paper);color:var(--ink)}
        .auth{min-height:100dvh;display:grid;grid-template-columns:minmax(0,1.08fr) minmax(430px,.92fr);background:#FFFFFF}
        .visual{position:relative;overflow:hidden;background:#201b15}
        .visual img{width:100%;height:100%;object-fit:cover;display:block}
        .visual:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(8,6,4,.08),rgba(8,6,4,.48))}
        .brand{position:absolute;z-index:2;top:44px;left:54px;color:#fff;text-decoration:none;text-transform:uppercase;letter-spacing:.18em;font-size:13px;font-weight:700}
        .panel{display:grid;place-items:center;padding:clamp(30px,5vw,76px);background:radial-gradient(circle at 100% 0%,rgba(168,121,60,.11),transparent 36%),#FFFFFF}
        .wrap{width:min(100%,460px)}
        .kicker{margin:0 0 10px;color:var(--gold);font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
        h1{margin:0;font:500 clamp(36px,4vw,52px)/1 Georgia,serif;letter-spacing:-.035em}
        .sub{margin:15px 0 28px;color:var(--muted);line-height:1.6}
        .field{margin-bottom:17px}
        .field label{display:block;margin-bottom:8px;font-size:13px;font-weight:700}
        .field input{width:100%;height:52px;border:1px solid var(--line);border-radius:14px;padding:0 16px;background:#fff;font:inherit;outline:none}
        .field input:focus{border-color:#a8793c;box-shadow:0 0 0 4px rgba(168,121,60,.12)}
        .err{margin:7px 0 0;color:#a13f35;font-size:12px}
        .row{display:flex;justify-content:space-between;align-items:center;gap:16px;margin:3px 0 21px;font-size:13px}
        .check{display:flex;align-items:center;gap:8px;color:var(--muted)}
        .link{color:var(--gold);text-decoration:none;font-weight:700}
        .submit{width:100%;height:54px;border:0;border-radius:14px;background:var(--ink);color:#fff;font-weight:800;cursor:pointer}
        .switch{margin:22px 0 0;text-align:center;color:var(--muted);font-size:13px}
        @media(min-width:768px){html,body{height:100%;overflow:hidden}.auth{height:100dvh;overflow:hidden}}
        @media(max-width:767px){.auth{display:block}.visual{display:none}.panel{min-height:100dvh;padding:25px 21px}h1{font-size:44px}}
        @media(max-height:700px) and (min-width:768px){.panel{padding-block:20px}.sub{margin-bottom:18px}.field{margin-bottom:12px}.field input{height:45px}.submit{height:47px}}
    </style>

        <!-- AZARI PWA HEAD START -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#052058">
        <link rel="apple-touch-icon" href="/public/images/logo-light.png">
        <!-- AZARI PWA HEAD END -->

</head>

<body>
<main class="auth">
    <section class="visual">
        <img src="{{ asset('images/azari-guest-auth-suite.png') }}" alt="Luxury Resavar suite">
        <a class="brand" href="{{ url('/') }}">Resavar</a>
    </section>

    <section class="panel">
        <div class="wrap">
            <a class="az-auth-home-link" href="{{ url('/') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M19 12H5"/>
                    <path d="m11 18-6-6 6-6"/>
                </svg>
                <span>Back to homepage</span>
            </a>

            <p class="kicker">Guest access</p>
            <h1>Welcome back.</h1>
            <p class="sub">Sign in to manage your reservations and stay details.</p>

            <form method="POST" action="{{ route('login') }}">
                @csrf

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
                        <p class="err">{{ $message }}</p>
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
                        <p class="err">{{ $message }}</p>
                    @enderror
                </div>

                <div class="row">
                    <label class="check">
                        <input name="remember" type="checkbox">
                        Remember me
                    </label>

                    @if(Route::has('password.request'))
                        <a class="link" href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button class="submit" type="submit">Sign in</button>
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
