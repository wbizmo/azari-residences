<section class="admin-card" aria-label="Property photo review" style="margin-top:24px">
    <h2>Property photo review</h2>
    <p>Each new photograph is hidden until approved with an accurate description and rights attribution.</p>
    @php($paths = collect([$property->cover_image])->merge($property->gallery ?? [])->filter()->unique())
    @forelse($property->photoModerations()->whereIn('path', $paths->all())->get() as $photo)
        <div style="padding:16px 0;border-bottom:1px solid #cbd5e1">
            <img src="{{ Storage::disk('public')->url($photo->path) }}"
                alt="Photo for staff review" style="display:block;max-width:300px;max-height:200px;object-fit:contain">
            <p>Status: <strong>{{ Str::headline($photo->status) }}</strong></p>
            <form method="POST" class="admin-form-grid"
                action="{{ route('azari.admin.properties.photos.update', [$property, $photo]) }}">
                @csrf @method('PATCH')
                <label>Alternative description
                    <input name="alt_text" maxlength="300" value="{{ $photo->alt_text }}">
                </label>
                <label>Attribution / rights holder
                    <input name="attribution" maxlength="300" value="{{ $photo->attribution }}">
                </label>
                <label>Review note
                    <textarea name="review_note" maxlength="1500">{{ $photo->review_note }}</textarea>
                </label>
                <button class="button button-primary" name="status" value="approved" type="submit">Approve</button>
                <button class="button button-secondary" name="status" value="rejected" type="submit">Reject</button>
            </form>
        </div>
    @empty
        <p>No new photos currently need review. Existing previously published photos keep their prior visibility.</p>
    @endforelse
</section>
