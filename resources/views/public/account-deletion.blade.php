@extends('layouts.public')

@section('title', 'Account & Data Deletion | Resavar')
@section('description', 'Request deletion of your Resavar account and associated personal data.')

@section('content')
<section style="min-height:70vh;background:#f8f5ef;padding:148px 20px 72px;">
    <div style="max-width:760px;margin:0 auto;background:#fffdf9;border:1px solid #e7e1d7;border-radius:24px;padding:36px;box-shadow:0 18px 50px rgba(12,43,36,.08);">
        <p style="margin:0 0 10px;color:#b58a4a;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:12px;">Privacy & account controls</p>
        <h1 style="margin:0;color:#0c2b24;font-size:38px;line-height:1.15;">Account & Data Deletion</h1>
        <p style="margin:18px 0 0;color:#6e7a75;font-size:16px;line-height:1.75;">Use this page to request deletion of your Resavar account and associated personal data. You can submit this request without installing or signing in to the app.</p>

        @if (session('account_deletion_reference'))
            <div style="margin-top:28px;padding:18px 20px;border-radius:16px;background:#dce9e4;color:#0c2b24;">
                <strong>Request received.</strong><br>
                Your reference is {{ session('account_deletion_reference') }}. We will verify account ownership before processing the request.
            </div>
        @else
            @if ($errors->any())
                <div style="margin-top:28px;padding:18px 20px;border-radius:16px;background:#f7e8ec;color:#6f263b;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('account-deletion.store') }}" style="margin-top:30px;display:grid;gap:18px;">
                @csrf
                <div>
                    <label for="name" style="display:block;margin-bottom:7px;color:#18231f;font-weight:700;">Name <span style="color:#6e7a75;font-weight:400;">(optional)</span></label>
                    <input id="name" name="name" type="text" maxlength="120" value="{{ old('name') }}" autocomplete="name" style="width:100%;box-sizing:border-box;border:1px solid #e7e1d7;border-radius:14px;padding:14px 16px;background:#fff;color:#18231f;">
                </div>

                <div>
                    <label for="email" style="display:block;margin-bottom:7px;color:#18231f;font-weight:700;">Email address used for your Azari account</label>
                    <input id="email" name="email" type="email" maxlength="254" required value="{{ old('email', $accountEmail) }}" autocomplete="email" style="width:100%;box-sizing:border-box;border:1px solid #e7e1d7;border-radius:14px;padding:14px 16px;background:#fff;color:#18231f;">
                </div>

                <div style="position:absolute;left:-9999px;" aria-hidden="true">
                    <label for="company_website">Website</label>
                    <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <label style="display:flex;gap:12px;align-items:flex-start;color:#38463f;line-height:1.55;">
                    <input type="checkbox" name="confirm" value="1" required style="margin-top:4px;">
                    <span>I confirm that I am requesting deletion of my Azari account and associated personal data.</span>
                </label>

                <button type="submit" style="border:0;border-radius:14px;padding:15px 20px;background:#0c2b24;color:#fff;font-weight:800;cursor:pointer;">Submit deletion request</button>
            </form>
        @endif

        <div style="margin-top:34px;padding-top:28px;border-top:1px solid #e7e1d7;">
            <h2 style="margin:0 0 12px;color:#123a30;font-size:21px;">What happens after you submit</h2>
            <p style="margin:0 0 12px;color:#6e7a75;line-height:1.7;">Azari will verify ownership of the account before processing deletion. Account and personal data that is not required for legitimate business, accounting, fraud-prevention, legal, or regulatory purposes will be deleted or anonymized.</p>
            <p style="margin:0;color:#6e7a75;line-height:1.7;">Some booking, payment, tax, security, or compliance records may need to be retained for a legally required period. Identity verification services may be provided by third-party processors such as Dojah; applicable deletion requests will be handled in line with Azari's instructions and retention obligations.</p>
        </div>

        @auth
            <div style="margin-top:24px;padding:18px 20px;border-radius:16px;background:#f8f5ef;color:#38463f;">
                You are currently signed in. You may also delete your account directly from your <a href="{{ route('profile.edit') }}" style="color:#286553;font-weight:700;">Profile</a>.
            </div>
        @endauth
    </div>
</section>
@endsection
