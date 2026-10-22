@extends('admin.layouts.app')
@section('title', 'Accommodation supply readiness')
@section('content')
<div class="admin-heading">
    <div><span>Owner and property operations</span><h1>Accommodation supply readiness</h1>
        <p>Private live-data report. Published means visible; bookable means one adult, one unit, these dates and a positive canonical price quote. This is not a reservation hold.</p>
    </div>
    <a class="button button-primary" href="{{ route('azari.admin.properties.index') }}">Manage properties</a>
</div>
<div class="az-alert" role="note">
    Never publish a listing without independent ownership evidence, reviewed imagery and correct policy/rate/availability. Historical channel reservations remain blocked until reconciled.
</div>
<form method="GET" action="{{ route('azari.admin.supply-readiness.index') }}" class="admin-table-wrap" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:14px;align-items:end">
    <label>Check-in <input type="date" name="check_in" required value="{{ $checkIn->toDateString() }}" min="{{ now()->toDateString() }}"></label>
    <label>Check-out <input type="date" name="check_out" required value="{{ $checkOut->toDateString() }}" min="{{ $checkIn->addDay()->toDateString() }}"></label>
    <button type="submit" class="button button-primary">Check sellability</button>
</form>
<div class="admin-table-wrap" style="padding:16px;margin-bottom:20px">
    <strong>Recorded supply (portfolio-wide)</strong>
    <p>Owner drafts: {{ number_format($counts['owner_drafts']) }} · Pending owner review: {{ number_format($counts['owner_submitted']) }} · Published records: {{ number_format($counts['published']) }} · Unpublished properties: {{ number_format($counts['unpublished']) }}</p>
    <small>Date-specific results below cover this page of {{ $properties->count() }} properties, not the entire portfolio.</small>
</div>
<div class="admin-table-wrap">
    <table class="admin-table">
        <thead><tr><th>Accommodation</th><th>Destination / owner</th><th>Readiness for selected dates</th><th>Evidence / actions</th></tr></thead>
        <tbody>
        @forelse($properties as $property)
            @php($readiness = $inspections[$property->id])
            <tr>
                <td><strong>{{ $property->name }}</strong><div>{{ $property->currency }} · {{ $property->property_type }}</div></td>
                <td>{{ $readiness['location'] }}<div>{{ $property->owner?->name ?? 'Resavar managed' }}</div></td>
                <td>
                    <strong>{{ str_replace('_', ' ', ucfirst($readiness['state'])) }}</strong>
                    @if($readiness['ready'])
                        <div>One unit from {{ $readiness['quote']['currency'] }} {{ number_format((float)$readiness['quote']['total'],2) }} for {{ $checkIn->toDateString() }} to {{ $checkOut->toDateString() }}. Not held.</div>
                    @else
                        <ul>@foreach($readiness['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
                    @endif
                </td>
                <td>
                    <div>{{ $readiness['verified_claims'] }} active independently verified claims</div>
                    <a href="{{ route('azari.admin.properties.edit', $property) }}">Review property</a>
                    <div><a href="{{ route('azari.admin.properties.commercial', $property) }}">Rates and calendar</a></div>
                </td>
            </tr>
        @empty
            <tr><td colspan="4">No properties have been created. No sellable inventory is claimed.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div style="margin-top:18px">{{ $properties->links() }}</div>
@endsection
