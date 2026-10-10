@extends('admin.layouts.app')
@section('title','Dining concierge')
@section('content')
<div style="padding:22px">
    <h1>Dining partners & concierge</h1>
    <p>Curated dining enquiries are not table confirmations. Verify operators, opening hours and safety before publishing.</p>
    <section class="az-user-panel" style="padding:20px">
        <h2>Register partner</h2>
        <form method="POST" action="{{ route('azari.admin.dining.store') }}" class="az-form-grid">
            @csrf
            <label>Name <input name="name" required maxlength="160"></label>
            <label>Full address <input name="address" required maxlength="350"></label>
            <label>City <input name="city" required maxlength="100"></label>
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
                <form method="POST" action="{{ route('azari.admin.dining.decline',$item) }}">
                    @csrf <button type="submit">Mark unavailable / needs support</button>
                </form>
            </div>
        @empty <p>No pending enquiries.</p>
        @endforelse
    </section>
</div>
@endsection
