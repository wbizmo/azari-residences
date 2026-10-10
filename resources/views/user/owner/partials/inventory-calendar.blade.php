<section class="az-user-panel" aria-label="Owner inventory calendar" style="margin-top:18px">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Availability calendar</h2>
            <p class="az-user-panel-subtitle">
                Live room availability from bookings, temporary holds, property restrictions and connected channels.
                Values are property-local calendar dates, not a promise that a new booking is confirmed.
            </p>
        </div>
    </header>
    <div class="az-user-panel-body">
        <nav class="az-user-actions" aria-label="Availability calendar navigation" style="margin-bottom:14px;flex-wrap:wrap">
            <a class="az-user-button az-user-button--light"
                href="{{ route('user.owner.commercial.edit', ['property' => $property, 'view' => $board['view'], 'date' => $board['previous']]) }}">
                Previous {{ $board['view'] }}
            </a>
            <a class="az-user-button {{ $board['view']==='week' ? 'az-user-button--dark' : 'az-user-button--light' }}"
                href="{{ route('user.owner.commercial.edit', ['property' => $property, 'view' => 'week', 'date' => $board['anchor']]) }}"
                @if($board['view']==='week') aria-current="page" @endif>Week</a>
            <a class="az-user-button {{ $board['view']==='month' ? 'az-user-button--dark' : 'az-user-button--light' }}"
                href="{{ route('user.owner.commercial.edit', ['property' => $property, 'view' => 'month', 'date' => $board['anchor']]) }}"
                @if($board['view']==='month') aria-current="page" @endif>Month</a>
            <a class="az-user-button az-user-button--light"
                href="{{ route('user.owner.commercial.edit', ['property' => $property, 'view' => $board['view'], 'date' => $board['next']]) }}">
                Next {{ $board['view'] }}
            </a>
        </nav>
        <p style="color:#052058">
            <strong>{{ \Carbon\CarbonImmutable::parse($board['anchor'])->format('F Y') }}</strong>
            · Available is calculated after current bookings, active holds, channel blocks and maintenance.
            Use the audited forms below to make changes.
        </p>

        @forelse($board['weeks'] as $week)
            <div role="region" tabindex="0"
                aria-label="Availability week of {{ \Carbon\CarbonImmutable::parse($week->first())->format('j F Y') }}"
                style="max-width:100%;overflow-x:auto;border:1px solid #cbd5e1;border-radius:12px;margin:18px 0;background:#fff">
                <table style="width:100%;min-width:880px;border-collapse:collapse;color:#052058">
                    <caption style="text-align:left;padding:12px;font-weight:700">
                        Week of {{ \Carbon\CarbonImmutable::parse($week->first())->format('j M Y') }}
                    </caption>
                    <thead>
                        <tr style="background:#eaf0fa">
                            <th scope="col" style="min-width:160px;text-align:left;padding:10px;border:1px solid #cbd5e1">Accommodation</th>
                            @foreach($week as $date)
                                <th scope="col" style="min-width:132px;text-align:left;padding:10px;border:1px solid #cbd5e1">
                                    {{ \Carbon\CarbonImmutable::parse($date)->format('D j M') }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($board['types'] as $row)
                            <tr>
                                <th scope="row" style="padding:10px;text-align:left;vertical-align:top;border:1px solid #cbd5e1">
                                    {{ $row['type']->name }}
                                    <small style="display:block;font-weight:400">Capacity {{ $row['type']->total_inventory }}</small>
                                </th>
                                @foreach($week as $date)
                                    @php($cell = $row['dates'][$date])
                                    <td style="padding:10px;vertical-align:top;border:1px solid #cbd5e1">
                                        <strong>{{ $cell['remaining'] }} available</strong>
                                        <div>Booked: {{ $cell['booked'] }}</div>
                                        <div>Held: {{ $cell['held'] }}</div>
                                        <div>Channel blocked: {{ $cell['external'] }}</div>
                                        @if($cell['maintenance'] > 0)<div>Maintenance: {{ $cell['maintenance'] }}</div>@endif
                                        @if($cell['stop_sell'])<div><strong>Stop sell</strong></div>@endif
                                        @if($cell['price_override'] !== null)
                                            <div>Nightly override: {{ number_format((float) $cell['price_override'], 2) }}</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="8" style="padding:18px">Add an accommodation type to see its inventory.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @empty
            <p>No calendar dates are available for this view.</p>
        @endforelse
    </div>
</section>
