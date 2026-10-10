@extends('admin.layouts.app')
@section('title','Mobile release decision')
@push('head')
<style>
.resavar-admin-tools{width:min(1120px,100%);margin:0 auto;padding:clamp(16px,2.5vw,28px);color:#052058}
.resavar-admin-tools .az-user-panel{background:#FFFFFF;color:#052058;border:1px solid #D8E2EE;border-radius:14px;overflow:hidden}
.resavar-admin-tools h1,.resavar-admin-tools h2,.resavar-admin-tools p,.resavar-admin-tools label{color:#052058}
.resavar-admin-tools :is(input,select,textarea){display:block;width:100%;max-width:100%;min-height:42px;border:1px solid #9DAEC9;border-radius:8px;background:#FFFFFF;color:#052058;padding:9px 11px;box-sizing:border-box}
.resavar-admin-tools :is(button[type=submit],.resavar-admin-button){display:inline-flex;justify-content:center;align-items:center;gap:8px;min-height:42px;padding:9px 15px;background:#052058;color:#FFFFFF;border:1px solid #052058;border-radius:9px;font-weight:700;cursor:pointer}
.resavar-admin-tools :is(button[type=submit],.resavar-admin-button):hover{background:#FFFFFF;color:#052058}
.resavar-admin-tools :is(button,input,select,textarea,a):focus-visible{outline:3px solid #365A91;outline-offset:2px}
.resavar-admin-tools table{width:100%;border-collapse:collapse;display:block;overflow-x:auto}
.resavar-admin-tools :is(th,td){padding:10px 12px;border-bottom:1px solid #D8E2EE;text-align:left;white-space:normal}
@media(max-width:650px){.resavar-admin-tools .az-form-grid{display:grid;grid-template-columns:1fr}.resavar-admin-tools :is(button[type=submit],.resavar-admin-button){width:100%}}
@media print{.resavar-admin-tools{padding:0}.resavar-admin-tools button{display:none!important}}
</style>
@endpush
@section('content')
<div class="resavar-admin-tools">
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
