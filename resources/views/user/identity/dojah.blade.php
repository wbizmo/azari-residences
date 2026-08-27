@extends('layouts.user')
@section('title','Dojah identity verification')
@section('kicker','Secure identity verification')
@section('page_title','Verify identity')
@section('content')
@php($verified = $verification->isVerified())
@php($continueUrl = session('url.intended', $backUrl))
<section class="az-user-panel">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Dojah verification · {{ $subjectName }}</h2>
            <p class="az-user-panel-subtitle">Azari uses Dojah for production identity verification. The signed server-side webhook result is authoritative.</p>
        </div>
        <span class="az-user-status {{ $verified ? '' : 'az-user-status--warning' }}">{{ str_replace('_',' ',$verification->status) }}</span>
    </header>
    <div class="az-user-panel-body">
        <dl class="az-user-detail-list">
            <div class="az-user-detail-row"><dt>Reference</dt><dd>{{ $verification->reference }}</dd></div>
            <div class="az-user-detail-row"><dt>Status</dt><dd>{{ ucfirst(str_replace('_',' ',$verification->status)) }}</dd></div>
            @if($verification->verified_at)<div class="az-user-detail-row"><dt>Verified</dt><dd><x-user-local-time :value="$verification->verified_at" /></dd></div>@endif
            @if($verification->failure_reason)<div class="az-user-detail-row"><dt>Result</dt><dd>{{ $verification->failure_reason }}</dd></div>@endif
        </dl>

        @if($verified)
            <div class="az-user-alert" style="margin-top:18px"><strong>Identity verified.</strong><p>Your Azari account is verified. Protected bookings, payments, property-owner actions and other KYC-gated features are now available.</p></div>
            <div class="az-user-actions" style="margin-top:18px">
                <a class="az-user-button az-user-button--dark" href="{{ $continueUrl }}">Continue</a>
            </div>
        @elseif(!$widgetConfigured)
            <div class="az-user-alert az-user-alert--danger" style="margin-top:18px"><strong>Dojah flow is not configured.</strong><p>A published EasyOnboard flow is required for the hosted verification experience. Set its DOJAH_WIDGET_ID before enabling identity verification. DOJAH_TOKEN_ID is not used as a Widget ID.</p></div>
        @else
            <div class="az-user-alert" style="margin-top:18px"><strong>Complete verification with Dojah.</strong><p>The secure Dojah flow opens in a new tab. When you finish, return here and refresh your status. Finishing the browser flow alone does not verify the Azari account; the signed Dojah webhook must confirm the result.</p></div>
            <div class="az-user-actions" style="margin-top:18px">
                <a class="az-user-button az-user-button--dark" href="{{ $widget['launch_url'] }}" target="_blank" rel="noopener noreferrer">Start secure verification</a>
                <a class="az-user-button az-user-button--light" href="{{ url()->current() }}">Refresh status</a>
            </div>
        @endif

        <div class="az-user-actions" style="margin-top:18px"><a class="az-user-button az-user-button--light" href="{{ $backUrl }}">Back</a></div>
    </div>
</section>
@endsection
