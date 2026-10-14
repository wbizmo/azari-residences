@extends('layouts.user')
@section('title',$listing->exists?'Edit Property Listing':'List Your Property')
@section('kicker','Property submission')
@section('page_title',$listing->exists?'Complete your application':'List your property')
@section('content')
<div class="az-premium-page-head">
    <div>
        <span class="az-premium-kicker">Owner marketplace</span>
        <h1>{{ $listing->exists?'Continue your application':'List your property' }}</h1>
        <p>Start with a private draft, then submit your completed hotel or property application for manual review. No map provider, payout gateway or automated ID check is required to submit.</p>
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

    <fieldset class="az-s78-permissions" style="margin-bottom:20px">
        <legend>How will you operate this property?</legend>
        <p>This information helps Resavar review your legal authority to host guests. Choose the arrangement that applies to you.</p>
        <label class="az-s78-field">
            <span>Applicant type</span>
            <select name="operator_type" id="resavar-operator-type" required>
                <option value="individual" @selected(old('operator_type', $listing->operator_type ?? 'individual') === 'individual')>Individual property owner or authorised host</option>
                <option value="hotel_operator" @selected(old('operator_type', $listing->operator_type) === 'hotel_operator')>Hotel or hospitality business operator</option>
            </select>
        </label>
        <div id="resavar-hotel-business" style="margin-top:12px">
            <p>Hotel and hospitality businesses must identify the operating legal entity, registration and reachable contact person before submission.</p>
            <div class="az-s78-form-grid">
                <label class="az-s78-field"><span>Registered business / hotel operator name</span>
                    <input name="business_legal_name" value="{{ old('business_legal_name', $listing->business_legal_name) }}" maxlength="180" data-business-required>
                </label>
                <label class="az-s78-field"><span>Business registration number</span>
                    <input name="business_registration_number" value="{{ old('business_registration_number', $listing->business_registration_number) }}" maxlength="100" data-business-required>
                </label>
                <label class="az-s78-field"><span>Business contact email</span>
                    <input type="email" name="business_email" value="{{ old('business_email', $listing->business_email) }}" maxlength="255" data-business-required>
                </label>
                <label class="az-s78-field"><span>Business contact telephone</span>
                    <input type="tel" name="business_phone" value="{{ old('business_phone', $listing->business_phone) }}" maxlength="40" data-business-required>
                </label>
                <label class="az-s78-field is-full"><span>Business address (optional)</span>
                    <input name="business_address" value="{{ old('business_address', $listing->business_address) }}" maxlength="500">
                </label>
            </div>
        </div>
    </fieldset>

    <div class="az-s78-form-grid">
        <label class="az-s78-field{{ $field('name') }}"><span>Property name</span><input name="name" value="{{ old('name',data_get($data,'name')) }}" required>@error('name')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('location_id') }}"><span>Location</span><select name="location_id" required><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)old('location_id',data_get($data,'location_id'))===(string)$location->id)>{{ $location->name }} · {{ $location->city }}, {{ $location->country }}</option>@endforeach</select>@error('location_id')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('room_type_id') }}"><span>Accommodation category</span><select name="room_type_id" required><option value="">Select category</option>@foreach($roomTypes as $type)<option value="{{ $type->id }}" @selected((string)old('room_type_id',data_get($data,'room_type_id'))===(string)$type->id)>{{ $type->name }}</option>@endforeach</select>@error('room_type_id')<small>{{ $message }}</small>@enderror</label>

        <x-property-address-fields :source="$data" wrapper-class="is-full" label-class="az-s78-field is-full" />
        @error('formatted_address')<small class="az-s78-field is-full">{{ $message }}</small>@enderror

        <label class="az-s78-field{{ $field('bedrooms') }}"><span>Bedrooms</span><input type="number" name="bedrooms" min="0" value="{{ old('bedrooms',data_get($data,'bedrooms',1)) }}" required>@error('bedrooms')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('bathrooms') }}"><span>Bathrooms</span><input type="number" name="bathrooms" min="0" value="{{ old('bathrooms',data_get($data,'bathrooms',1)) }}" required>@error('bathrooms')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('max_guests') }}"><span>Maximum guests</span><input type="number" name="max_guests" min="1" value="{{ old('max_guests',data_get($data,'max_guests',2)) }}" required>@error('max_guests')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('minimum_stay') }}"><span>Minimum stay</span><input type="number" name="minimum_stay" min="1" value="{{ old('minimum_stay',data_get($data,'minimum_stay',1)) }}">@error('minimum_stay')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('maximum_stay') }}"><span>Maximum stay</span><input type="number" name="maximum_stay" min="1" value="{{ old('maximum_stay',data_get($data,'maximum_stay')) }}">@error('maximum_stay')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('nightly_rate') }}"><span>Nightly rate</span><input type="number" step=".01" min="0" name="nightly_rate" value="{{ old('nightly_rate',data_get($data,'nightly_rate')) }}" required>@error('nightly_rate')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('weekend_rate') }}"><span>Weekend rate</span><input type="number" step=".01" min="0" name="weekend_rate" value="{{ old('weekend_rate',data_get($data,'weekend_rate')) }}">@error('weekend_rate')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('cleaning_fee') }}"><span>Cleaning fee</span><input type="number" step=".01" min="0" name="cleaning_fee" value="{{ old('cleaning_fee',data_get($data,'cleaning_fee')) }}">@error('cleaning_fee')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('service_charge') }}"><span>Service charge</span><input type="number" step=".01" min="0" name="service_charge" value="{{ old('service_charge',data_get($data,'service_charge')) }}">@error('service_charge')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('tax_rate') }}"><span>Tax rate (%)</span><input type="number" step=".001" min="0" max="100" name="tax_rate" value="{{ old('tax_rate',data_get($data,'tax_rate')) }}">@error('tax_rate')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field{{ $field('currency') }}"><span>Currency</span><select name="currency" required>@foreach($currencies as $code=>$name)<option value="{{ $code }}" @selected(old('currency',data_get($data,'currency',$currency))===$code)>{{ $code }} · {{ $name }}</option>@endforeach</select>@error('currency')<small>{{ $message }}</small>@enderror</label><label class="az-s78-field{{ $field('timezone') }}"><span>Property timezone</span><input name="timezone" value="{{ old('timezone',data_get($data,'timezone')) }}" placeholder="Africa/Lagos"><small>Leave blank to use the selected location timezone.</small>@error('timezone')<small>{{ $message }}</small>@enderror</label>
        <label class="az-s78-field"><span>Owner revenue</span><input value="No Resavar platform commission" readonly disabled><small>Resavar does not deduct a platform percentage from owner-property room sales.</small></label>

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
        <label class="az-s78-field is-full{{ $field('cover_image') }}"><span>{{ $listing->cover_image?'Replace cover image':'Cover image' }}</span><input type="file" name="cover_image" accept="image/*">@error('cover_image')<small>{{ $message }}</small>@enderror</label>

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

        <fieldset class="az-s78-field is-full" style="border:1px solid #D0DBEA;border-radius:12px;padding:16px;min-width:0">
            <legend><strong>Private proof of ownership or management authority</strong></legend>
            <p>Upload a deed, lease, authorisation letter or other genuine evidence that you may offer this accommodation. Only you and authorised Resavar reviewers can download it. It will never appear on the public property page.</p>
            <label>Proof document (PDF, JPEG, PNG or WebP, maximum 10 MB)
                <input type="file" name="proof_of_control" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
            </label>
            @error('proof_of_control')<small role="alert">{{ $message }}</small>@enderror
            @if($listing->exists && $listing->controlDocuments()->exists())
                <ul>
                    @foreach($listing->controlDocuments()->latest('id')->get() as $proof)
                        <li><a href="{{ route('user.owner.listings.control-documents.download', [$listing, $proof]) }}">{{ $proof->original_name }}</a>
                            · {{ Str::headline($proof->status) }}
                            @if($proof->status === 'rejected' && $proof->review_note)
                                · {{ $proof->review_note }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </fieldset>

        <label class="az-s78-field is-full{{ $field('owner_notes') }}"><span>Notes for Resavar review</span><textarea name="owner_notes">{{ old('owner_notes',$listing->owner_notes) }}</textarea>@error('owner_notes')<small>{{ $message }}</small>@enderror</label>
    </div>

    <fieldset class="az-s78-permissions">
        <legend>Amenities</legend>
        @foreach($amenities as $amenity)
            <label class="az-s78-check"><input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id,old('amenities',$listing->amenity_ids??[])))><span>{{ $amenity->name }}</span></label>
        @endforeach
        @error('amenities.*')<small>{{ $message }}</small>@enderror
    </fieldset>

    <section class="az-s78-permissions" aria-labelledby="onboarding-checklist">
        <h2 id="onboarding-checklist">Before you submit</h2>
        <ul>
            <li>Current signed owner-management agreement</li>
            <li>Property category, accurate address, guest capacity, accommodation price and description</li>
            <li>Hotel business identity and contact details, when applicable</li>
            <li>At least one property photograph and a private ownership/management-authority document</li>
            <li>Listing amenities, room and inventory details ready for staff review before publication</li>
        </ul>
        <p>A draft is private. Submitted listings are reviewed manually. Publishing is a separate decision and payout/bank verification happens later, when requesting a withdrawal.</p>
    </section>
    <div class="az-premium-actions" style="display:flex;flex-wrap:wrap;gap:12px">
        <button class="az-premium-button" type="submit" name="intent" value="draft" formnovalidate>Save private draft</button>
        <button class="az-premium-button" type="submit" name="intent" value="submit">
            {{ $listing->exists?'Submit updated application':'Submit for Resavar review' }}
        </button>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const operator = document.getElementById('resavar-operator-type');
    const panel = document.getElementById('resavar-hotel-business');
    if (!operator || !panel) return;
    const sync = () => {
        const hotel = operator.value === 'hotel_operator';
        panel.hidden = !hotel;
        panel.querySelectorAll('[data-business-required]').forEach((field) => { field.required = hotel; });
    };
    operator.addEventListener('change', sync);
    sync();
});
</script>
@endsection
