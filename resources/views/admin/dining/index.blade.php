@extends('admin.layouts.app')
@section('title','Dining concierge')
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
    <h1>Dining partners & concierge</h1>
    <p>Curated dining enquiries are not table confirmations. Verify operators, opening hours and safety before publishing.</p>
    <section class="az-user-panel" style="padding:20px">
        <h2>Register partner</h2>
        <form method="POST" action="{{ route('azari.admin.dining.store') }}" class="az-form-grid">
            @csrf
            <label>Name <input name="name" required maxlength="160"></label>
            <label>Full address <input name="address" required maxlength="350"></label>
            <label>City <input name="city" required maxlength="100"></label>
            <label>Verified latitude <input type="number" name="latitude" step="0.0000001" min="-90" max="90" required></label>
            <label>Verified longitude <input type="number" name="longitude" step="0.0000001" min="-180" max="180" required></label>
            <label>IANA timezone <input name="timezone" value="Africa/Lagos" required></label>
            <label>HTTPS website <input type="url" name="website"></label>
            <label>Support email <input type="email" name="support_email"></label>
            <label>Disclosures / cancellation policy <textarea name="disclosures" required maxlength="1500"></textarea></label>
            <button type="submit">Create pending listing</button>
        </form>
    </section>
    <section class="az-user-panel" style="padding:20px;margin-top:18px">
        <h2>Dining listings</h2>
        @forelse($partners as $partner)
            <div style="padding:12px;border-bottom:1px solid #ddd">
                <strong>{{ $partner->name }}</strong> · {{ $partner->city }} · {{ $partner->status }}
                <p>{{ $partner->disclosures }}</p>
                @if($partner->status!=='published')
                    <form method="POST" action="{{ route('azari.admin.dining.publish',$partner) }}">
                        @csrf <input type="hidden" name="review_attestation" value="DETAILS_AND_TERMS_VERIFIED">
                        <button type="submit">Confirm review and publish</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('azari.admin.dining.pause',$partner) }}">
                        @csrf <button type="submit">Pause listing</button>
                    </form>
                @endif
            </div>
        @empty <p>No suppliers onboarded.</p>
        @endforelse
    </section>
    <section class="az-user-panel" style="padding:20px;margin-top:18px">
        <h2>Concierge follow-ups</h2>
        @forelse($enquiries as $item)
            <div style="padding:12px;border-bottom:1px solid #ddd">
                <p><strong>{{ $item->partner?->name }}</strong> · {{ $item->party_size }} guests · {{ $item->requested_for }} · {{ $item->status }}</p>
                <p>Request ID {{ $item->id }}. Private dietary notes are never displayed or sent automatically.</p>
                @if(config('travel.dining_provider_confirmation_enabled',false))
                <form method="POST" action="{{ route('azari.admin.dining.verify',$item) }}">
                    @csrf
                    <label>Supplier reservation reference <input name="provider_reference" maxlength="160" required></label>
                    <button type="submit">Verify independently against partner API</button>
                </form>
                @endif
                <form method="POST" action="{{ route('azari.admin.dining.decline',$item) }}">
                    @csrf <button type="submit">Mark unavailable / needs support</button>
                </form>
            </div>
        @empty <p>No pending enquiries.</p>
        @endforelse
    </section>
</div>
@endsection
