<x-azari-auth-shell
    title="Verify email"
    eyebrow="Email verification"
    heading="Check your inbox"
    description="Open the verification link sent to your email address before continuing. Unverified accounts are closed after 7 days and permanently removed after 30 days."
>
    <div class="az-user-alert az-user-alert--warning" style="margin-bottom:16px">
        <strong>Verification policy</strong>
        <p>Verify this email within 7 days. If you do not, access to the account will be closed. Unverified accounts that remain inactive for 30 days are permanently scrubbed from the platform, except records that must be retained for transactional integrity.</p>
    </div>

    <div class="az-standalone-auth-actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="secondary">Sign out</button>
        </form>
    </div>
</x-azari-auth-shell>
