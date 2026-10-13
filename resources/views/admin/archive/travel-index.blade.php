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
    <p>Do not interpret a supplier enquiry as proof of a captured payment. Verify financial evidence in the existing payment, refund and ledger modules.</p>
</section>
@endsection
