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

    @if(auth()->user()?->isAdministrator())
    <fieldset class="admin-form-span-2" style="border:1px solid #CAD5E6;border-radius:12px;padding:18px;min-width:0">
        <legend style="padding:0 8px">Property ownership and management</legend>
        <p>Leave the owner unassigned for a Resavar-managed property. To attach an independent owner, that account must be verified and have signed the current owner agreement. New owner-assigned properties are always saved as drafts.</p>
        <label>Verified owner account
            <select name="owner_id">
                <option value="">Resavar-managed (no third-party owner)</option>
                @foreach($owners as $owner)
                    <option value="{{ $owner->id }}"
                        @selected((string) old('owner_id', $property->owner_id) === (string) $owner->id)>
                        {{ $owner->name }} · {{ $owner->email }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>Owner revenue share (%)
            <input name="owner_share_percentage" type="number" min="0.01" max="100" step="0.01"
                value="{{ old('owner_share_percentage', $property->owner_id ? $property->owner_share_percentage : config('azari.owners.default_owner_share_percentage', 100)) }}">
            <small>Only applies to verified owner-managed properties. Resavar-managed properties are set to 100% platform share. Existing booking terms cannot be rewritten by reassignment.</small>
        </label>
        @if($property->exists)
            <p>Origin: <strong>{{ $property->owner_listing_id ? 'Approved owner submission' : 'Created by administration' }}</strong>.
                {{ $property->owner_id ? 'Managed for a property owner.' : 'Resavar-managed.' }}
                @if($property->ownerAgreement)
                    Owner agreement: signed {{ $property->ownerAgreement->signed_at?->format('j M Y') }},
                    version {{ $property->ownerAgreement->version }}.
                @elseif($property->owner_listing_id)
                    Owner agreement evidence is preserved with the original listing submission.
                @endif
            </p>
        @endif
    </fieldset>
    @endif

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

    <label>Property category
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

    <label>Bedrooms (0 for hotel rooms)<input name="bedrooms" type="number" min="0" value="{{ old('bedrooms', $property->bedrooms ?? 1) }}" required></label>
    <label>Bathrooms (0 allowed for shared facilities)<input name="bathrooms" type="number" min="0" value="{{ old('bathrooms', $property->bathrooms ?? 1) }}" required></label>
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

    <label>Cover image<input name="cover_image" type="file" accept="image/*"><small>New uploads remain hidden until an administrator approves each image and its attribution.</small></label>
    <label>Gallery images<input name="gallery[]" type="file" accept="image/*" multiple></label>
    <label>Sort order<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $property->sort_order ?? 0) }}"></label>

    <label><input type="checkbox" name="same_day_booking" value="1" @checked(old('same_day_booking', $property->same_day_booking))> Allow same-day booking</label>
    <p class="admin-form-span-2">Save property details as a private draft. Public availability needs a separate quality review and publication decision. Editing live core details revokes its earlier publication.</p>

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
@if($property->exists && auth()->user()?->isAdministrator())
<section class="admin-card" aria-labelledby="publication-review-heading" style="margin-top:24px">
    <h2 id="publication-review-heading">Property quality review and publication</h2>
    @php($latestPublication = $property->publicationReviews()->with('reviewer')->latest('id')->first())
    <p role="status"><strong>Visibility:</strong> {{ $property->is_published ? 'Published' : 'Private draft' }}.
       <strong>Latest decision:</strong> {{ $latestPublication ? Str::headline($latestPublication->status) : 'Not reviewed' }}.</p>
    @if($latestPublication)
        <p>{{ $latestPublication->review_note }} · {{ $latestPublication->reviewer?->name ?? 'System' }}
           · {{ $latestPublication->created_at?->format('j M Y, H:i') }}</p>
    @endif
    <p>Before publication, staff must verify owner identity or signed authority, correct property address,
        photo rights and alternative text, truthful amenities, sellable room inventory, pricing and tax display,
        cancellation terms and payment terms. This is an independent human decision, not a payment or external ID check.</p>
    @if(! $property->is_published && $latestPublication?->status !== 'approved_draft')
        <form method="POST" action="{{ route('azari.admin.properties.publication.review', $property) }}" class="admin-form-grid">
            @csrf
            <label class="admin-form-span-2"><input type="checkbox" name="identity_checked" value="1" required> I inspected the signed owner rights / current property-control documents</label>
            <label class="admin-form-span-2"><input type="checkbox" name="property_checked" value="1" required> I checked address, images, alt text, attribution and amenity claims</label>
            <label class="admin-form-span-2"><input type="checkbox" name="terms_checked" value="1" required> I checked rooms, rates, tax display, cancellation and payment policies</label>
            <label class="admin-form-span-2">Evidence reference and findings
                <textarea name="review_note" minlength="20" maxlength="3000" required>{{ old('review_note') }}</textarea>
            </label>
            <button class="button button-primary" type="submit">Approve private draft</button>
        </form>
    @elseif($latestPublication?->status === 'approved_draft' && ! $property->is_published)
        <form method="POST" action="{{ route('azari.admin.properties.publication.publish', $property) }}">
            @csrf
            <button class="button button-primary" type="submit">Publish reviewed accommodation</button>
        </form>
    @endif
    @if($property->is_published || $latestPublication?->status === 'approved_draft')
        <form method="POST" class="admin-form-grid"
            action="{{ route('azari.admin.properties.publication.unpublish', $property) }}" style="margin-top:16px">
            @csrf
            <label>Reason for rollback or reopening review
                <textarea name="reason" minlength="10" maxlength="2000" required></textarea>
            </label>
            <button class="button button-secondary" type="submit">Unpublish / reopen review</button>
        </form>
    @endif
    <p>Publication, unpublication and revisions are auditable. Historic stays and booking IDs are never deleted.</p>
