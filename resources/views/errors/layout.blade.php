<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Error') — {{ config('app.name', 'Resavar') }}</title>
    <meta name="robots" content="noindex, nofollow, noarchive">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #052058;
            color: #FFFFFF;
            font-family: Arial, Helvetica, sans-serif;
        }

        .error {
            width: 100%;
            max-width: 560px;
            text-align: center;
        }

        .error-code {
            margin: 0;
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.18em;
        }

        h1 {
            margin: 14px 0 10px;
            font-size: 28px;
            font-weight: 600;
            line-height: 1.25;
        }

        .message {
            margin: 0 auto;
            max-width: 470px;
            color: rgba(255, 255, 255, 0.86);
            font-size: 14px;
            line-height: 1.7;
        }

        .links {
            margin-top: 24px;
            font-size: 13px;
        }

        .links a {
            color: #FFFFFF;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .links span {
            margin: 0 8px;
            color: rgba(255, 255, 255, 0.62);
        }

        h1 {
            color: #FFFFFF;
        }
    </style>
</head>
<body>
    <main class="error">
        <p class="error-code">@yield('code')</p>

        <h1>@yield('title', 'Something went wrong')</h1>

        <p class="message">
            @yield('message')
        </p>

        <div class="links">
            <a href="{{ url('/') }}">Return home</a>
            <span>·</span>
            <a href="javascript:history.back()">Go back</a>
        </div>
    </main>
</body>
</html>
