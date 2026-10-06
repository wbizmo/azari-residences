@php
    $ownerMode = $ownerMode ?? false;
    $currency = $property->currency ?: config('azari.currency', 'USD');
    $accommodationStoreRoute = $ownerMode
        ? route('user.owner.commercial.accommodations.store', $property)
        : route('azari.admin.properties.commercial.accommodations.store', $property);
@endphp

@if($errors->any())
    <div class="{{ $ownerMode ? 'az-user-alert az-user-alert--danger' : 'admin-card' }}" role="alert">
        <strong>Please correct the highlighted commercial setup fields.</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section class="{{ $ownerMode ? 'az-user-panel' : 'admin-card' }}">
    <header class="{{ $ownerMode ? 'az-user-panel-header' : 'admin-heading' }}">
        <div>
            <span>Inventory</span>
            <h2>Accommodation types</h2>
            <p>Each type is independently capacity and quantity aware. A private residence can remain at one unit; hotel room categories can use larger inventory.</p>
        </div>
    </header>

    <div class="{{ $ownerMode ? 'az-user-panel-body' : '' }}">
        @foreach($property->accommodationTypes as $type)
            @php
                $typeUpdateRoute = $ownerMode
                    ? route('user.owner.commercial.accommodations.update', [$property, $type])
                    : route('azari.admin.properties.commercial.accommodations.update', [$property, $type]);
            @endphp
            <details class="reserva-commercial-editor" @if($loop->first) open @endif>
                <summary>
                    <strong>{{ $type->name }}</strong>
                    <span>{{ $type->total_inventory }} {{ Str::plural('unit', $type->total_inventory) }} · {{ $currency }} {{ number_format((float) $type->base_rate, 2) }}</span>
                </summary>

                <form method="POST" action="{{ $typeUpdateRoute }}" class="reserva-commercial-form">
                    @csrf
                    @method('PUT')
                    @include('partials.commercial-inventory-type-fields', ['type' => $type, 'roomTypes' => $roomTypes, 'currency' => $currency])
                    <button class="{{ $ownerMode ? 'az-user-button az-user-button--dark' : 'button button-primary' }}" type="submit">Save accommodation type</button>
                </form>

                <div class="reserva-rate-plan-stack">
                    <h3>Rate plans</h3>

                    @foreach($type->ratePlans as $ratePlan)
                        @php
                            $rateUpdateRoute = $ownerMode
                                ? route('user.owner.commercial.rate-plans.update', [$property, $type, $ratePlan])
                                : route('azari.admin.properties.commercial.rate-plans.update', [$property, $type, $ratePlan]);
                        @endphp

                        <details class="reserva-rate-plan-editor">
                            <summary>
                                <strong>{{ $ratePlan->name }}</strong>
                                <span>{{ $ratePlan->code }} · {{ $ratePlan->is_public ? 'Public' : 'Private' }}</span>
                            </summary>
                            <form method="POST" action="{{ $rateUpdateRoute }}" class="reserva-commercial-form">
                                @csrf
                                @method('PUT')
                                @include('partials.commercial-rate-plan-fields', ['ratePlan' => $ratePlan])
                                <button class="{{ $ownerMode ? 'az-user-button az-user-button--dark' : 'button button-primary' }}" type="submit">Save rate plan</button>
                            </form>
                        </details>
                    @endforeach

                    @php
                        $rateStoreRoute = $ownerMode
                            ? route('user.owner.commercial.rate-plans.store', [$property, $type])
                            : route('azari.admin.properties.commercial.rate-plans.store', [$property, $type]);
                    @endphp
                    <details class="reserva-rate-plan-editor">
                        <summary><strong>Add rate plan</strong></summary>
                        <form method="POST" action="{{ $rateStoreRoute }}" class="reserva-commercial-form">
                            @csrf
                            @include('partials.commercial-rate-plan-fields', ['ratePlan' => new AppModelsRatePlan()])
                            <button class="{{ $ownerMode ? 'az-user-button az-user-button--dark' : 'button button-primary' }}" type="submit">Create rate plan</button>
                        </form>
                    </details>
                </div>
            </details>
        @endforeach

        <details class="reserva-commercial-editor">
            <summary><strong>Add accommodation type</strong></summary>
            <form method="POST" action="{{ $accommodationStoreRoute }}" class="reserva-commercial-form">
                @csrf
                @include('partials.commercial-inventory-type-fields', ['type' => new AppModelsAccommodationType(), 'roomTypes' => $roomTypes, 'currency' => $currency])
                <button class="{{ $ownerMode ? 'az-user-button az-user-button--dark' : 'button button-primary' }}" type="submit">Create accommodation type</button>
            </form>
        </details>
    </div>
</section>
