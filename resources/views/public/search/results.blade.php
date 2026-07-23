<x-public-site.layout
    title="Availability search"
    description="Availability search results for Azari Residences."
    body-class="search-results-page"
>
    <section class="search-results-hero">
        <div class="site-container">
            <span class="eyebrow">Availability search</span>
            <h1>Your stay request</h1>
            <p>
                {{ $search['check_in'] }} to {{ $search['check_out'] }}
                · {{ $search['nights'] }} {{ Str::plural('night', $search['nights']) }}
                · {{ $search['guest_total'] }} {{ Str::plural('guest', $search['guest_total']) }}
                @if ($search['location'])
                    · {{ $search['location'] }}
                @endif
            </p>
        </div>
    </section>

    <section class="search-results-content">
        <div class="site-container results-layout">
            <aside class="search-summary-card">
                <h2>Search summary</h2>
                <dl>
                    <div>
                        <dt>Check in</dt>
                        <dd>{{ $search['check_in'] }}</dd>
                    </div>
                    <div>
                        <dt>Check out</dt>
                        <dd>{{ $search['check_out'] }}</dd>
                    </div>
                    <div>
                        <dt>Adults</dt>
                        <dd>{{ $search['adults'] }}</dd>
                    </div>
                    <div>
                        <dt>Children</dt>
                        <dd>{{ $search['children'] }}</dd>
                    </div>
                    <div>
                        <dt>Residence type</dt>
                        <dd>{{ $search['property_type'] ? ucfirst($search['property_type']) : 'Any type' }}</dd>
                    </div>
                </dl>

                <a href="{{ route('home') }}#availability" class="button button-secondary button-block">
                    Modify search
                </a>
            </aside>

            <div class="results-panel">
                @if (count($results))
                    <h2>Available residences</h2>
                @else
                    <div class="empty-search-state">
                        <span class="material-symbols-outlined" aria-hidden="true">calendar_search</span>
                        <h2>The availability engine arrives in Sprint 5.</h2>
                        <p>
                            Your search was validated and processed successfully.
                            Live inventory cannot be returned until property records
                            and booking calendars are connected in Sprints 4 and 5.
                        </p>
                        <a href="{{ route('home') }}#residences" class="button button-primary">
                            Browse the preview collection
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-public-site.layout>
