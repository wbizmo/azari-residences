<!doctype html>
<html lang="en">
<head>
    @include('partials.material-symbols-preload')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create guest account | Azari Hotels & Residences</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body class="az-auth-body">
<main class="az-auth-shell">
    <section class="az-auth-visual">
        <img
            src="{{ asset('images/azari-auth-lounge.png') }}"
            alt="Elegant private lounge at Azari Hotels & Residences"
        >
        <div>
            <h1>Your stays, preferences and reservations in one refined space.</h1>
        </div>
    </section>

    <section class="az-auth-panel">
        <a class="az-auth-home-link" href="{{ url('/') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M19 12H5"/>
                <path d="m11 18-6-6 6-6"/>
            </svg>
            <span>Back to homepage</span>
        </a>

        <h2>Create your guest account</h2>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <label>
                Full name
                <input name="name" value="{{ old('name') }}" autocomplete="name" required>
            </label>

            <label>
                Email
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                >
            </label>

            <label>
                Password
                <x-azari-password-input
                    id="customer_password"
                    name="password"
                    autocomplete="new-password"
                    label="password"
                />
            </label>

            <label>
                Confirm password
                <x-azari-password-input
                    id="customer_password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    label="password confirmation"
                />
            </label>

            <button type="submit">Create guest account</button>
        </form>
    </section>
</main>
</body>
</html>
