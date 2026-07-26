<x-azari-auth-shell
    title="Forgot password"
    eyebrow="Account recovery"
    heading="Reset your password"
    description="Enter the email attached to your account and we will send a secure reset link."
>
    <form method="POST" action="{{ route('password.email') }}" class="az-standalone-auth-form">
        @csrf

        <label>
            <span>Email address</span>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
            >
            @error('email')
                <small>{{ $message }}</small>
            @enderror
        </label>

        <button type="submit">Send reset link</button>
        <a href="{{ route('login') }}">Return to sign in</a>
    </form>
</x-azari-auth-shell>
