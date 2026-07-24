<x-azari-auth-shell
    title="Confirm password"
    eyebrow="Secure access"
    heading="Confirm your password"
    description="For your security, confirm your password before continuing."
>
    <form method="POST" action="{{ route('password.confirm') }}" class="az-standalone-auth-form">
        @csrf
        <label><span>Password</span><input type="password" name="password" required autofocus autocomplete="current-password">@error('password')<small>{{ $message }}</small>@enderror</label>
        <button type="submit">Continue securely</button>
    </form>
</x-azari-auth-shell>
