@extends('admin.layouts.app')
@section('title', 'Historical records')
@section('content')
<section class="az-admin-panel" aria-labelledby="archive-title">
    <h1 id="archive-title">Archived supplier requests</h1>
    <p>This is a read-only audit index for requests created before Resavar refocused on accommodation. New travel and partner transactions are disabled. No payout, refund or booking can be initiated here.</p>
    <div style="overflow-x:auto">
        <table class="az-admin-table">
            <thead>
                <tr><th scope="col">Request reference</th><th scope="col">Guest ID</th><th scope="col">Historical offer</th><th scope="col">Status</th><th scope="col">Quote</th><th scope="col">Created</th></tr>
            </thead>
            <tbody>
                @forelse($requests as $historical)
                    <tr>
                        <td>{{ $historical->id }}</td>
                        <td>{{ $historical->user_id }}</td>
                        <td>{{ $historical->offer?->title ?? 'Unavailable' }}</td>
                        <td>{{ str_replace('_', ' ', $historical->status) }}</td>
                        <td>{{ $historical->currency }} {{ number_format(((int) $historical->quoted_total_minor) / 100, 2) }} <small>(indicative)</small></td>
                        <td>{{ $historical->created_at?->format('j M Y, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No historical supplier requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
    <h2>Former dining requests</h2>
    @forelse($diningRequests as $dining)
        <p><strong>{{ $dining->id }}</strong> · guest #{{ $dining->user_id }} ·
            {{ $dining->partner?->name ?? 'Unknown partner' }} ·
            {{ str_replace('_', ' ', $dining->status) }}
        </p>
    @empty
        <p>No archived dining requests.</p>
    @endforelse
    {{ $diningRequests->links() }}
    <h2>Former trip assemblies</h2>
    @forelse($assemblies as $assembly)
        <p><strong>{{ $assembly->id }}</strong> · guest #{{ $assembly->user_id }} ·
            {{ str_replace('_', ' ', $assembly->status) }} ·
            {{ $assembly->created_at?->format('j M Y') }}
        </p>
    @empty
        <p>No archived trip assemblies.</p>
    @endforelse
    {{ $assemblies->links() }}
    <h2>Historical external calendar reservations</h2>
    <div style="overflow-x:auto">
        <table class="az-admin-table">
            <thead><tr><th scope="col">Source record</th><th scope="col">Property</th><th scope="col">Dates</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse($channelReservations as $reservation)
                    <tr>
                        <td>{{ $reservation->id }}</td>
                        <td>{{ $reservation->property?->name ?? 'Unavailable' }}</td>
                        <td>{{ $reservation->starts_on?->format('j M Y') }} – {{ $reservation->ends_on?->format('j M Y') }}</td>
                        <td>{{ str_replace('_', ' ', $reservation->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No retained external calendar reservations.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $channelReservations->links() }}
    <p>Do not interpret a supplier enquiry as proof of a captured payment. Verify financial evidence in the existing payment, refund and ledger modules.</p>
</section>
@endsection
