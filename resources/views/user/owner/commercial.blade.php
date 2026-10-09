@extends('layouts.user')
@section('title','Commercial setup')
@section('kicker','Property Centre')
@section('page_title',$property->name)
@section('content')
<section class="az-user-panel">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Commercial setup</h2>
            <p class="az-user-panel-subtitle">Manage unit quantities, accommodation types, rates and booking policies for this approved property.</p>
        </div>
    </header>
</section>

@include('partials.commercial-inventory-manager', ['property' => $property, 'roomTypes' => $roomTypes, 'ownerMode' => true])

@foreach($property->accommodationTypes as $type)
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">{{ $type->name }} calendar</h2>
            <p class="az-user-panel-subtitle">Bulk-edit availability, restrictions and price overrides for up to 367 days. Every change is audited.</p>
        </div>
    </header>
    <div class="az-user-panel-body">
        <h3>30-day inventory view</h3>
        <p class="az-user-panel-subtitle">Availability is calculated from the same property inventory, confirmed/pending stays, live holds, maintenance and active channel blocks used by booking search.</p>
        <div style="overflow-x:auto;margin:14px 0 20px">
            <table class="az-admin-table" style="min-width:860px">
                <thead><tr><th>Date</th><th>Sellable</th><th>Booked</th><th>Held</th><th>External</th><th>Maintenance</th><th>Available</th><th>Restriction</th><th>Rate override</th></tr></thead>
                <tbody>
                @foreach($calendarByType->get($type->id, collect()) as $day)
                    <tr>
                        <td>{{ $day['date']->format('D j M') }}</td>
                        <td>{{ $day['sellable'] }}</td>
                        <td>{{ $day['booked'] }}</td>
                        <td>{{ $day['held'] }}</td>
                        <td>{{ $day['external'] }}</td>
                        <td>{{ $day['maintenance'] }}</td>
                        <td><strong>{{ $day['available'] }}</strong></td>
                        <td>{{ $day['stop_sell'] ? 'Stop sell' : ($day['minimum_stay'] ? 'Min '.$day['minimum_stay'].' nights' : 'Open') }}</td>
                        <td>{{ $day['price_override'] !== null ? $type->currency.' '.number_format((float)$day['price_override'],2) : 'Base rate' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST"
              action="{{ route('user.owner.commercial.calendar.bulk-update',[$property,$type]) }}"
              data-calendar-preview
              data-preview-url="{{ route('user.owner.phase2.calendar.preview',[$property,$type]) }}"
              class="az-form-grid">
            @csrf
            <input type="hidden" name="expected_version" value="" data-calendar-version>
            <label><span>From</span><input type="date" name="from_date" required></label>
            <label><span>To</span><input type="date" name="to_date" required></label>
            <label><span>Sellable quantity</span><input type="number" name="sellable_inventory" min="0" max="{{ $type->total_inventory }}"></label>
            <label><span>Maintenance quantity</span><input type="number" name="maintenance_inventory" min="0" max="{{ $type->total_inventory }}"></label>
            <label><span>Minimum stay</span><input type="number" name="minimum_stay" min="1" max="730"></label>
            <label><span>Maximum stay</span><input type="number" name="maximum_stay" min="1" max="730"></label>
            <label><span>Nightly override</span><input type="number" name="price_override" min="0" step="0.01"></label>
            <label><span>Stop sell</span><select name="stop_sell"><option value="">No change</option><option value="1">Yes</option><option value="0">No</option></select></label>
            <label><span>Closed to arrival</span><select name="closed_to_arrival"><option value="">No change</option><option value="1">Yes</option><option value="0">No</option></select></label>
            <label><span>Closed to departure</span><select name="closed_to_departure"><option value="">No change</option><option value="1">Yes</option><option value="0">No</option></select></label>
            <div class="az-user-alert wide" data-calendar-preview-result hidden role="status" aria-live="polite"></div>
            <button class="az-user-button az-user-button--dark" type="submit" data-calendar-submit>Preview calendar update</button>
        </form>

        <div style="margin-top:24px">
            <h3>Recent calendar changes</h3>
            <div class="az-user-list">
                @forelse($recentCalendarChanges->get($type->id, collect())->take(10) as $change)
                    <div class="az-user-list-item">
                        <div>
                            <strong>{{ $change->from_date->format('j M') }}–{{ $change->to_date->format('j M Y') }}</strong>
                            <p>{{ collect($change->changes)->map(fn($value,$key)=>Str::headline($key).': '.(is_bool($value)?($value?'Yes':'No'):$value))->join(' · ') }}</p>
                            <small>{{ $change->actor?->name ?? 'System' }} · {{ $change->created_at->diffForHumans() }} @if($change->reverted_at) · Undone {{ $change->reverted_at->diffForHumans() }} @endif</small>
                        </div>
                        @if(!$change->reverted_at && !empty($change->before_snapshot))
                            <form method="POST" action="{{ route('user.owner.phase2.calendar.undo',[$property,$change]) }}">
                                @csrf
                                <button class="az-user-button az-user-button--light" type="submit" onclick="return confirm('Undo this calendar change? Current committed stays will still be protected.')">Undo safely</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="az-user-empty"><p>No calendar changes recorded yet.</p></div>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endforeach
@endsection
