<x-public-site.layout :title="'Verified guest reviews | '.$property->name">
<main class="site-container reserva-property-page" style="padding-block:32px 64px">
    <nav class="reserva-property-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('properties.show', $property) }}">{{ $property->name }}</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Guest reviews</span>
    </nav>

    <header class="reserva-property-heading">
        <div>
            <p class="eyebrow">Resavar verified stays</p>
            <h1>Guest reviews of {{ $property->name }}</h1>
            <p>Only approved reviews from eligible completed stays appear here.</p>
            @if(($summary['count'] ?? 0) > 0)
                <p><strong>{{ number_format((float)$summary['overall'], 1) }}/5</strong>
                    from {{ number_format($summary['count']) }} verified {{ Str::plural('review', $summary['count']) }}.</p>
            @endif
        </div>
    </header>

    <section class="reserva-property-section" aria-label="Browse guest reviews">
        <form method="GET" class="az-form-grid" action="{{ route('properties.reviews', $property) }}" style="margin-block:20px">
            <label><span>Sort reviews</span>
                <select name="sort">
                    @foreach(['recent'=>'Most recent', 'highest'=>'Highest rated', 'lowest'=>'Lowest rated'] as $value => $title)
                        <option value="{{ $value }}" @selected(($filters['sort'] ?? 'recent') === $value)>{{ $title }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Trip type</span>
                <select name="trip_type">
                    <option value="">All trips</option>
                    @foreach(['business','couple','family','friends','solo','other'] as $kind)
                        <option value="{{ $kind }}" @selected(($filters['trip_type'] ?? '') === $kind)>{{ Str::headline($kind) }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Review language</span>
                <select name="language">
                    <option value="">All languages</option>
                    @foreach(['und'=>'Unspecified','en'=>'English','fr'=>'French','es'=>'Spanish','de'=>'German','pt'=>'Portuguese','ar'=>'Arabic','hi'=>'Hindi','zh'=>'Chinese','it'=>'Italian','yo'=>'Yoruba','ig'=>'Igbo','ha'=>'Hausa','other'=>'Other'] as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['language'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-primary" type="submit">Apply filters</button>
        </form>

        <p role="status">{{ number_format($reviews->total()) }} matching {{ Str::plural('review', $reviews->total()) }}</p>
        <div class="reserva-review-list">
            @forelse($reviews as $review)
                <article class="reserva-review-card">
                    <div class="reserva-review-card__meta">
                        <strong>Verified guest</strong>
                        <span>{{ $review->created_at->format('M Y') }} · {{ $review->rating }}/5</span>
                    </div>
                    @if($review->trip_type)<p>Trip type: {{ Str::headline($review->trip_type) }}</p>@endif
                    @if($review->language && $review->language !== "und")<p>Language: {{ $review->language === "other" ? "Other" : strtoupper($review->language) }}</p>@endif
                    @if($review->title)<h2>{{ $review->title }}</h2>@endif
                    @if($review->positive_feedback)<p><strong>Liked:</strong> {{ $review->positive_feedback }}</p>@endif
                    @if($review->negative_feedback)<p><strong>Could be better:</strong> {{ $review->negative_feedback }}</p>@endif
                    <p>{{ $review->body }}</p>
                    <div class="reserva-review-helpful">
                        <span>{{ number_format((int) ($review->helpful_votes_count ?? 0)) }} found this helpful</span>
                        @if(auth()->check() && auth()->user()->hasVerifiedEmail()
                            && (int) auth()->id() !== (int) $review->user_id
                            && (int) auth()->id() !== (int) $property->owner_id)
                            <form method="POST" action="{{ route('user.reviews.helpful', $review) }}">
                                @csrf
                                <button class="button button-secondary" type="submit" aria-label="Mark review as helpful">Helpful</button>
                            </form>
                        @endif
                    </div>
                    @if($review->admin_reply)
                        <div class="reserva-management-reply"><strong>Resavar response</strong><p>{{ $review->admin_reply }}</p></div>
                    @endif
                    @if($review->owner_reply && $review->owner_reply_status === 'approved')
                        <div class="reserva-management-reply"><strong>Property response</strong><p>{{ $review->owner_reply }}</p></div>
                    @endif
                </article>
            @empty
                <p>No approved verified-stay reviews match these filters.</p>
            @endforelse
        </div>
        {{ $reviews->links() }}
    </section>
</main>
</x-public-site.layout>
