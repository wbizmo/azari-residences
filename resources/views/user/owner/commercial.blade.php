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
@endsection

@foreach($property->accommodationTypes as $type)
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">{{ $type->name }} calendar</h2>
            <p class="az-user-panel-subtitle">Bulk-edit availability, restrictions and price overrides for up to 367 days. Every change is audited.</p>
        </div>
    </header>
    <div class="az-user-panel-body">
        <form method="POST" action="{{ route('user.owner.commercial.calendar.bulk-update',[$property,$type]) }}" class="az-form-grid">
            @csrf
            <label><span>From</span><input type="date" name="from_date" required></label>
            <label><span>To</span><input type="date" name="to_date" required></label>
            <label><span>Sellable quantity</span><input type="number" name="sellable_inventory" min="0" max="{{ $type->total_inventory }}"></label>
            <label><span>Maintenance quantity</span><input type="number" name="maintenance_inventory" min="0" max="{{ $type->total_inventory }}"></label>
            <label><span>Minimum stay</span><input type="number" name="minimum_stay" min="1" max="730"></label>
            <label><span>Maximum stay</span><input type="number" name="maximum_stay" min="1" max="730"></label>
            <label><span>Nightly override</span><input type="number" name="price_override" min="0" step="0.01"></label>
            <label><input type="checkbox" name="stop_sell" value="1"> Stop sell</label>
            <label><input type="checkbox" name="closed_to_arrival" value="1"> Closed to arrival</label>
            <label><input type="checkbox" name="closed_to_departure" value="1"> Closed to departure</label>
            <button class="az-user-button az-user-button--dark" type="submit">Apply calendar update</button>
        </form>
    </div>
</section>
@endforeach