</section>
@endif

@if($property->exists && auth()->user()?->is_admin)
    @include('admin.properties.photo-review', ['property' => $property])
@endif
@if($property->exists)
<section class="admin-card" aria-labelledby="property-audit" style="margin-top:24px">
    <h2 id="property-audit">Source and change history</h2>
    <p>{{ $property->ownerListing ? 'Originally approved from owner submission '.$property->ownerListing->reference : 'This property was created directly by administration.' }}</p>
    <p>Owner assignment and publication decisions are logged separately from booking, payment and photo-review activity.</p>
    @forelse($auditEvents as $event)
        <div style="border-bottom:1px solid #CAD5E6;padding:12px 0">
            <strong>{{ str_replace('_', ' ', $event->action) }}</strong>
            <p>{{ $event->actor?->name ?? 'System' }} · {{ $event->created_at?->format('j M Y, H:i') }}</p>
            @if($event->old_values || $event->new_values)
                <small>Recorded fields: {{ implode(', ', array_unique([...array_keys($event->old_values ?? []), ...array_keys($event->new_values ?? [])])) }}</small>
            @endif
        </div>
    @empty
        <p>No property change history has been recorded yet.</p>
    @endforelse
</section>
@endif

@if($property->exists && auth()->user()?->is_admin)
    <section class="admin-card" aria-label="Independent property claim verification" style="margin-top:24px">
        <h2>Independent claim verification</h2>
        <p>Only record checks supported by staff-reviewed evidence. Evidence references remain private and claims expire automatically.</p>
        @php $claimRecords = $property->verifiedClaims->keyBy('claim_type'); @endphp
        @foreach(\App\Models\PropertyVerifiedClaim::TYPES as $claimKey => $label)
            @php $claim = $claimRecords->get($claimKey); @endphp
            <div style="padding:12px 0;border-bottom:1px solid #bdc9d9">
                <strong>{{ $label }}</strong>
                <p>Status:
                    @if($claim && $claim->status === 'verified' && $claim->expires_at?->isFuture())
                        Verified until {{ $claim->expires_at->format('j M Y') }}
                    @elseif($claim && $claim->status === 'verified')
                        Expired
                    @else
                        Not independently verified
                    @endif
                </p>
                <form method="POST" action="{{ route('azari.admin.properties.claims.update', $property) }}" class="admin-form-grid">
                    @csrf
                    <input type="hidden" name="claim_type" value="{{ $claimKey }}">
                    <label>Evidence reference (staff only)
                        <input name="evidence_reference" maxlength="255" value="{{ $claim?->evidence_reference }}">
                    </label>
                    <label>Expires at
                        <input name="expires_at" type="date" value="{{ $claim?->expires_at?->toDateString() }}">
                    </label>
                    <button class="button button-primary" type="submit" name="action" value="verify">Verify claim</button>
                    @if($claim && $claim->status === 'verified')
                        <button class="button button-secondary" type="submit" name="action" value="revoke" formnovalidate>Revoke claim</button>
                    @endif
                </form>
            </div>
        @endforeach
    </section>
@endif

@endsection
