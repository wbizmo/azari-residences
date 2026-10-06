@extends('admin.layouts.app')
@section('content')
<div class="admin-heading">
    <div><span>Inventory</span><h1>{{ $property->exists ? 'Edit property' : 'Add property' }}</h1></div>
    @if($property->exists)
        <a class="button button-secondary" href="{{ route('azari.admin.properties.commercial', $property) }}">
            Accommodation & rates
        </a>
    @endif
</div>

@if($locations->isEmpty() || $roomTypes->isEmpty())
    <div class="admin-card">
        <strong>Inventory setup required.</strong>
        <p>Add at least one location and one residence category before creating a property.</p>
        <a href="{{ route('azari.admin.locations.index') }}">Manage locations</a>
        <a href="{{ route('azari.admin.room-types.index') }}">Manage categories</a>
    </div>
@endif

@if($errors->any())
    <div class="admin-card" role="alert">
        <strong>Please correct the highlighted property fields.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form class="admin-form admin-form-grid" method="POST" enctype="multipart/form-data"
      action="{{ $property->exists ? route('azari.admin.properties.update', $property) : route('azari.admin.properties.store') }}">
    @csrf
    @if($property->exists) @method('PUT') @endif

    <label>Name<input name="name" value="{{ old('name', $property->name) }}" required></label>
    <label>Slug<input name="slug" value="{{ old('slug', $property->slug) }}"></label>

    <label>Location
        <select name="location_id" required>
            <option value="">Select location</option>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" @selected((string)old('location_id', $property->location_id) === (string)$location->id)>
                    {{ $location->name }} · {{ $location->city }}, {{ $location->country }}
                </option>
            @endforeach
        </select>
    </label>

    <label>Residence category
        <select name="room_type_id" required>
            <option value="">Select category</option>
            @foreach($roomTypes as $roomType)
                <option value="{{ $roomType->id }}" @selected((string)old('room_type_id', $property->room_type_id) === (string)$roomType->id)>
                    {{ $roomType->name }}
                </option>
            @endforeach
        </select>
    </label>

    <x-property-address-fields :source="$property" wrapper-class="admin-form-span-2" />

    <label>Bedrooms<input name="bedrooms" type="number" min="0" value="{{ old('bedrooms', $property->bedrooms ?? 1) }}" required></label>
    <label>Bathrooms<input name="bathrooms" type="number" min="1" value="{{ old('bathrooms', $property->bathrooms ?? 1) }}" required></label>
    <label>Maximum guests<input name="max_guests" type="number" min="1" value="{{ old('max_guests', $property->max_guests ?? 2) }}" required></label>
    <label>Minimum stay<input name="minimum_stay" type="number" min="1" value="{{ old('minimum_stay', $property->minimum_stay ?? 1) }}"></label>
    <label>Maximum stay<input name="maximum_stay" type="number" min="1" value="{{ old('maximum_stay', $property->maximum_stay) }}"></label>
    <label>Nightly rate<input name="nightly_rate" type="number" min="0" step="0.01" value="{{ old('nightly_rate', $property->nightly_rate) }}" required></label>
    <label>Weekend rate<input name="weekend_rate" type="number" min="0" step="0.01" value="{{ old('weekend_rate', $property->weekend_rate) }}"></label>
    <label>Cleaning fee<input name="cleaning_fee" type="number" min="0" step="0.01" value="{{ old('cleaning_fee', $property->cleaning_fee) }}"></label>
    <label>Service charge<input name="service_charge" type="number" min="0" step="0.01" value="{{ old('service_charge', $property->service_charge) }}"></label>
    <label>Tax rate (%)<input name="tax_rate" type="number" min="0" max="100" step="0.001" value="{{ old('tax_rate', $property->tax_rate) }}"></label>
    <label>Currency<select name="currency" required>@foreach(config('localization.supported_currencies',[]) as $code=>$name)<option value="{{ $code }}" @selected(old('currency',$property->currency ?: config('localization.default_currency'))===$code)>{{ $code }} · {{ $name }}</option>@endforeach</select></label>
    <label>Property timezone<input name="timezone" value="{{ old('timezone',$property->timezone ?: $property->locationRecord?->timezone) }}" placeholder="Africa/Lagos"><small>Check-in and check-out date boundaries use this timezone.</small></label>

    <label class="admin-form-span-2">Short description
        <textarea name="short_description">{{ old('short_description', $property->short_description) }}</textarea>
    </label>
    <label class="admin-form-span-2">Description
        <textarea name="description">{{ old('description', $property->description) }}</textarea>
    </label>

    <label>Cover image<input name="cover_image" type="file" accept="image/*"></label>
    <label>Gallery images<input name="gallery[]" type="file" accept="image/*" multiple></label>
    <label>Sort order<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $property->sort_order ?? 0) }}"></label>

    <label><input type="checkbox" name="same_day_booking" value="1" @checked(old('same_day_booking', $property->same_day_booking))> Allow same-day booking</label>
    <label><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $property->is_featured))> Featured</label>
    <label><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $property->is_published))> Published</label>

    <fieldset class="admin-form-span-2">
        <legend>Amenities</legend>
        @foreach($amenities as $amenity)
            <label>
                <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}"
                       @checked(in_array($amenity->id, old('amenities', $property->amenities->pluck('id')->all()), true))>
                {{ $amenity->name }}
            </label>
        @endforeach
    </fieldset>

    <button class="button button-primary" type="submit"
            @disabled($locations->isEmpty() || $roomTypes->isEmpty())>
        Save property
    </button>
</form>
@endsection
