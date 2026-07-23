<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Something went wrong' }} | {{ config('app.name', 'Azari Residences') }}</title>
    <style>
        *{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;background:#f6f1e7;color:#17211d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:min(680px,100%);padding:42px;border:1px solid rgba(23,63,53,.14);border-radius:24px;background:#fff;box-shadow:0 24px 70px rgba(14,43,36,.15)}.code{margin:0;color:#b59657;font-family:Georgia,serif;font-size:4rem;font-weight:700}.title{margin:12px 0;color:#0e2b24;font-family:Georgia,serif;font-size:2.2rem}.message{color:#66736d;line-height:1.7}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:26px}.button{display:inline-flex;min-height:46px;align-items:center;justify-content:center;padding:11px 17px;border:1px solid rgba(23,63,53,.16);border-radius:13px;color:#173f35;background:#fff;font-weight:700;text-decoration:none;cursor:pointer}.primary{border-color:#173f35;color:#fff;background:#173f35}@media(max-width:560px){.card{padding:28px}.actions{display:grid}.button{width:100%}}
    </style>
</head>
<body>
    <main class="card">
        <p class="code">{{ $exception?->getStatusCode() ?? 'Error' }}</p>
        <h1 class="title">{{ $title ?? 'Something went wrong' }}</h1>
        <p class="message">{{ $message ?? 'We could not complete your request. The issue has been noted where logging is available, and it will be reviewed.' }}</p>
        <div class="actions">
            <button class="button primary" type="button" onclick="history.length > 1 ? history.back() : location.assign('/')">Go back</button>
            <a class="button" href="{{ url('/') }}">Return home</a>
        </div>
    </main>
</body>
</html>
