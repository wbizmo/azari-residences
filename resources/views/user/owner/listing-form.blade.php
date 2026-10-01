@extends('layouts.user')
@section('title',$listing->exists?'Edit Property Listing':'List Your Property')
@section('kicker','Property submission')
@section('page_title',$listing->exists?'Correct and resubmit':'List your property')
@section('content')
<div class="az-premium-page-head">
    <div>
        <span class="az-premium-kicker">Owner marketplace</span>
        <h1>{{ $listing->exists?'Correct and resubmit':'List your property' }}</h1>
        <p>Provide the exact property details and location. Approved listings can become bookable without duplicate data entry.</p>
    </div>
</div>

@if($errors->any())
<div class="az-user-alert az-user-alert--danger" role="alert">
    <strong>Please correct the highlighted fields.</strong>
    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<form class="az-premium-card az-s78-form" method="POST" enctype="multipart/form-data"
      action="{{ $listing->exists?route('user.owner.listings.update',$listing):route('user.owner.listings.store') }}">
    @csrf
    @if($listing->exists) @method('PUT') @endif

    @php
        $field = fn(string $name) => $errors->has($name) ? ' az-s78-field--error' : '';
    @endphp

    <div class="az-s78-form-grid">
        <label class="az-s78-field{{ $field('name') }}"><span>Property name</span><input name="name" value="{{ old('name',data_get($data,'name')) }}" required>@error('name')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('location_id') }}"><span>Location</span><select name="location_id" required><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)old('location_id',data_get($data,'location_id'))===(string)$location->id)>{{ $location->name }} · {{ $location->city }}, {{ $location->country }}</option>@endforeach</select>@error('location_id')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('room_type_id') }}"><span>Residence category</span><select name="room_type_id" required><option value="">Select category</option>@foreach($roomTypes as $type)<option value="{{ $type->id }}" @selected((string)old('room_type_id',data_get($data,'room_type_id'))===(string)$type->id)>{{ $type->name }}</option>@endforeach</select>@error('room_type_id')<small>{{ $message }}</small>@enderror</label>

        <x-property-address-fields :source="$data" wrapper-class="is-full" label-class="az-s78-field is-full" />
        @error('formatted_address')<small class="az-s78-field is-full">{{ $message }}</small>@enderror

        <label class="az-s78-field{{ $field('bedrooms') }}"><span>Bedrooms</span><input type="number" name="bedrooms" min="0" value="{{ old('bedrooms',data_get($data,'bedrooms',1)) }}" required>@error('bedrooms')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('bathrooms') }}"><span>Bathrooms</span><input type="number" name="bathrooms" min="1" value="{{ old('bathrooms',data_get($data,'bathrooms',1)) }}" required>@error('bathrooms')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('max_guests') }}"><span>Maximum guests</span><input type="number" name="max_guests" min="1" value="{{ old('max_guests',data_get($data,'max_guests',2)) }}" required>@error('max_guests')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('minimum_stay') }}"><span>Minimum stay</span><input type="number" name="minimum_stay" min="1" value="{{ old('minimum_stay',data_get($data,'minimum_stay',1)) }}">@error('minimum_stay')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('maximum_stay') }}"><span>Maximum stay</span><input type="number" name="maximum_stay" min="1" value="{{ old('maximum_stay',data_get($data,'maximum_stay')) }}">@error('maximum_stay')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('nightly_rate') }}"><span>Nightly rate (USD)</span><input type="number" step=".01" min="0" name="nightly_rate" value="{{ old('nightly_rate',data_get($data,'nightly_rate')) }}" required>@error('nightly_rate')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('weekend_rate') }}"><span>Weekend rate (USD)</span><input type="number" step=".01" min="0" name="weekend_rate" value="{{ old('weekend_rate',data_get($data,'weekend_rate')) }}">@error('weekend_rate')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('cleaning_fee') }}"><span>Cleaning fee (USD)</span><input type="number" step=".01" min="0" name="cleaning_fee" value="{{ old('cleaning_fee',data_get($data,'cleaning_fee')) }}">@error('cleaning_fee')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('service_charge') }}"><span>Service charge (USD)</span><input type="number" step=".01" min="0" name="service_charge" value="{{ old('service_charge',data_get($data,'service_charge')) }}">@error('service_charge')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('tax_rate') }}"><span>Tax rate (%)</span><input type="number" step=".001" min="0" max="100" name="tax_rate" value="{{ old('tax_rate',data_get($data,'tax_rate')) }}">@error('tax_rate')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field"><span>Currency</span><input value="{{ $currency }}" readonly disabled><small>Azari property pricing is locked to USD.</small></label>
        <label class="az-s78-field"><span>Owner revenue</span><input value="No Azari platform commission" readonly disabled><small>Azari does not deduct a platform percentage from owner-property room sales.</small></label>

        <label class="az-s78-field is-full{{ $field('short_description') }}"><span>Short description</span><textarea name="short_description" required>{{ old('short_description',data_get($data,'short_description')) }}</textarea>@error('short_description')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field is-full{{ $field('description') }}"><span>Full description</span><textarea name="description" required>{{ old('description',data_get($data,'description')) }}</textarea>@error('description')<small>{{ $message }}</small>@enderror</label>

        <div class="az-s78-field is-full">
            <span>Current cover</span>
            @if($listing->cover_image)
                <img class="az-owner-media-preview az-owner-media-preview--cover" src="{{ Storage::disk('public')->url($listing->cover_image) }}" alt="Current property cover">
            @else
                <p>No cover uploaded yet.</p>
            @endif
        </div>
        <label class="az-s78-field is-full{{ $field('cover_image') }}"><span>{{ $listing->cover_image?'Replace cover image':'Cover image' }}</span><input type="file" name="cover_image" accept="image/*" {{ $listing->cover_image?'':'required' }}>@error('cover_image')<small>{{ $message }}</small>@enderror</label>

        <div class="az-s78-field is-full">
            <span>Current gallery</span>
            @if(count($listing->gallery ?? []))
                <div class="az-owner-gallery-manager">
                    @foreach($listing->gallery as $image)
                        <label class="az-owner-gallery-item">
                            <img src="{{ Storage::disk('public')->url($image) }}" alt="Property gallery image">
                            <span><input type="checkbox" name="remove_gallery[]" value="{{ $image }}"> Remove</span>
                        </label>
                    @endforeach
                </div>
            @else
                <p>No gallery images yet.</p>
            @endif
        </div>
        <label class="az-s78-field is-full"><span>Add gallery images</span><input type="file" name="gallery[]" accept="image/*" multiple>@error('gallery.*')<small>{{ $message }}</small>@enderror</label>

        <label class="az-s78-field is-full{{ $field('owner_notes') }}"><span>Notes for Azari review</span><textarea name="owner_notes">{{ old('owner_notes',$listing->owner_notes) }}</textarea>@error('owner_notes')<small>{{ $message }}</small>@enderror</label>
    </div>

    <fieldset class="az-s78-permissions">
        <legend>Amenities</legend>
        @foreach($amenities as $amenity)
            <label class="az-s78-check"><input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id,old('amenities',$listing->amenity_ids??[])))><span>{{ $amenity->name }}</span></label>
        @endforeach
        @error('amenities.*')<small>{{ $message }}</small>@enderror
    </fieldset>

    <div class="az-premium-actions"><button class="az-premium-button" type="submit">{{ $listing->exists?'Resubmit property':'Submit for review' }}</button></div>
</form>
@endsection
