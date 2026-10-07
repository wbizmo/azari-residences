<x-azari-auth-shell
    title="Forgot password"
    eyebrow="Account recovery"
    heading="Reset your password"
    description="Enter the email attached to your account and we will send a secure reset link."
>
    <form method="POST" action="{{ route('password.email') }}" class="az-standalone-auth-form">
        @csrf
        <input type="hidden" name="_auth_form_token" value="{{ $authFormToken }}">
        <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">
            <label for="company_website">Company website</label>
            <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
            <label for="contact_fax">Fax</label>
            <input id="contact_fax" name="contact_fax" type="text" tabindex="-1" autocomplete="off">
        </div>

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
