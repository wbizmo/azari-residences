@extends('admin.layouts.app')
@section('title','Customer account')
@section('content')
@php
    $fallback = fn($value) => filled($value) ? $value : 'Not provided';
    $fields = collect($user->getAttributes())->except(['password','remember_token']);
@endphp
<section class="az-page-heading"><div><p class="az-eyebrow">Customer account</p><h1>{{ $user->name ?: 'Unnamed customer' }}</h1><p>Read-only account and operational history.</p></div><div class="az-page-actions">
@if($user->isSuspended())
<form method="POST" action="{{ route('azari.admin.users.reactivate',$user) }}">@csrf @method('PUT')<button class="az-button" type="submit">Reactivate account</button></form>
@else
<button type="button" class="az-button az-button--danger" data-az-modal-open="user-action-modal" data-action="{{ route('azari.admin.users.suspend',$user) }}" data-method="PUT" data-title="Suspend user" data-message="Suspend {{ $user->name }}?" data-confirm-label="Suspend account" data-reason="Suspended by administrator">Suspend account</button>
@endif
</div></section>

<section class="az-s78-card"><h2>Complete account record</h2><div class="az-s78-detail-grid">
@foreach($fields as $field => $value)
<div><dt>{{ Str::headline($field) }}</dt><dd>
@if(is_bool($user->getAttribute($field))){{ $user->getAttribute($field) ? 'Yes' : 'No' }}
@elseif($value instanceof \DateTimeInterface){{ $value->format('d M Y, H:i:s') }}
@elseif(is_array($value)){{ json_encode($value, JSON_UNESCAPED_SLASHES) }}
@else{{ $fallback($value) }}@endif
</dd></div>
@endforeach
<div><dt>Password</dt><dd>Protected credential</dd></div><div><dt>Remember token</dt><dd>Protected credential</dd></div>
</div></section>

<div class="az-s78-grid" style="margin-top:18px"><section class="az-s78-card"><h2>Bookings ({{ $user->bookings_count }})</h2>@forelse($bookings as $booking)<article class="az-s78-event"><strong><a href="{{ route('azari.admin.bookings.show',$booking) }}">{{ $booking->reference }}</a></strong><p>{{ $booking->property?->name ?? 'Residence unavailable' }} · {{ Str::headline($booking->status) }} · {{ $booking->currency }} {{ number_format((float)$booking->total,2) }}</p></article>@empty<p>No bookings.</p>@endforelse{{ $bookings->links() }}</section>
<section class="az-s78-card"><h2>Payments ({{ $user->payments_count }})</h2>@forelse($payments as $payment)<article class="az-s78-event"><strong><a href="{{ route('azari.admin.payments.show',$payment) }}">{{ $payment->reference }}</a></strong><p>{{ Str::headline($payment->status) }} · {{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</p></article>@empty<p>No payments.</p>@endforelse{{ $payments->links() }}</section></div>
<x-azari-confirm-modal id="user-action-modal" />
@endsection
