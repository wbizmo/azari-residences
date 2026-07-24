<x-azari-auth-shell
    title="Reset password"
    eyebrow="Account recovery"
    heading="Choose a new password"
    description="Use a strong password that you have not used for this account before."
>
    <form method="POST" action="{{ route('password.store') }}" class="az-standalone-auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <label><span>Email address</span><input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email">@error('email')<small>{{ $message }}</small>@enderror</label>
        <label><span>New password</span><input type="password" name="password" required autocomplete="new-password">@error('password')<small>{{ $message }}</small>@enderror</label>
        <label><span>Confirm password</span><input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <button type="submit">Reset password</button>
    </form>
</x-azari-auth-shell>
