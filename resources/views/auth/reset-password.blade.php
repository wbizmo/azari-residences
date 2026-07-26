<x-azari-auth-shell
    title="Reset password"
    eyebrow="Account recovery"
    heading="Choose a new password"
    description="Use a strong password that you have not used for this account before."
>
    <form method="POST" action="{{ route('password.store') }}" class="az-standalone-auth-form">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label>
            <span>Email address</span>
            <input
                type="email"
                name="email"
                value="{{ old('email', $request->email) }}"
                required
                autofocus
                autocomplete="email"
            >
            @error('email')
                <small>{{ $message }}</small>
            @enderror
        </label>

        <label>
            <span>New password</span>
            <x-azari-password-input
                id="reset_password"
                name="password"
                autocomplete="new-password"
                label="new password"
            />
            @error('password')
                <small>{{ $message }}</small>
            @enderror
        </label>

        <label>
            <span>Confirm password</span>
            <x-azari-password-input
                id="reset_password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                label="password confirmation"
            />
        </label>

        <button type="submit">Reset password</button>
    </form>
</x-azari-auth-shell>
