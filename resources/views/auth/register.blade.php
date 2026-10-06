<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create account | Resavar</title>
    @vite(['resources/css/app.css','resources/js/app.js'])

    <style>
        :root{--ink:#052058;--muted:#052058;--line:rgba(5,32,88,.14);--paper:#FFFFFF;--gold:#052058}
        *{box-sizing:border-box}
        html,body{margin:0;min-height:100%}
        body{font-family:Inter,system-ui,sans-serif;background:var(--paper);color:var(--ink)}
        .auth{min-height:100dvh;display:grid;grid-template-columns:minmax(0,1.08fr) minmax(450px,.92fr);background:#FFFFFF}
        .visual{position:relative;overflow:hidden;background:#052058}
        .visual img{width:100%;height:100%;object-fit:cover;display:block}
        .visual:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(8,6,4,.08),rgba(8,6,4,.48))}
        .brand{position:absolute;z-index:2;top:44px;left:54px;color:#FFFFFF;text-decoration:none;text-transform:uppercase;letter-spacing:.18em;font-size:13px;font-weight:700}
        .panel{display:grid;place-items:center;padding:clamp(24px,4vw,66px);background:radial-gradient(circle at 100% 0%,rgba(5,32,88,.08),transparent 36%),#FFFFFF}
        .wrap{width:min(100%,480px)}
        .kicker{margin:0 0 9px;color:var(--gold);font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
        h1{margin:0;font:500 clamp(34px,4vw,50px)/1 Georgia,serif;letter-spacing:-.035em}
        .sub{margin:13px 0 22px;color:var(--muted);line-height:1.55;font-size:14px}
        .fields{display:grid;grid-template-columns:1fr 1fr;gap:13px}
        .wide{grid-column:1/-1}
        .field label{display:block;margin-bottom:7px;font-size:12px;font-weight:700}
        .field input{width:100%;height:47px;border:1px solid var(--line);border-radius:13px;padding:0 15px;background:#FFFFFF;font:inherit;outline:none}
        .field input:focus{border-color:#052058;box-shadow:0 0 0 4px rgba(5,32,88,.10)}
        .err{margin:6px 0 0;color:#000000;font-size:12px}
        .submit{width:100%;height:50px;margin-top:17px;border:0;border-radius:14px;background:var(--ink);color:#FFFFFF;font-weight:800;cursor:pointer}
        .switch{margin:17px 0 0;text-align:center;color:var(--muted);font-size:13px}
        .link{color:var(--gold);text-decoration:none;font-weight:700}
        @media(min-width:768px){html,body{height:100%;overflow:hidden}.auth{height:100dvh;overflow:hidden}}
        @media(max-width:767px){.auth{display:block}.visual{display:none}.panel{min-height:100dvh;padding:22px 20px}.fields{grid-template-columns:1fr;gap:11px}.wide{grid-column:auto}h1{font-size:42px}}
        @media(max-height:760px) and (min-width:768px){.panel{padding-block:16px}.sub{margin-bottom:14px}.fields{gap:9px 11px}.field input{height:42px}.submit{height:45px;margin-top:12px}.switch{margin-top:11px}}
    </style>
</head>

<body>
<main class="auth">
    <section class="visual">
        <img src="{{ asset('images/azari-guest-auth-suite.png') }}" alt="Luxury Azari suite">
        <a class="brand" href="{{ url('/') }}">Resavar</a>
    </section>

    <section class="panel">
        <div class="wrap">

            <p class="kicker">Guest registration</p>
            <h1>Create your account.</h1>
            <p class="sub">Keep reservations, guest details and stay information in one place.</p>

            <form method="POST" action="{{ route('register') }}">
                @csrf

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
                            <p class="err">{{ $message }}</p>
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
                            <p class="err">{{ $message }}</p>
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
                            <p class="err">{{ $message }}</p>
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

                <button class="submit" type="submit">Create account</button>
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
