@extends('layouts.user')
@section('title','Dojah identity verification')
@section('kicker','Secure identity verification')
@section('page_title','Verify identity')

@section('content')
@php($verified = $verification->isVerified())

<section class="az-user-panel">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Dojah verification · {{ $subjectName }}</h2>
            <p class="az-user-panel-subtitle">Dojah is the only identity-verification authority for Resarva customer accounts and adult booking guests.</p>
        </div>

        <span class="az-user-status {{ $verified ? '' : 'az-user-status--warning' }}" data-dojah-status>
            {{ $verified ? 'Verified' : ucfirst(str_replace('_',' ',$verification->status)) }}
        </span>
    </header>

    <div class="az-user-panel-body">
        <dl class="az-user-detail-list">
            <div class="az-user-detail-row">
                <dt>Reference</dt>
                <dd>{{ $verification->reference }}</dd>
            </div>

            <div class="az-user-detail-row">
                <dt>Status</dt>
                <dd data-dojah-status-text>{{ $verified ? 'Verified' : ucfirst(str_replace('_',' ',$verification->status)) }}</dd>
            </div>

            @if($verification->verified_at)
                <div class="az-user-detail-row">
                    <dt>Verified</dt>
                    <dd><x-user-local-time :value="$verification->verified_at" /></dd>
                </div>
            @endif

            @if($verification->failure_reason)
                <div class="az-user-detail-row">
                    <dt>Result</dt>
                    <dd>{{ $verification->failure_reason }}</dd>
                </div>
            @endif
        </dl>

        @if($verified)
            <div class="az-user-alert" style="margin-top:18px">
                <strong>Identity verified.</strong>
                <p>Your Resarva account is verified. Bookings, payments, property-owner actions and other KYC-gated features are available.</p>
            </div>

            <div class="az-user-actions" style="margin-top:18px">
                <a class="az-user-button az-user-button--dark" href="{{ $continueUrl }}">Continue</a>
            </div>
        @elseif(!$widgetConfigured)
            <div class="az-user-alert az-user-alert--danger" style="margin-top:18px">
                <strong>Identity verification is temporarily unavailable.</strong>
                <p>The Dojah EasyOnboard flow is not currently available. Resarva fails closed: protected actions stay locked until Dojah verification can be completed successfully.</p>
            </div>
        @else
            <div class="az-user-alert" style="margin-top:18px">
                <strong>Complete verification with Dojah.</strong>
                <p>
                    The secure Dojah flow opens in a new tab. Keep this Resarva page open.
                    As soon as Resarva receives and validates Dojah's signed result, this page automatically returns you to the booking or action you were completing.
                </p>
            </div>

            <div class="az-user-actions" style="margin-top:18px">
                <a
                    class="az-user-button az-user-button--dark"
                    href="{{ $widget['launch_url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Start secure verification
                </a>

                <a class="az-user-button az-user-button--light" href="{{ url()->current() }}">
                    Refresh status
                </a>
            </div>

            <p class="az-user-panel-subtitle" style="margin-top:12px" data-dojah-waiting>
                Waiting securely for Dojah's signed verification result…
            </p>
        @endif

        <div class="az-user-actions" style="margin-top:18px">
            <a class="az-user-button az-user-button--light" href="{{ $backUrl }}">Back</a>
        </div>
    </div>
</section>

@if(!$verified && $widgetConfigured)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusUrl = @json(route('user.identity.status'));
    const fallbackContinueUrl = @json($continueUrl);
    let stopped = false;

    async function checkStatus() {
        if (stopped || document.hidden) {
            return;
        }

        try {
            const response = await fetch(statusUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            const badge = document.querySelector('[data-dojah-status]');
            const statusText = document.querySelector('[data-dojah-status-text]');
            const waiting = document.querySelector('[data-dojah-waiting]');
            const label = String(data.status || 'pending').replaceAll('_', ' ');
            const display = label.charAt(0).toUpperCase() + label.slice(1);

            if (badge) badge.textContent = data.verified ? 'Verified' : display;
            if (statusText) statusText.textContent = data.verified ? 'Verified' : display;
            if (waiting && data.failure_reason) waiting.textContent = data.failure_reason;

            if (data.verified) {
                stopped = true;
                window.location.assign(data.continue_url || fallbackContinueUrl);
            }
        } catch (_) {
            // A temporary network failure should not interrupt the Dojah flow.
        }
    }

    const timer = window.setInterval(checkStatus, 3500);

    window.addEventListener('beforeunload', function () {
        stopped = true;
        window.clearInterval(timer);
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            checkStatus();
        }
    });
});
</script>
@endif
@endsection
