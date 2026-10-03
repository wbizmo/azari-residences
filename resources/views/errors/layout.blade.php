<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('code') | {{ config('app.name', 'Reserva') }}</title>

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
            color: #052058;
            font-family: Arial, Helvetica, sans-serif;
        }

        .error {
            width: 100%;
            max-width: 560px;
            text-align: center;
        }

        .error-code {
            margin: 0;
            color: #052058;
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
            color: #052058;
            font-size: 14px;
            line-height: 1.7;
        }

        .links {
            margin-top: 24px;
            font-size: 13px;
        }

        .links a {
            color: #052058;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .links span {
            margin: 0 8px;
            color: #052058;
        }
    </style>
</head>
<body>
    <main class="error">
        <p class="error-code">@yield('code')</p>

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
