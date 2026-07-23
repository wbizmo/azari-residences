<x-admin.layout :title="$property->exists ? 'Edit property' : 'Add property'">
    <div class="admin-heading">
        <div><span>PROPERTY EDITOR</span><h1>{{ $property->exists ? $property->name : 'Add property' }}</h1><p>Every control below uses the Azari administration design system.</p></div>
        <a class="button button-secondary" href="{{ route('azari.admin.inventory.index') }}">Back to inventory</a>
    </div>

    <form class="admin-form az-form-grid" method="POST" enctype="multipart/form-data" action="{{ $property->exists ? route('azari.admin.inventory.properties.update', $property) : route('azari.admin.inventory.properties.store') }}">
        @csrf
        @if($property->exists) @method('PUT') @endif

        <div class="az-form-section-head"><span class="material-symbols-outlined">apartment</span><div><h2>Identity and hierarchy</h2><p>Location, building, room type and internal references.</p></div></div>

        <label class="az-field"><span>Property name</span><input name="name" value="{{ old('name', $property->name) }}" required></label>
        <label class="az-field"><span>URL slug</span><input name="slug" value="{{ old('slug', $property->slug) }}"></label>
        <label class="az-field"><span>Unique code</span><input name="code" value="{{ old('code', $property->code) }}"></label>
        <label class="az-field"><span>Unit / apartment number</span><input name="unit_number" value="{{ old('unit_number', $property->unit_number) }}"></label>

        <label class="az-field"><span>Location record</span><select name="location_id"><option value="">Not assigned</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id', $property->location_id) == $location->id)>{{ $location->name }}</option>@endforeach</select></label>
        <label class="az-field"><span>Building</span><select name="building_id"><option value="">Not assigned</option>@foreach($buildings as $building)<option value="{{ $building->id }}" @selected(old('building_id', $property->building_id) == $building->id)>{{ $building->name }}</option>@endforeach</select></label>
        <label class="az-field"><span>Room type</span><select name="room_type_id"><option value="">Not assigned</option>@foreach($roomTypes as $type)<option value="{{ $type->id }}" @selected(old('room_type_id', $property->room_type_id) == $type->id)>{{ $type->name }}</option>@endforeach</select></label>
        <label class="az-field"><span>Floor</span><input name="floor" value="{{ old('floor', $property->floor) }}"></label>

        <label class="az-field"><span>Display location</span><input name="location" value="{{ old('location', $property->location) }}" required></label>
        <label class="az-field"><span>Country</span><input name="country" value="{{ old('country', $property->country ?: 'Nigeria') }}" required></label>
        <label class="az-field az-span-2"><span>Property type label</span><input name="property_type" value="{{ old('property_type', $property->property_type ?: 'Apartment') }}" required></label>

        <div class="az-form-section-head"><span class="material-symbols-outlined">groups</span><div><h2>Capacity and stay details</h2><p>Guest limits, beds, bathrooms and operational times.</p></div></div>

        @foreach([
            'bedrooms' => ['Bedrooms', 1],
            'bathrooms' => ['Bathrooms', 1],
            'max_guests' => ['Maximum guests', 2],
            'adult_capacity' => ['Adult capacity', 2],
            'child_capacity' => ['Child capacity', 0],
        ] as $name => [$label, $default])
            <label class="az-field"><span>{{ $label }}</span><input type="number" min="0" name="{{ $name }}" value="{{ old($name, $property->{$name} ?? $default) }}" required></label>
        @endforeach

        <label class="az-field"><span>Bed configuration</span><input name="bed_configuration" value="{{ old('bed_configuration', $property->bed_configuration) }}" placeholder="1 king bed, 2 single beds"></label>
        <label class="az-field"><span>Room size (m²)</span><input type="number" step="0.01" min="0" name="room_size" value="{{ old('room_size', $property->room_size) }}"></label>
        <label class="az-field"><span>Check-in time</span><input type="time" name="check_in_time" value="{{ old('check_in_time', $property->check_in_time) }}"></label>
        <label class="az-field"><span>Check-out time</span><input type="time" name="check_out_time" value="{{ old('check_out_time', $property->check_out_time) }}"></label>

        <div class="az-form-section-head"><span class="material-symbols-outlined">payments</span><div><h2>Base pricing</h2><p>Seasonal and promotional rules can be added after saving.</p></div></div>

        <label class="az-field"><span>Currency</span><select name="currency"><option value="NGN" @selected(old('currency', $property->currency ?: 'NGN') === 'NGN')>NGN</option><option value="RWF" @selected(old('currency', $property->currency) === 'RWF')>RWF</option><option value="USD" @selected(old('currency', $property->currency) === 'USD')>USD</option></select></label>
        <label class="az-field"><span>Nightly rate</span><input type="number" step="0.01" min="0" name="nightly_rate" value="{{ old('nightly_rate', $property->nightly_rate ?: 0) }}" required></label>
        <label class="az-field"><span>Weekend rate</span><input type="number" step="0.01" min="0" name="weekend_rate" value="{{ old('weekend_rate', $property->weekend_rate) }}"></label>
        <label class="az-field"><span>Cleaning fee</span><input type="number" step="0.01" min="0" name="cleaning_fee" value="{{ old('cleaning_fee', $property->cleaning_fee ?: 0) }}"></label>
        <label class="az-field"><span>Security deposit</span><input type="number" step="0.01" min="0" name="security_deposit" value="{{ old('security_deposit', $property->security_deposit ?: 0) }}"></label>
        <label class="az-field"><span>Service charge</span><input type="number" step="0.01" min="0" name="service_charge" value="{{ old('service_charge', $property->service_charge ?: 0) }}"></label>
        <label class="az-field"><span>Tax rate (%)</span><input type="number" step="0.001" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', $property->tax_rate ?: 0) }}"></label>

        <div class="az-form-section-head"><span class="material-symbols-outlined">description</span><div><h2>Public content</h2><p>Descriptions, video and virtual-tour links.</p></div></div>

        <label class="az-field az-span-2"><span>Short description</span><textarea name="short_description" rows="3">{{ old('short_description', $property->short_description) }}</textarea></label>
        <label class="az-field az-span-2"><span>Full description</span><textarea name="description" rows="9">{{ old('description', $property->description) }}</textarea></label>
        <label class="az-field"><span>Video URL</span><input type="url" name="video_url" value="{{ old('video_url', $property->video_url) }}"></label>
        <label class="az-field"><span>Virtual tour URL</span><input type="url" name="virtual_tour_url" value="{{ old('virtual_tour_url', $property->virtual_tour_url) }}"></label>

        <div class="az-form-section-head"><span class="material-symbols-outlined">photo_library</span><div><h2>Media and gallery</h2><p>Upload a cover image and multiple sortable gallery images.</p></div></div>

        <label class="az-upload"><span class="material-symbols-outlined">add_photo_alternate</span><strong>Cover image</strong><small data-file-label>Select image</small><input type="file" name="cover_image" accept="image/*"></label>
        <label class="az-upload"><span class="material-symbols-outlined">collections</span><strong>Gallery images</strong><small data-file-label>Select up to 20 images</small><input type="file" name="gallery_images[]" accept="image/*" multiple></label>

        <div class="az-form-section-head"><span class="material-symbols-outlined">checklist</span><div><h2>Amenities and publication</h2><p>Assign central amenities and control visibility.</p></div></div>

        <fieldset class="az-checkbox-grid az-span-2">
            <legend>Amenities</legend>
            @foreach($amenities as $amenity)
                <label><input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id, old('amenities', $property->amenities->pluck('id')->all() ?? []), true))><span class="material-symbols-outlined">{{ $amenity->icon }}</span><strong>{{ $amenity->name }}</strong></label>
            @endforeach
        </fieldset>

        <label class="az-field"><span>Operational status</span><select name="status"><option value="available" @selected(old('status', $property->status ?: 'available') === 'available')>Available</option><option value="unavailable" @selected(old('status', $property->status) === 'unavailable')>Unavailable</option><option value="maintenance" @selected(old('status', $property->status) === 'maintenance')>Maintenance</option><option value="archived" @selected(old('status', $property->status) === 'archived')>Archived</option></select></label>
        <div class="az-toggle-stack">
            <label class="az-toggle"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $property->is_featured))><span class="az-toggle-track"></span><span>Featured residence</span></label>
            <label class="az-toggle"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $property->is_published))><span class="az-toggle-track"></span><span>Published publicly</span></label>
        </div>

        <label class="az-field az-span-2"><span>Internal notes</span><textarea name="internal_notes" rows="5">{{ old('internal_notes', $property->internal_notes) }}</textarea></label>

        <div class="az-form-actions az-span-2">
            <button class="button button-primary" type="submit">{{ $property->exists ? 'Save property changes' : 'Create property' }}</button>
        </div>
    </form>

    @if($property->exists)
        <section class="az-admin-subsection">
            <div class="az-form-section-head"><span class="material-symbols-outlined">photo_library</span><div><h2>Sortable gallery</h2><p>Drag images to change their public order.</p></div></div>
            <form method="POST" action="{{ route('azari.admin.inventory.images.sort', $property) }}" data-sort-form>
                @csrf
                @method('PUT')
                <div class="az-gallery-sortable" data-sortable>
                    @foreach($property->images as $image)
                        <article draggable="true" data-sort-id="{{ $image->id }}">
                            <img src="{{ Storage::url($image->path) }}" alt="{{ $image->alt_text }}">
                            <span class="material-symbols-outlined az-drag-handle">drag_indicator</span>
                            <button class="az-icon-button" type="submit" form="delete-image-{{ $image->id }}"><span class="material-symbols-outlined">delete</span></button>
                        </article>
                    @endforeach
                </div>
                <div data-sort-inputs></div>
                <button class="button button-secondary">Save gallery order</button>
            </form>
            @foreach($property->images as $image)
                <form id="delete-image-{{ $image->id }}" method="POST" action="{{ route('azari.admin.inventory.images.delete', $image) }}">@csrf @method('DELETE')</form>
            @endforeach
        </section>

        <section class="az-admin-subsection">
            <div class="az-form-section-head"><span class="material-symbols-outlined">calendar_month</span><div><h2>Dynamic pricing rules</h2><p>Seasonal, weekend, promotional and stay-length adjustments.</p></div></div>
            <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.inventory.pricing.store', $property) }}">
                @csrf
                <label class="az-field"><span>Rule name</span><input name="name" required></label>
                <label class="az-field"><span>Rule type</span><select name="rule_type"><option value="seasonal">Seasonal</option><option value="weekend">Weekend</option><option value="promotion">Promotion</option><option value="minimum_stay">Minimum stay</option></select></label>
                <label class="az-field"><span>Starts on</span><input type="date" name="starts_on"></label>
                <label class="az-field"><span>Ends on</span><input type="date" name="ends_on"></label>
                <label class="az-field"><span>Fixed amount</span><input type="number" step="0.01" name="amount"></label>
                <label class="az-field"><span>Percentage adjustment</span><input type="number" step="0.001" name="percentage"></label>
                <label class="az-field"><span>Minimum stay</span><input type="number" min="1" name="minimum_stay"></label>
                <label class="az-field"><span>Maximum stay</span><input type="number" min="1" name="maximum_stay"></label>
                <label class="az-field"><span>Priority</span><input type="number" min="0" name="priority" value="0" required></label>
                <label class="az-toggle"><input type="checkbox" name="is_active" value="1" checked><span class="az-toggle-track"></span><span>Active</span></label>
                <fieldset class="az-checkbox-grid az-span-2"><legend>Days of week</legend>@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day => $label)<label><input type="checkbox" name="days_of_week[]" value="{{ $day }}"><strong>{{ $label }}</strong></label>@endforeach</fieldset>
                <div class="az-form-actions az-span-2"><button class="button button-primary">Add pricing rule</button></div>
            </form>

            <div class="az-list-grid">
                @foreach($property->pricingRules as $rule)
                    <article class="az-summary-card">
                        <span class="az-status">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span>
                        <h3>{{ $rule->name }}</h3>
                        <p>{{ ucfirst($rule->rule_type) }}</p>
                        <small>Priority {{ $rule->priority }}</small>
                        <form method="POST" action="{{ route('azari.admin.inventory.pricing.delete', $rule) }}">@csrf @method('DELETE')<button class="az-icon-button"><span class="material-symbols-outlined">delete</span></button></form>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</x-admin.layout>
