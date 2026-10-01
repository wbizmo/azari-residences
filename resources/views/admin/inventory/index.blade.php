@extends('admin.layouts.app')
@section('content')
    <div class="admin-heading">
        <div>
            <span>SPRINT 04</span>
            <h1>Property management</h1>
            <p>Manage locations, buildings, room types, amenities, properties, publication status and pricing.</p>
        </div>
        <a class="button button-primary" href="{{ route('azari.admin.inventory.properties.create') }}">Add property</a>
    </div>

    <div class="az-admin-tabs" data-admin-tabs>
        <button type="button" class="is-active" data-tab-target="properties">Properties</button>
        <button type="button" data-tab-target="locations">Locations</button>
        <button type="button" data-tab-target="buildings">Buildings</button>
        <button type="button" data-tab-target="types">Room types</button>
        <button type="button" data-tab-target="amenities">Amenities</button>
    </div>

    <section class="az-admin-panel is-active" data-tab-panel="properties">
        <div class="az-property-admin-grid">
            @forelse($properties as $property)
                <article class="az-property-admin-card">
                    <div class="az-property-admin-media">
                        @if($property->cover_image)
                            <img src="{{ Storage::url($property->cover_image) }}" alt="{{ $property->name }}">
                        @else
                            <span class="material-symbols-outlined">apartment</span>
                        @endif
                        <span class="az-status az-status--{{ $property->status }}">{{ ucfirst($property->status) }}</span>
                    </div>
                    <div class="az-property-admin-body">
                        <small>{{ $property->code }}</small>
                        <h2>{{ $property->name }}</h2>
                        <p>{{ $property->location }}, {{ $property->country }}</p>
                        <div class="az-property-facts">
                            <span>{{ $property->bedrooms }} beds</span>
                            <span>{{ $property->bathrooms }} baths</span>
                            <span>{{ $property->max_guests }} guests</span>
                        </div>
                        <div class="az-property-card-footer">
                            <strong>{{ $property->currency }} {{ number_format($property->nightly_rate) }}</strong>
                            <a class="button button-secondary" href="{{ route('azari.admin.inventory.properties.edit', $property) }}">Manage</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="production-empty-state">No properties have been created.</div>
            @endforelse
        </div>

        <div class="az-inventory-pagination">
            <span class="az-pagination-label">
                Properties · Page {{ $properties->currentPage() }} of {{ $properties->lastPage() }}
                · {{ $properties->count() }} shown of {{ $properties->total() }}
            </span>
            @if($properties->hasPages())
                {{ $properties->onEachSide(1)->links() }}
            @endif
        </div>
    </section>

    <section class="az-admin-panel" data-tab-panel="locations">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.inventory.locations.store') }}">
            @csrf
            <div class="az-form-section-head"><span class="material-symbols-outlined">location_city</span><div><h2>Add location</h2><p>Create an operational destination or branch.</p></div></div>
            <label class="az-field"><span>Name</span><input name="name" required></label>
            <label class="az-field"><span>Slug</span><input name="slug" placeholder="lagos"></label>
            <label class="az-field"><span>Country</span><input name="country" value="Nigeria" required></label>
            <label class="az-field"><span>City</span><input name="city" required></label>
            <label class="az-field az-span-2"><span>Address</span><textarea name="address" rows="3"></textarea></label>
            <label class="az-field"><span>Timezone</span><select name="timezone"><option value="Africa/Lagos">Africa/Lagos</option><option value="Africa/Kigali">Africa/Kigali</option><option value="UTC">UTC</option></select></label>
            <label class="az-toggle"><input type="checkbox" name="is_active" value="1" checked><span class="az-toggle-track"></span><span>Active</span></label>
            <div class="az-form-actions az-span-2"><button class="button button-primary">Create location</button></div>
        </form>
        <div class="az-list-grid">
            @foreach($locations as $location)
                <article class="az-summary-card"><span class="material-symbols-outlined">location_on</span><h3>{{ $location->name }}</h3><p>{{ $location->city }}, {{ $location->country }}</p><small>{{ $location->buildings_count }} buildings · {{ $location->properties_count }} properties</small></article>
            @endforeach
        </div>

        <div class="az-inventory-pagination">
            <span class="az-pagination-label">
                Locations · Page {{ $locations->currentPage() }} of {{ $locations->lastPage() }}
                · {{ $locations->count() }} shown of {{ $locations->total() }}
            </span>
            @if($locations->hasPages())
                {{ $locations->onEachSide(1)->links() }}
            @endif
        </div>
    </section>

    <section class="az-admin-panel" data-tab-panel="buildings">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.inventory.buildings.store') }}">
            @csrf
            <div class="az-form-section-head"><span class="material-symbols-outlined">domain</span><div><h2>Add building</h2><p>Group apartments and rooms within a location.</p></div></div>
            <label class="az-field"><span>Location</span><select name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
            <label class="az-field"><span>Building name</span><input name="name" required></label>
            <label class="az-field"><span>Unique code</span><input name="code" required></label>
            <label class="az-field"><span>Number of floors</span><input type="number" name="floors" min="1"></label>
            <label class="az-field az-span-2"><span>Description</span><textarea name="description" rows="4"></textarea></label>
            <label class="az-toggle az-span-2"><input type="checkbox" name="is_active" value="1" checked><span class="az-toggle-track"></span><span>Active</span></label>
            <div class="az-form-actions az-span-2"><button class="button button-primary">Create building</button></div>
        </form>
        <div class="az-list-grid">
            @foreach($buildings as $building)
                <article class="az-summary-card"><span class="material-symbols-outlined">apartment</span><h3>{{ $building->name }}</h3><p>{{ $building->location->name }}</p><small>{{ $building->properties_count }} properties</small></article>
            @endforeach
        </div>

        <div class="az-inventory-pagination">
            <span class="az-pagination-label">
                Buildings · Page {{ $buildings->currentPage() }} of {{ $buildings->lastPage() }}
                · {{ $buildings->count() }} shown of {{ $buildings->total() }}
            </span>
            @if($buildings->hasPages())
                {{ $buildings->onEachSide(1)->links() }}
            @endif
        </div>
    </section>

    <section class="az-admin-panel" data-tab-panel="types">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.inventory.room-types.store') }}">
            @csrf
            <div class="az-form-section-head"><span class="material-symbols-outlined">bed</span><div><h2>Add room type</h2><p>Central room and apartment category management.</p></div></div>
            <label class="az-field"><span>Name</span><input name="name" required></label>
            <label class="az-field"><span>Slug</span><input name="slug"></label>
            <label class="az-field"><span>Material icon</span><input name="icon" value="bed" required></label>
            <label class="az-toggle"><input type="checkbox" name="is_active" value="1" checked><span class="az-toggle-track"></span><span>Active</span></label>
            <label class="az-field az-span-2"><span>Description</span><textarea name="description" rows="4"></textarea></label>
            <div class="az-form-actions az-span-2"><button class="button button-primary">Create room type</button></div>
        </form>
        <div class="az-list-grid">
            @foreach($roomTypes as $type)
                <article class="az-summary-card"><span class="material-symbols-outlined">{{ $type->icon }}</span><h3>{{ $type->name }}</h3><p>{{ $type->description }}</p><small>{{ $type->properties_count }} properties</small></article>
            @endforeach
        </div>

        <div class="az-inventory-pagination">
            <span class="az-pagination-label">
                Room types · Page {{ $roomTypes->currentPage() }} of {{ $roomTypes->lastPage() }}
                · {{ $roomTypes->count() }} shown of {{ $roomTypes->total() }}
            </span>
            @if($roomTypes->hasPages())
                {{ $roomTypes->onEachSide(1)->links() }}
            @endif
        </div>
    </section>

    <section class="az-admin-panel" data-tab-panel="amenities">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.inventory.amenities.store') }}">
            @csrf
            <div class="az-form-section-head"><span class="material-symbols-outlined">checklist</span><div><h2>Add amenity</h2><p>Create reusable, sortable amenities.</p></div></div>
            <label class="az-field"><span>Name</span><input name="name" required></label>
            <label class="az-field"><span>Material icon</span><input name="icon" value="check_circle" required></label>
            <label class="az-field az-span-2"><span>Description</span><textarea name="description" rows="3"></textarea></label>
            <label class="az-toggle az-span-2"><input type="checkbox" name="is_active" value="1" checked><span class="az-toggle-track"></span><span>Published</span></label>
            <div class="az-form-actions az-span-2"><button class="button button-primary">Create amenity</button></div>
        </form>
        <div class="az-list-grid">
            @foreach($amenities as $amenity)
                <article class="az-summary-card"><span class="material-symbols-outlined">{{ $amenity->icon }}</span><h3>{{ $amenity->name }}</h3><p>{{ $amenity->description }}</p><small>{{ $amenity->properties_count }} assignments</small></article>
            @endforeach
        </div>

        <div class="az-inventory-pagination">
            <span class="az-pagination-label">
                Amenities · Page {{ $amenities->currentPage() }} of {{ $amenities->lastPage() }}
                · {{ $amenities->count() }} shown of {{ $amenities->total() }}
            </span>
            @if($amenities->hasPages())
                {{ $amenities->onEachSide(1)->links() }}
            @endif
        </div>
    </section>
@endsection
