@extends('admin.layout')
@section('title','Reviews')
@section('content')
<section class="az-page-heading">
    <div><p class="az-eyebrow">Trust & safety</p><h1>Verified-stay reviews</h1><p>Moderate abusive content without editing guest text. Public scores use approved verified reviews only.</p></div>
</section>

<form method="GET" class="az-s78-actions" style="margin-bottom:18px">
    <select name="status">
        <option value="">All statuses</option>
        @foreach(['pending','approved','hidden','archived','flagged'] as $status)
            <option value="{{ $status }}" @selected(request('status')===$status)>{{ Str::headline($status) }}</option>
        @endforeach
    </select>
    <label><input type="checkbox" name="verified_only" value="1" @checked(request()->boolean('verified_only'))> Verified stays only</label>
    <button class="az-button" type="submit">Filter</button>
</form>

@foreach($reviews as $review)
    <article class="az-admin-card" style="margin-bottom:16px">
        <header>
            <strong>
                {{ $review->user?->name ?? 'Guest' }}
                · {{ $review->rating }}/5
                · {{ $review->booking?->reference }}
                · {{ $review->property?->name ?? $review->booking?->property?->name }}
            </strong>
            <p>{{ $review->verified_stay ? 'Verified completed stay' : 'Legacy/unverified review' }}</p>
        </header>

        <dl class="az-s78-detail">
            @foreach([
                'Cleanliness' => $review->cleanliness,
                'Comfort' => $review->comfort,
                'Facilities' => $review->facilities,
                'Location' => $review->location_score,
                'Staff/service' => $review->staff_service,
                'Value' => $review->value_score,
                'Wi-Fi' => $review->wifi_score,
            ] as $label => $score)
                @if($score !== null)<div><dt>{{ $label }}</dt><dd>{{ $score }}/5</dd></div>@endif
            @endforeach
        </dl>

        @if($review->title)<h3>{{ $review->title }}</h3>@endif
        @if($review->positive_feedback)<p><strong>Liked:</strong> {{ $review->positive_feedback }}</p>@endif
        @if($review->negative_feedback)<p><strong>Could be better:</strong> {{ $review->negative_feedback }}</p>@endif
        <p>{{ $review->body }}</p>

        <form method="POST" action="{{ route('azari.admin.reviews.update',$review) }}" class="az-form-grid">
            @csrf
            @method('PUT')
            <label>
                <span>Status</span>
                <select name="status">
                    @foreach(['pending','approved','hidden','archived','flagged'] as $status)
                        <option value="{{ $status }}" @selected($review->status===$status)>{{ Str::headline($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Featured</span><input type="checkbox" name="featured" value="1" @checked($review->featured)></label>
            <label class="wide"><span>Moderation reason</span><textarea name="moderation_reason" maxlength="1000">{{ $review->moderation_reason }}</textarea></label>
            <label class="wide"><span>Public management response</span><textarea name="admin_reply" maxlength="2000">{{ $review->admin_reply }}</textarea></label>
            @if(filled($review->owner_reply))
                <div class="wide"><strong>Property team's proposed public reply</strong><p>{{ $review->owner_reply }}</p></div>
                <label class="wide"><span>Property reply publication</span>
                    <select name="owner_reply_status">
                        @foreach(['pending', 'approved', 'rejected'] as $replyStatus)
                            <option value="{{ $replyStatus }}" @selected(($review->owner_reply_status ?? 'pending') === $replyStatus)>{{ Str::headline($replyStatus) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button class="az-button" type="submit">Update moderation</button>
        </form>
        @if($review->appeal)
            <section class="az-admin-card" aria-label="Guest review moderation appeal" style="margin-top:14px">
                <strong>Guest moderation appeal: {{ Str::headline($review->appeal->status) }}</strong>
                <p>{{ $review->appeal->reason }}</p>
                @if($review->appeal->status === 'pending')
                    <form method="POST" action="{{ route('azari.admin.reviews.appeal', $review) }}" class="az-form-grid">
                        @csrf
                        <label><span>Decision</span>
                            <select name="decision" required>
                                <option value="">Choose decision</option>
                                <option value="accepted">Accept and restore review</option>
                                <option value="rejected">Reject appeal</option>
                            </select>
                        </label>
                        <label class="wide"><span>Decision note (required when rejected)</span>
                            <textarea name="decision_note" maxlength="2000" rows="3"></textarea>
                        </label>
                        <button class="az-button" type="submit">Record appeal decision</button>
                    </form>
                @elseif($review->appeal->decision_note)
                    <p>Decision note: {{ $review->appeal->decision_note }}</p>
                @endif
            </section>
        @endif
    </article>
@endforeach

{{ $reviews->links() }}
@endsection
