@extends('admin.layouts.app')
@section('title', 'Payments & Integrations')
@section('content')
<div class="az-page-heading"><div><p class="az-eyebrow">System configuration</p><h1>Payments, messaging and documents</h1></div></div>
<form method="POST" action="{{ route('azari.admin.settings.integrations.update') }}" class="az-card az-form-grid">@csrf @method('PUT')
<label>Default payment gateway<select name="payment_default_gateway"><option value="">Select gateway</option>@foreach(['paystack'=>'Paystack','flutterwave'=>'Flutterwave','stripe'=>'Stripe'] as $value=>$label)<option value="{{ $value }}" @selected(($settings['payment_default_gateway'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Paystack public key<input name="paystack_public_key" value="{{ $settings['paystack_public_key'] ?? '' }}"></label>
<label>Paystack secret key<input type="password" name="paystack_secret_key" value="{{ $settings['paystack_secret_key'] ?? '' }}"></label>
<label>Flutterwave public key<input name="flutterwave_public_key" value="{{ $settings['flutterwave_public_key'] ?? '' }}"></label>
<label>Flutterwave secret key<input type="password" name="flutterwave_secret_key" value="{{ $settings['flutterwave_secret_key'] ?? '' }}"></label>
<label>Stripe public key<input name="stripe_public_key" value="{{ $settings['stripe_public_key'] ?? '' }}"></label>
<label>Stripe secret key<input type="password" name="stripe_secret_key" value="{{ $settings['stripe_secret_key'] ?? '' }}"></label>
<label>Twilio SID<input name="twilio_sid" value="{{ $settings['twilio_sid'] ?? '' }}"></label>
<label>Twilio token<input type="password" name="twilio_token" value="{{ $settings['twilio_token'] ?? '' }}"></label>
<label>Twilio sender<input name="twilio_from" value="{{ $settings['twilio_from'] ?? '' }}"></label>
<label>Mail from address<input type="email" name="mail_from_address" value="{{ $settings['mail_from_address'] ?? '' }}"></label>
<label>Mail from name<input name="mail_from_name" value="{{ $settings['mail_from_name'] ?? '' }}"></label>
<label>Invoice prefix<input name="invoice_prefix" value="{{ $settings['invoice_prefix'] ?? 'INV' }}"></label>
<label>Receipt prefix<input name="receipt_prefix" value="{{ $settings['receipt_prefix'] ?? 'RCT' }}"></label>
<label>Booking prefix<input name="booking_prefix" value="{{ $settings['booking_prefix'] ?? 'AZR' }}"></label>
<label class="az-span-2">Invoice footer<textarea name="invoice_footer">{{ $settings['invoice_footer'] ?? '' }}</textarea></label>
<label class="az-span-2">Receipt footer<textarea name="receipt_footer">{{ $settings['receipt_footer'] ?? '' }}</textarea></label>
<div class="az-form-actions az-span-2"><button class="az-button">Save settings</button></div>
</form>
@endsection
