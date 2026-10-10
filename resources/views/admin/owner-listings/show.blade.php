@extends('admin.layouts.app')
@section('title','Review owner listing')
@section('content')
<div class="az-admin-page-header">
    <div><h1>{{ data_get($listing->property_data,'name','Owner listing') }}</h1><p>{{ $listing->reference }} · {{ $listing->user?->name }} · {{ str_replace('_',' ',$listing->status) }}</p></div>
    <a class="az-admin-link" href="{{ route('azari.admin.owner-listings.agreement',$listing) }}">Download signed agreement</a>
</div>

@if($errors->any())<div class="az-admin-alert az-admin-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="az-admin-card">
    <p><strong>Listing completeness:</strong> {{ $completion['score'] }}%</p>
    @if(!$completion['publishable'])
        <div class="az-admin-alert az-admin-alert--danger">
            <strong>Publication blocked.</strong>
            <p>Missing: {{ collect($completion['blockers'])->map(fn($item)=>Str::headline($item))->join(', ') }}.</p>
        </div>
    @endif
    @if($listing->cover_image)<img class="az-owner-cover" src="{{ Storage::disk('public')->url($listing->cover_image) }}" alt="{{ data_get($listing->property_data,'name','Property cover') }}">@endif
    <section aria-label="Owner-submitted photography awaiting moderation">
        <h2>Submitted property photos</h2>
        <p>Inspect the cover and every gallery photo for accuracy, privacy, prohibited content and mismatch with the advertised room. Broken images must block approval.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,220px),1fr));gap:14px">
            @foreach(($listing->gallery ?? []) as $index => $image)
                <figure style="margin:0;padding:10px;border:1px solid #cbd5e1;border-radius:10px">
                    <img src="{{ Storage::disk('public')->url($image) }}"
                        alt="Submitted property photo {{ $index + 1 }} for moderation"
                        loading="lazy" decoding="async"
                        style="display:block;width:100%;aspect-ratio:4/3;object-fit:cover">
                    <figcaption>Gallery photo {{ $index + 1 }} · review required</figcaption>
                </figure>
            @endforeach
        </div>
        @if($listing->media_reviewed_at)
            <p>Media reviewed {{ $listing->media_reviewed_at->format('j M Y H:i') }}
                by {{ $listing->mediaReviewedBy?->name ?? 'authorized reviewer' }}.
                {{ $listing->media_review_note }}</p>
        @endif
    </section>
    <dl class="az-admin-detail-list">@foreach($listing->property_data as $key=>$value)@unless(is_array($value))<dt>{{ ucfirst(str_replace('_',' ',$key)) }}</dt><dd>{{ $value }}</dd>@endunless @endforeach</dl>
    <p><strong>Amenities:</strong> {{ $amenities->join(', ') ?: 'None selected' }}</p>
    <p><strong>Owner revenue:</strong> No Resavar platform commission is deducted from owner-property room sales.</p>
    @if($listing->decline_reason)<p><strong>Previous decline:</strong> {{ $listing->decline_reason }}</p>@endif
</div>

@if($listing->status==='submitted')
<form method="post" action="{{ route('azari.admin.owner-listings.review',$listing) }}" class="az-admin-form az-admin-card">
    @csrf
    <h2>Begin review</h2>
    <p>Mark this application as under review before approval or decline.</p>
    <button class="az-admin-button">Mark under review</button>
</form>
@endif

@if(in_array($listing->status,['submitted','under_review']))
<div class="az-admin-grid">
    <form method="post" action="{{ route('azari.admin.owner-listings.approve',$listing) }}" class="az-admin-form az-admin-card">@csrf
        <h2>Approve listing</h2>
        <p><strong>Revenue treatment:</strong> Owner receives 100% of applicable owner-property room-sale revenue. Resavar platform commission: 0%.</p>
        <label><input type="checkbox" name="publish_now" value="1"> Publish immediately</label>
        <label><input type="checkbox" name="feature_now" value="1"> Feature immediately</label>
        @foreach(collect([$listing->cover_image])->merge($listing->gallery ?? [])->filter()->unique() as $image)
            @php($photoKey = hash('sha256', $image))
            <fieldset class="az-span-2" style="padding:14px;border:1px solid #cbd5e1;border-radius:10px">
                <legend>Photo description and provenance</legend>
                <img src="{{ Storage::disk('public')->url($image) }}"
                     alt="Photo under administrative review"
                     style="display:block;max-height:170px;max-width:100%;object-fit:contain">
                <label>Accurate, descriptive alternative text
                    <input type="text" name="photo_alt[{{ $photoKey }}]"
                        minlength="8" maxlength="300" required
                        value="{{ old('photo_alt.'.$photoKey) }}">
                </label>
                <label>Photo attribution / rights holder
                    <input type="text" name="photo_attribution[{{ $photoKey }}]"
                        minlength="3" maxlength="300" required
                        value="{{ old('photo_attribution.'.$photoKey) }}">
                </label>
            </fieldset>
        @endforeach
        <label><input type="checkbox" name="media_reviewed" value="1" required>
            I examined the cover and all gallery images, confirmed that they depict the listed property and contain no prohibited or private material.
        </label>
        <label>Mandatory photo-review note
            <textarea name="media_review_note" minlength="12" maxlength="1500" required>{{ old('media_review_note') }}</textarea>
        </label>
        <label>Internal notes<textarea name="admin_notes">{{ old('admin_notes') }}</textarea></label>
        <button class="az-admin-button">Approve and create owner-managed property</button>
    </form>

    <form method="post" action="{{ route('azari.admin.owner-listings.decline',$listing) }}" class="az-admin-form az-admin-card">@csrf
        <h2>Decline listing</h2>
        <label>Reason visible to owner<textarea name="decline_reason" minlength="10" required>{{ old('decline_reason') }}</textarea></label>
        <label>Internal notes<textarea name="admin_notes">{{ old('admin_notes') }}</textarea></label>
        <button class="az-admin-button">Decline with reason</button>
    </form>
</div>
@endif
@endsection
