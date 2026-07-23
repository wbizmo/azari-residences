<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
                
            <title>{{ $title ?? 'Azari Residences' }}</title>
        
            
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-login-page">
    <main class="admin-login-card">
        <x-brand-logo variant="header" />
        <h1>Staff administration</h1>
        <p>Authorised personnel only.</p>

        <form method="POST" action="{{ route('azari.admin.login.store') }}">
            @csrf
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<small>{{ $message }}</small>@enderror

            <label>Password</label>
            <input type="password" name="password" required autocomplete="current-password">
            @error('password')<small>{{ $message }}</small>@enderror

            <label class="admin-checkbox">
                <input type="checkbox" name="remember" value="1">
                Keep me signed in
            </label>

            <button type="submit" class="button button-primary button-block">Secure login</button>
        </form>
    </main>
</body>
</html>
