@extends('layouts.user')
@section('title','Additional guests')
@section('kicker','Dojah identity verification')
@section('page_title','Additional guests')
@section('content')
<div class="az-user-restricted-note">Every additional adult must pass Dojah identity verification. Manual ID uploads are no longer used or accepted for verification.</div>
<section class="az-user-panel" style="margin-top:18px">
<header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Additional adult verification</h2><p class="az-user-panel-subtitle">Each adult must be verified before the booking can proceed to payment or become eligible for check-in.</p></div></header>
<div class="az-user-panel-body">
@if($guests->isEmpty())
    <div class="az-user-empty"><span class="material-symbols-outlined">group</span><p>No additional adults are currently attached to your bookings.</p></div>
@else
    <div class="az-user-list">
    @foreach($guests as $guest)
        @php
            $dojah = $guest->latestIdentityVerification;
            $ready = $dojah?->isVerified() ?? false;
        @endphp
        <article class="az-user-list-item">
            <div>
                <h3>{{ $guest->full_name }} · {{ $guest->booking?->reference }}</h3>
                <p>{{ $guest->booking?->property?->name }} · {{ $ready ? 'Dojah verified' : 'Dojah verification required' }}</p>
            </div>
            <div class="az-user-actions">
                <span class="az-user-status {{ $ready ? '' : 'az-user-status--warning' }}">{{ $ready ? 'Verified' : 'Required' }}</span>
                @if(!$ready)
                    <a class="az-user-button az-user-button--dark" href="{{ route('user.guests.identity.dojah',[$guest->booking->reference,$guest]) }}">Verify with Dojah</a>
                @endif
            </div>
        </article>
    @endforeach
    </div>
    {{ $guests->links() }}
@endif
</div>
</section>
@endsection
