<x-public-site.layout
    title="Full-stay price calendar — {{ $property->name }} | Resavar"
    description="Choose truthful date-specific full-stay accommodation prices including known taxes and fees."
>
@php
    $monthDate = \Carbon\CarbonImmutable::parse($prices['month'].'-01');
    $today = \Carbon\CarbonImmutable::now($prices['timezone'])->startOfMonth();
    $other = collect($options)->except('month')->all();
    $offset = $monthDate->dayOfWeekIso - 1;
    $cells = array_merge(array_fill(0, $offset, null), $prices['days']);
@endphp
<main class="site-container" style="padding:clamp(18px,3vw,45px) 0;max-width:1150px">
    <nav aria-label="Breadcrumb">
        <a href="{{ route('properties.show', $property) }}">Back to {{ $property->name }}</a>
    </nav>
    <header style="margin:18px 0 24px">
        <h1>Compare full-stay prices</h1>
        <p>{{ $property->name }} · {{ $prices['timezone'] }} · {{ $options['nights'] }}
            {{ \Illuminate\Support\Str::plural('night', $options['nights']) }} ·
            {{ $options['rooms'] }} {{ \Illuminate\Support\Str::plural('room', $options['rooms']) }}</p>
        <p>Each available date shows a verified total including the selected stay's known fees and taxes.
            Prices may change until a booking is confirmed. Unavailable dates cannot be selected.</p>
    </header>

    <form method="GET" action="{{ route('properties.price-calendar', $property) }}"
        style="display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin-bottom:24px">
        <label><span>Month</span><input type="month" name="month"
            value="{{ $prices['month'] }}" min="{{ $today->format('Y-m') }}"
            max="{{ $today->addMonths(13)->format('Y-m') }}" required></label>
        <label><span>Length of stay (nights)</span>
            <input type="number" name="nights" min="1" max="14" value="{{ $options['nights'] }}" required>
        </label>
        <label><span>Adults</span>
            <input type="number" name="adults" min="1" max="12" value="{{ $options['adults'] }}" required>
        </label>
        <label><span>Children</span>
            <input type="number" name="children" min="0" max="8" value="{{ $options['children'] }}">
        </label>
        <label><span>Rooms</span>
            <input type="number" name="rooms" min="1" max="20" value="{{ $options['rooms'] }}" required>
        </label>
        @if($prices['type'])
            <input type="hidden" name="accommodation_type_id" value="{{ $prices['type']->id }}">
        @endif
        @if($prices['ratePlan'])
            <input type="hidden" name="rate_plan_id" value="{{ $prices['ratePlan']->id }}">
        @endif
        <button type="submit" class="button button-primary">Update calendar</button>
    </form>

    <nav aria-label="Calendar month navigation" style="display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px">
        @if($monthDate->greaterThan($today))
            <a class="button button-secondary" href="{{ route('properties.price-calendar',
                ['property'=>$property, ...$other, 'month'=>$monthDate->subMonth()->format('Y-m')]) }}">Previous month</a>
        @endif
        <h2>{{ $monthDate->format('F Y') }}</h2>
        @if($monthDate->lessThan($today->addMonths(13)))
            <a class="button button-secondary" href="{{ route('properties.price-calendar',
                ['property'=>$property, ...$other, 'month'=>$monthDate->addMonth()->format('Y-m')]) }}">Next month</a>
        @endif
    </nav>

    <div role="region" aria-label="Calendar of full-stay prices"
        style="overflow-x:auto;max-width:100%;background:#fff;border:1px solid #bac8df;border-radius:12px">
        <table style="border-collapse:collapse;width:100%;min-width:650px;table-layout:fixed;color:#052058">
            <caption style="text-align:left;padding:14px">Verified available dates for {{ $monthDate->format('F Y') }}</caption>
            <thead><tr style="background:#eef2f8">
                @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)
                    <th scope="col" style="padding:12px 8px;text-align:left;border:1px solid #cbd5e1">{{ $day }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach(array_chunk($cells, 7) as $week)
                    <tr>
                        @foreach(array_pad($week, 7, null) as $day)
                            <td style="border:1px solid #cbd5e1;padding:8px;vertical-align:top;height:112px">
                                @if($day)
                                    <span style="display:block;font-weight:700">
                                        {{ \Carbon\CarbonImmutable::parse($day['date'])->format('j M') }}
                                    </span>
                                    @if($day['available'])
                                        <a href="{{ $day['url'] }}"
                                            style="display:block;text-decoration:underline;color:#052058;overflow-wrap:anywhere"
                                            aria-label="View available {{ $options['nights'] }}-night stay starting {{ $day['date'] }} for {{ $day['currency'] }} {{ number_format((float)$day['total'],2) }}">
                                            <strong>{{ $day['currency'] }} {{ number_format((float)$day['total'],2) }}</strong>
                                            <small style="display:block">Full-stay total</small>
                                        </a>
                                    @else
                                        <span aria-label="{{ $day['date'] }} unavailable">Unavailable</span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p style="margin-top:20px">
        <strong>Selected accommodation:</strong>
        {{ $prices['type']?->name ?? 'Property default' }} ·
        {{ $prices['ratePlan']?->publicLabel() ?? 'Standard rate' }}.
        For stays longer than 14 nights, return to the property booking page for an exact quote.
        All selected dates and room/rate choices are revalidated at checkout.
    </p>
</main>
</x-public-site.layout>
