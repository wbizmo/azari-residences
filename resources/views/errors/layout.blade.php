<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.azari-head-assets')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#173f35">

    <title>@yield('title', 'Something went wrong') | {{ config('app.name', 'Azari Residences') }}</title>

    <style>
        :root {
            --azari-green: #173f35;
            --azari-green-deep: #0e2b24;
            --azari-ivory: #f6f1e7;
            --azari-surface: rgba(255, 255, 255, 0.92);
            --azari-brass: #b59657;
            --azari-wine: #6e2937;
            --azari-text: #17211d;
            --azari-muted: #66736d;
            --azari-border: rgba(23, 63, 53, 0.14);
            --azari-shadow: 0 30px 90px rgba(14, 43, 36, 0.18);
            --azari-radius: 28px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--azari-ivory);
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--azari-text);
            background:
                radial-gradient(circle at 12% 10%, rgba(181, 150, 87, 0.20), transparent 30rem),
                radial-gradient(circle at 88% 88%, rgba(110, 41, 55, 0.14), transparent 28rem),
                linear-gradient(135deg, #fbf8f1 0%, #eee6d8 100%);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a,
        button {
            -webkit-tap-highlight-color: transparent;
        }

        .error-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 20px;
            position: relative;
            overflow: hidden;
        }

        .error-shell::before,
        .error-shell::after {
            content: "";
            position: fixed;
            width: 420px;
            height: 420px;
            border-radius: 999px;
            filter: blur(12px);
            pointer-events: none;
            opacity: .42;
        }

        .error-shell::before {
            top: -210px;
            right: -160px;
            background: rgba(23, 63, 53, .22);
        }

        .error-shell::after {
            bottom: -250px;
            left: -170px;
            background: rgba(181, 150, 87, .25);
        }

        .error-card {
            width: min(860px, 100%);
            position: relative;
            z-index: 1;
            overflow: hidden;
            border: 1px solid var(--azari-border);
            border-radius: var(--azari-radius);
            background: var(--azari-surface);
            box-shadow: var(--azari-shadow);
            backdrop-filter: blur(22px);
        }

        .error-card__accent {
            height: 7px;
            background: linear-gradient(90deg, var(--azari-green), var(--azari-brass), var(--azari-wine));
        }

        .error-card__body {
            padding: clamp(30px, 6vw, 74px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 42px;
            color: var(--azari-green);
            text-decoration: none;
        }

        .brand__mark {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            color: white;
            background: var(--azari-green);
            box-shadow: 0 12px 28px rgba(23, 63, 53, .22);
        }

        .brand__mark svg {
            width: 23px;
            height: 23px;
        }

        .brand__text {
            display: grid;
            gap: 2px;
        }

        .brand__name {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: .015em;
        }

        .brand__tagline {
            color: var(--azari-muted);
            font-size: .78rem;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .error-code {
            margin: 0 0 12px;
            color: var(--azari-brass);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(3.4rem, 10vw, 7.6rem);
            font-weight: 700;
            line-height: .88;
            letter-spacing: -.055em;
        }

        .error-eyebrow {
            margin: 0 0 12px;
            color: var(--azari-wine);
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .error-title {
            max-width: 680px;
            margin: 0;
            color: var(--azari-green-deep);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2rem, 5vw, 4.1rem);
            line-height: 1.04;
            letter-spacing: -.035em;
        }

        .error-message {
            max-width: 650px;
            margin: 22px 0 0;
            color: var(--azari-muted);
            font-size: clamp(1rem, 2vw, 1.12rem);
            line-height: 1.75;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 34px;
        }

        .error-button {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 18px;
            border: 1px solid transparent;
            border-radius: 14px;
            font: inherit;
            font-weight: 750;
            cursor: pointer;
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease;
        }

        .error-button:hover {
            transform: translateY(-2px);
        }

        .error-button:focus-visible {
            outline: 3px solid rgba(181, 150, 87, .48);
            outline-offset: 3px;
        }

        .error-button--primary {
            color: white;
            background: var(--azari-green);
            box-shadow: 0 13px 28px rgba(23, 63, 53, .22);
        }

        .error-button--primary:hover {
            background: var(--azari-green-deep);
        }

        .error-button--secondary {
            color: var(--azari-green);
            border-color: var(--azari-border);
            background: rgba(255, 255, 255, .72);
        }

        .error-button--secondary:hover {
            border-color: rgba(23, 63, 53, .28);
            background: #fff;
        }

        .error-button svg {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
        }

        .error-meta {
            margin-top: 38px;
            padding-top: 20px;
            border-top: 1px solid var(--azari-border);
            color: var(--azari-muted);
            font-size: .86rem;
            line-height: 1.55;
        }

        @media (max-width: 640px) {
            .error-shell {
                padding: 14px;
            }

            .error-card {
                border-radius: 22px;
            }

            .error-card__body {
                padding: 28px 22px 30px;
            }

            .brand {
                margin-bottom: 34px;
            }

            .error-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .error-button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>
<body>
    <main class="error-shell">
        <section class="error-card" aria-labelledby="error-title">
            <div class="error-card__accent"></div>

            <div class="error-card__body">
                <a class="brand" href="{{ url('/') }}" aria-label="Return to {{ config('app.name', 'Azari Residences') }} homepage">
                    <span class="brand__mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4 19V9.5L12 4l8 5.5V19M8 19v-5h8v5M7 10h.01M12 10h.01M17 10h.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="brand__text">
                        <span class="brand__name">{{ config('app.name', 'Azari Residences') }}</span>
                        <span class="brand__tagline">Luxury hospitality</span>
                    </span>
                </a>

                <p class="error-code">@yield('code', 'Error')</p>
                <p class="error-eyebrow">@yield('eyebrow', 'Request interrupted')</p>
                <h1 class="error-title" id="error-title">@yield('heading', 'Something went wrong')</h1>
                <div class="error-message">
                    @yield('message', 'We could not complete your request. Our team has been notified where applicable, and the issue will be reviewed.')
                </div>

                <div class="error-actions">
                    <button class="error-button error-button--primary" type="button" onclick="goBackSafely()">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Go back
                    </button>

                    <a class="error-button error-button--secondary" href="{{ url('/') }}">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 19V9.5L12 4l8 5.5V19M8 19v-5h8v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Return home
                    </a>
                </div>

                <div class="error-meta">
                    @yield('support', 'Please try again in a moment. If the issue continues, contact Azari Residences support and mention what you were trying to do.')
                </div>
            </div>
        </section>
    </main>

    <script>
        function goBackSafely() {
            if (window.history.length > 1 && document.referrer) {
                window.history.back();
                return;
            }

            window.location.assign(@json(url('/')));
        }
    </script>
</body>
</html>
