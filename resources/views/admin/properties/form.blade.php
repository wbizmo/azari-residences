@extends('admin.layouts.app')
@section('content')exists ? 'Edit property' : 'Add property'">
    <div class="admin-heading"><div><span>Inventory</span><h1>{{ $property->exists ? 'Edit property' : 'Add property' }}</h1></div></div>
    <form class="admin-form admin-form-grid" method="POST" enctype="multipart/form-data"
          action="{{ $property->exists ? route('azari.admin.properties.update', $property) : route('azari.admin.properties.store') }}">
        @csrf
        @if($property->exists) @method('PUT') @endif
        <label>Name<input name="name" value="{{ old('name', $property->name) }}" required></label>
        <label>Slug<input name="slug" value="{{ old('slug', $property->slug) }}"></label>
        <label>Location<input name="location" value="{{ old('location', $property->location) }}" required></label>
        <label>Country<input name="country" value="{{ old('country', $property->country ?: 'Nigeria') }}" required></label>
        <label>Property type<input name="property_type" value="{{ old('property_type', $property->property_type) }}" required></label>
        <label>Bedrooms<input type="number" name="bedrooms" min="0" value="{{ old('bedrooms', $property->bedrooms ?: 1) }}" required></label>
        <label>Bathrooms<input type="number" name="bathrooms" min="1" value="{{ old('bathrooms', $property->bathrooms ?: 1) }}" required></label>
        <label>Maximum guests<input type="number" name="max_guests" min="1" value="{{ old('max_guests', $property->max_guests ?: 2) }}" required></label>
        <label>Nightly rate<input type="number" name="nightly_rate" min="0" step="0.01" value="{{ old('nightly_rate', $property->nightly_rate ?: 0) }}" required></label>
        <label>Currency<input name="currency" maxlength="3" value="{{ old('currency', $property->currency ?: 'NGN') }}" required></label>
        <label>Sort order<input type="number" name="sort_order" min="0" value="{{ old('sort_order', $property->sort_order ?: 0) }}"></label>
        <label>Cover image<input type="file" name="cover_image" accept="image/*"></label>
        <label>Gallery images<input type="file" name="gallery[]" accept="image/*" multiple></label>
        <label class="admin-span-2">Short description<textarea name="short_description" rows="3">{{ old('short_description', $property->short_description) }}</textarea></label>
        <label class="admin-span-2">Full description<textarea name="description" rows="8">{{ old('description', $property->description) }}</textarea></label>
        <fieldset class="admin-span-2">
            <legend>Amenities</legend>
            <div class="admin-check-grid">
                @foreach($amenities as $amenity)
                    <label class="admin-checkbox">
                        <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}"
                               @checked(in_array($amenity->id, old('amenities', $property->amenities->pluck('id')->all())))>
                        {{ $amenity->name }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <label class="admin-checkbox"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $property->is_featured))> Featured on homepage</label>
        <label class="admin-checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $property->is_published))> Published</label>
        <div class="admin-span-2"><button class="button button-primary" type="submit">Save property</button></div>
    </form>
@endsection
