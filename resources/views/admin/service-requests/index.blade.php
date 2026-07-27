@extends('admin.layout')

@section('title', 'Service requests')

@section('content')
<section class="az-page-heading az-service-request-hero">
    <div>
        <p class="az-eyebrow">Guest operations</p>
        <h1>Service requests</h1>
        <p>Review every booking-linked concierge, housekeeping, restaurant, airport-transfer, maintenance and general guest request.</p>
    </div>
</section>

<section class="az-admin-card az-service-request-card">
    <header class="az-admin-card__header">
        <div>
            <h2>Request queue</h2>
            <p>Open a request to review its details, status history, assignment and guest-facing response.</p>
        </div>
    </header>

    <div class="az-admin-table-wrap az-service-request-table-wrap">
        <table class="az-admin-table az-service-request-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Type</th>
                    <th>Booking</th>
                    <th>Guest</th>
                    <th>Property</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Assigned</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $sr)
                    <tr>
                        <td>
                            <a class="az-service-request-reference" href="{{ route('azari.admin.service-requests.show', $sr) }}">
                                {{ $sr->reference }}
                            </a>
                        </td>
                        <td>{{ ucwords(str_replace('_', ' ', $sr->type)) }}</td>
                        <td>{{ $sr->booking?->reference ?: '—' }}</td>
                        <td>{{ $sr->user?->name ?: $sr->booking?->guest_name ?: '—' }}</td>
                        <td>{{ $sr->booking?->property?->name ?: '—' }}</td>
                        <td>{{ $sr->requested_at?->format('j M Y, g:i A') ?: '—' }}</td>
                        <td><span class="az-status-badge">{{ ucwords(str_replace('_', ' ', $sr->status)) }}</span></td>
                        <td>{{ $sr->assignee?->name ?: 'Unassigned' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="az-service-request-empty">No service requests have been submitted.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="az-admin-pagination">
        <span>
            @if(method_exists($requests, 'total'))
                Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }} requests
            @else
                {{ $requests->count() }} requests shown
            @endif
        </span>
        {{ $requests->onEachSide(1)->links() }}
    </div>
</section>
@endsection
