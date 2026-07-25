@extends('admin.layouts.app')
@section('title', 'Payments & Integrations')
@section('content')
<div class="az-page-heading">
    <div>
        <p class="az-eyebrow">System configuration</p>
        <h1>Payments, messaging and documents</h1>
        <p>Provider credentials are loaded only from the server environment. Administrators can see safe status information but cannot view or edit secrets.</p>
    </div>
    @if(Route::has('azari.admin.payments.providers'))
        <a class="az-button az-button-secondary" href="{{ route('azari.admin.payments.providers') }}">Provider monitoring</a>
    @endif
</div>

@if(($providers ?? collect())->isNotEmpty())
    <section class="az-s78-provider-grid" aria-label="Payment provider status">
        @foreach($providers as $provider)
            <article class="az-s78-provider-card">
                <div>
                    <p class="az-eyebrow">{{ strtoupper($provider->provider) }}</p>
                    <h2>{{ ucfirst($provider->connection_status ?? 'not tested') }}</h2>
                </div>
                <dl>
                    <div><dt>Enabled</dt><dd>{{ $provider->enabled ? 'Yes' : 'No' }}</dd></div>
                    <div><dt>Mode</dt><dd>{{ ucfirst($provider->mode ?: 'Not configured') }}</dd></div>
                    <div><dt>Last webhook</dt><dd>{{ $provider->last_webhook_at?->format('j M Y, g:i A') ?? 'None received' }}</dd></div>
                    <div><dt>Last payment</dt><dd>{{ $provider->last_successful_payment_at?->format('j M Y, g:i A') ?? 'None verified' }}</dd></div>
                </dl>
            </article>
        @endforeach
    </section>
@endif

<form method="POST" action="{{ route('azari.admin.settings.integrations.update') }}" class="az-card az-form-grid">
    @csrf
    @method('PUT')
    <label>Default payment gateway
        <select name="payment_default_gateway">
            <option value="">Select gateway</option>
            @foreach(['flutterwave'=>'Flutterwave','pesapal'=>'Pesapal','intouch'=>'InTouch'] as $value=>$label)
                <option value="{{ $value }}" @selected(($settings['payment_default_gateway'] ?? '')===$value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Mail from address<input type="email" name="mail_from_address" value="{{ $settings['mail_from_address'] ?? '' }}"></label>
    <label>Mail from name<input name="mail_from_name" value="{{ $settings['mail_from_name'] ?? '' }}"></label>
    <label>Invoice prefix<input name="invoice_prefix" value="{{ $settings['invoice_prefix'] ?? 'INV' }}"></label>
    <label>Receipt prefix<input name="receipt_prefix" value="{{ $settings['receipt_prefix'] ?? 'RCT' }}"></label>
    <label>Booking prefix<input name="booking_prefix" value="{{ $settings['booking_prefix'] ?? 'AZR' }}"></label>
    <label class="az-span-2">Invoice footer<textarea name="invoice_footer">{{ $settings['invoice_footer'] ?? '' }}</textarea></label>
    <label class="az-span-2">Receipt footer<textarea name="receipt_footer">{{ $settings['receipt_footer'] ?? '' }}</textarea></label>
    <div class="az-s78-note az-span-2">Flutterwave, Pesapal and InTouch credentials, callback secrets and webhook secrets remain in <code>.env</code>. Paystack and Stripe are not part of the canonical Sprint 8 roadmap.</div>
    <div class="az-form-actions az-span-2"><button class="az-button">Save settings</button></div>
</form>
@endsection
