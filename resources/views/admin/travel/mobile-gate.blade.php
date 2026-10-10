@extends('layouts.admin')
@section('title','Mobile release decision')
@section('content')
<div style="padding:22px">
    <h1>Mobile vs PWA demand decision</h1>
    <p>This is a release gate, not an assumption that a native app is needed. The booking and payment source of truth must remain Resavar's Laravel backend.</p>
    <p>Window: {{ $report['window_days'] }} days</p>
    <table>
        <thead><tr><th>Signal</th><th>Measured</th><th>Minimum</th></tr></thead>
        <tbody>
            <tr><td>Unique PWA installs</td><td>{{ $report['pwa_installs'] }}</td><td>{{ $report['thresholds']['installs'] }}</td></tr>
            <tr><td>Returning PWA users</td><td>{{ $report['returning_pwa_guests'] }}</td><td>{{ $report['thresholds']['returning'] }}</td></tr>
            <tr><td>PWA-attributed paid bookings</td><td>{{ $report['pwa_attributed_paid_bookings'] }}</td><td>{{ $report['thresholds']['bookings'] }}</td></tr>
            <tr><td>All paid bookings (context only)</td><td>{{ $report['total_paid_bookings'] }}</td><td>Not a native signal</td></tr>
        </tbody>
    </table>
    <p><strong>{{ $report['recommendation'] }}</strong></p>
    <p>Native rollout authorized: {{ $report['native_code_authorized'] ? 'Yes, pending human approval' : 'No' }}</p>
    <h2>Required before any mobile release</h2>
    <p>Standard OAuth authorization-code/PKCE with verified redirect URIs, short-lived tokens and device revocation;
       no offline replay of financial mutations; rate-limited idempotent booking API; zero persistent payment secrets;
       isolated private document cache cleared on logout; per-device notification revocation; accessibility and crash telemetry.</p>
    <p>No separate native booking engine should be built until the demand gate and explicit business/security sign-off are met.</p>
</div>
@endsection
