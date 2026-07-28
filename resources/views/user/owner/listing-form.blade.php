@extends('layouts.user')
@section('title',$listing->exists?'Edit Property Listing':'List Your Property')
@section('content')
<div class="az-premium-page-head"><div><span class="az-premium-kicker">Property submission</span><h1>{{ $listing->exists?'Correct and resubmit':'List your property' }}</h1><p>This form mirrors Azari inventory fields so approval can create the property without manual re-entry.</p></div></div>
<form class="az-premium-card az-s78-form" method="POST" enctype="multipart/form-data" action="{{ $listing->exists?route('user.owner.listings.update',$listing):route('user.owner.listings.store') }}">
@csrf @if($listing->exists) @method('PUT') @endif
<div class="az-s78-form-grid">
<label class="az-s78-field"><span>Property name</span><input name="name" value="{{ old('name',data_get($data,'name')) }}" required></label>
<label class="az-s78-field"><span>Location</span><select name="location_id" required><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)old('location_id',data_get($data,'location_id'))===(string)$location->id)>{{ $location->name }} · {{ $location->city }}, {{ $location->country }}</option>@endforeach</select></label>
<label class="az-s78-field"><span>Residence category</span><select name="room_type_id" required><option value="">Select category</option>@foreach($roomTypes as $type)<option value="{{ $type->id }}" @selected((string)old('room_type_id',data_get($data,'room_type_id'))===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></label>
<label class="az-s78-field"><span>Bedrooms</span><input type="number" name="bedrooms" min="0" value="{{ old('bedrooms',data_get($data,'bedrooms',1)) }}" required></label>
<label class="az-s78-field"><span>Bathrooms</span><input type="number" name="bathrooms" min="1" value="{{ old('bathrooms',data_get($data,'bathrooms',1)) }}" required></label>
<label class="az-s78-field"><span>Maximum guests</span><input type="number" name="max_guests" min="1" value="{{ old('max_guests',data_get($data,'max_guests',2)) }}" required></label>
<label class="az-s78-field"><span>Minimum stay</span><input type="number" name="minimum_stay" min="1" value="{{ old('minimum_stay',data_get($data,'minimum_stay',1)) }}"></label>
<label class="az-s78-field"><span>Maximum stay</span><input type="number" name="maximum_stay" min="1" value="{{ old('maximum_stay',data_get($data,'maximum_stay')) }}"></label>
<label class="az-s78-field"><span>Nightly rate</span><input type="number" step=".01" min="0" name="nightly_rate" value="{{ old('nightly_rate',data_get($data,'nightly_rate')) }}" required></label>
<label class="az-s78-field"><span>Weekend rate</span><input type="number" step=".01" min="0" name="weekend_rate" value="{{ old('weekend_rate',data_get($data,'weekend_rate')) }}"></label>
<label class="az-s78-field"><span>Cleaning fee</span><input type="number" step=".01" min="0" name="cleaning_fee" value="{{ old('cleaning_fee',data_get($data,'cleaning_fee')) }}"></label>
<label class="az-s78-field"><span>Service charge</span><input type="number" step=".01" min="0" name="service_charge" value="{{ old('service_charge',data_get($data,'service_charge')) }}"></label>
<label class="az-s78-field"><span>Tax rate (%)</span><input type="number" step=".001" min="0" max="100" name="tax_rate" value="{{ old('tax_rate',data_get($data,'tax_rate')) }}"></label>
<label class="az-s78-field"><span>Currency</span><input name="currency" maxlength="3" value="{{ old('currency',data_get($data,'currency','USD')) }}" required></label>
<label class="az-s78-field is-full"><span>Short description</span><textarea name="short_description" required>{{ old('short_description',data_get($data,'short_description')) }}</textarea></label>
<label class="az-s78-field is-full"><span>Full description</span><textarea name="description" required>{{ old('description',data_get($data,'description')) }}</textarea></label>
<label class="az-s78-field"><span>Cover image {{ $listing->cover_image?'(leave blank to retain current)':'' }}</span><input type="file" name="cover_image" accept="image/*" {{ $listing->cover_image?'':'required' }}></label>
<label class="az-s78-field"><span>Gallery images</span><input type="file" name="gallery[]" accept="image/*" multiple></label>
<label class="az-s78-field"><span>Proposed owner share (%)</span><input type="number" step=".01" min="0" max="100" name="proposed_owner_share_percentage" value="{{ old('proposed_owner_share_percentage',$listing->proposed_owner_share_percentage) }}"></label>
<label class="az-s78-field is-full"><span>Notes for Azari review</span><textarea name="owner_notes">{{ old('owner_notes',$listing->owner_notes) }}</textarea></label>
</div>
<fieldset class="az-s78-permissions"><legend>Amenities</legend>@foreach($amenities as $amenity)<label class="az-s78-check"><input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id,old('amenities',$listing->amenity_ids??[])))><span>{{ $amenity->name }}</span></label>@endforeach</fieldset>
<div class="az-premium-actions"><button class="az-premium-button" type="submit">{{ $listing->exists?'Resubmit property':'Submit for review' }}</button></div>
</form>
@endsection
