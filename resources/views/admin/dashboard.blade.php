@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('section-label', 'Administration overview')

@section('content')
<section class="az-dashboard-hero">
    <div class="az-dashboard-hero__content">
        <p class="az-eyebrow">Resavar Stay Operations</p>
        <h1>Welcome back, {{ auth()->user()->name ?: auth()->user()->username }}.</h1>
        <p>
            Manage the property portfolio, content and operational foundation from one considered workspace.
        </p>

        <div class="az-dashboard-hero__actions">
            @if (Route::has('azari.admin.properties.create'))
                <a href="{{ route('azari.admin.properties.create') }}" class="az-button az-button--brass">
                    <span class="material-symbols-outlined" aria-hidden="true">add_home</span>
                    Add property
                </a>
            @endif

            <a href="{{ url('/azari-admin/cms') }}" class="az-button az-button--ghost-light">
                <span class="material-symbols-outlined" aria-hidden="true">edit_note</span>
                Manage content
            </a>
        </div>
    </div>

    <div class="az-dashboard-hero__date" aria-label="Current date">
        <span>{{ now()->format('l') }}</span>
        <strong>{{ now()->format('d') }}</strong>
        <small>{{ now()->format('F Y') }}</small>
    </div>
</section>

<section aria-labelledby="platform-overview-heading">
    <div class="az-section-heading">
        <div>
            <p class="az-eyebrow">Live platform data</p>
            <h2 id="platform-overview-heading">Platform overview</h2>
        </div>
        <span class="az-section-heading__meta">Updated {{ now()->format('g:i A') }}</span>
    </div>

    <div class="az-metric-grid">
        <article class="az-metric-card">
            <div class="az-metric-card__icon">
                <span class="material-symbols-outlined" aria-hidden="true">group</span>
            </div>
            <div>
                <span>Total users</span>
                <strong>{{ number_format($userCount ?? 0) }}</strong>
                <small>Registered platform accounts</small>
            </div>
        </article>

        <article class="az-metric-card">
            <div class="az-metric-card__icon">
                <span class="material-symbols-outlined" aria-hidden="true">admin_panel_settings</span>
            </div>
            <div>
                <span>Administrators</span>
                <strong>{{ number_format($adminCount ?? 0) }}</strong>
                <small>Authorised administrative accounts</small>
            </div>
        </article>

        <article class="az-metric-card">
            <div class="az-metric-card__icon">
                <span class="material-symbols-outlined" aria-hidden="true">apartment</span>
            </div>
            <div>
                <span>Properties</span>
                <strong>{{ number_format($propertyCount ?? 0) }}</strong>
                <small>Stays in inventory</small>
            </div>
        </article>

        <article class="az-metric-card">
            <div class="az-metric-card__icon">
                <span class="material-symbols-outlined" aria-hidden="true">star</span>
            </div>
            <div>
                <span>Featured properties</span>
                <strong>{{ number_format($featuredCount ?? 0) }}</strong>
                <small>Highlighted on the public experience</small>
            </div>
        </article>

        <article class="az-metric-card">
            <div class="az-metric-card__icon">
                <span class="material-symbols-outlined" aria-hidden="true">badge</span>
            </div>
            <div>
                <span>Staff accounts</span>
                <strong>{{ number_format($staffCount ?? 0) }}</strong>
                <small>Operational team members</small>
            </div>
        </article>

        <article class="az-metric-card">
            <div class="az-metric-card__icon">
                <span class="material-symbols-outlined" aria-hidden="true">view_quilt</span>
            </div>
            <div>
                <span>Content blocks</span>
                <strong>{{ number_format($contentCount ?? 0) }}</strong>
                <small>Editable website content areas</small>
            </div>
        </article>
    </div>
</section>

<div class="az-dashboard-grid">
    <section class="az-panel" aria-labelledby="quick-actions-heading">
        <div class="az-panel__header">
            <div>
                <p class="az-eyebrow">Common tasks</p>
                <h2 id="quick-actions-heading">Quick actions</h2>
            </div>
        </div>

        <div class="az-quick-actions">
            <a href="{{ url('/azari-admin/inventory') }}">
                <span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>
                <div>
                    <strong>Manage inventory</strong>
                    <span>Properties, rooms, amenities and pricing</span>
                </div>
                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
            </a>

            <a href="{{ url('/azari-admin/cms') }}">
                <span class="material-symbols-outlined" aria-hidden="true">edit_note</span>
                <div>
                    <strong>Update website content</strong>
                    <span>Homepage sections, navigation and media</span>
                </div>
                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
            </a>

            @if (Route::has('azari.admin.settings.edit'))
                <a href="{{ route('azari.admin.settings.edit') }}">
                    <span class="material-symbols-outlined" aria-hidden="true">palette</span>
                    <div>
                        <strong>Review brand settings</strong>
                        <span>Logos, colours, typography and identity</span>
                    </div>
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                </a>
            @endif
        </div>
    </section>

    <section class="az-panel" aria-labelledby="readiness-heading">
        <div class="az-panel__header">
            <div>
                <p class="az-eyebrow">System foundation</p>
                <h2 id="readiness-heading">Operational readiness</h2>
            </div>
            <span class="az-status-pill az-status-pill--good">Stable</span>
        </div>

        <div class="az-readiness-list">
            <div>
                <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                <div>
                    <strong>Administration access</strong>
                    <span>Protected staff entry is available.</span>
                </div>
            </div>
            <div>
                <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                <div>
                    <strong>CMS foundation</strong>
                    <span>Core website content can be managed.</span>
                </div>
            </div>
            <div>
                <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                <div>
                    <strong>Inventory foundation</strong>
                    <span>Stay data and publication controls are available.</span>
                </div>
            </div>
            <div>
                <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                <div>
                    <strong>Booking and payment operations</strong>
                    <span>Live arrivals, departures, balances, payment exceptions and inventory controls are active.</span>
                </div>
            </div>
        </div>
    </section>
</div>

<section style="margin-top:28px" aria-labelledby="operations-heading">
    <div class="az-section-heading">
        <div><p class="az-eyebrow">Today</p><h2 id="operations-heading">Hospitality command centre</h2></div>
        @if(Route::has('azari.admin.bookings.index'))<a class="az-button" href="{{ route('azari.admin.bookings.index') }}">Open bookings</a>@endif
    </div>

    <div class="az-metric-grid">
        @foreach([
            'arrivals' => ['Arrivals','login'],
            'departures' => ['Departures','logout'],
            'checked_in' => ['Currently checked in','hotel'],
            'overdue_checkouts' => ['Overdue check-outs','warning'],
            'pending_payments' => ['Outstanding balances','account_balance_wallet'],
            'failed_payments' => ['Payment exceptions','error'],
            'cancellations' => ['Cancellations today','event_busy'],
            'no_shows' => ['No-shows today','person_off'],
            'expiring_holds' => ['Holds expiring soon','timer'],
            'maintenance_inventory' => ['Inventory restrictions','build'],
            'support_escalations' => ['Support escalations','support_agent'],
            'owner_approvals' => ['Owner approvals','approval'],
        ] as $key => [$label,$icon])
            <article class="az-metric-card">
                <div class="az-metric-card__icon"><span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span></div>
                <div><span>{{ $label }}</span><strong>{{ number_format($operations[$key] ?? 0) }}</strong></div>
            </article>
        @endforeach
    </div>

    <div class="az-dashboard-grid" style="margin-top:18px">
        <section class="az-panel">
            <div class="az-panel__header"><div><p class="az-eyebrow">Front desk</p><h2>Today's arrivals</h2></div></div>
            <div class="az-readiness-list">
                @forelse($operationQueues['arrivals'] as $booking)
                    <div>
                        <span class="material-symbols-outlined" aria-hidden="true">login</span>
                        <div>
                            <strong><a href="{{ route('azari.admin.bookings.show',$booking) }}">{{ $booking->reference }} · {{ $booking->guest_name }}</a></strong>
                            <span>{{ $booking->property?->name }} · {{ Str::headline($booking->status) }}</span>
                        </div>
                    </div>
                @empty
                    <p>No arrivals scheduled today.</p>
                @endforelse
            </div>
        </section>

        <section class="az-panel">
            <div class="az-panel__header"><div><p class="az-eyebrow">Front desk</p><h2>Today's departures</h2></div></div>
            <div class="az-readiness-list">
                @forelse($operationQueues['departures'] as $booking)
                    <div>
                        <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                        <div>
                            <strong><a href="{{ route('azari.admin.bookings.show',$booking) }}">{{ $booking->reference }} · {{ $booking->guest_name }}</a></strong>
                            <span>{{ $booking->property?->name }} · {{ Str::headline($booking->status) }}</span>
                        </div>
                    </div>
                @empty
                    <p>No departures scheduled today.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="az-dashboard-grid" style="margin-top:18px">
        <section class="az-panel">
            <div class="az-panel__header"><div><p class="az-eyebrow">Finance</p><h2>Payment attention</h2></div>
                @if(Route::has('azari.admin.payments.index'))<a href="{{ route('azari.admin.payments.index',['attention'=>1]) }}">View all</a>@endif
            </div>
            <div class="az-readiness-list">
                @forelse($operationQueues['payment_attention'] as $payment)
                    <div>
                        <span class="material-symbols-outlined" aria-hidden="true">payments</span>
                        <div>
                            <strong><a href="{{ route('azari.admin.payments.show',$payment) }}">{{ $payment->reference }}</a></strong>
                            <span>{{ Str::headline($payment->status) }} · {{ $payment->currency }} {{ number_format((float)$payment->amount,2) }} · {{ $payment->booking?->property?->name }}</span>
                        </div>
                    </div>
                @empty
                    <p>No payment exceptions require attention.</p>
                @endforelse
            </div>
        </section>

        <section class="az-panel">
            <div class="az-panel__header"><div><p class="az-eyebrow">Guest care</p><h2>Support attention</h2></div>
                @if(Route::has('azari.admin.support.index'))<a href="{{ route('azari.admin.support.index') }}">View all</a>@endif
            </div>
            <div class="az-readiness-list">
                @forelse($operationQueues['support_attention'] as $ticket)
                    <div>
                        <span class="material-symbols-outlined" aria-hidden="true">support_agent</span>
                        <div>
                            <strong>{{ $ticket->reference }} · {{ $ticket->subject }}</strong>
                            <span>{{ Str::headline($ticket->priority) }} · {{ $ticket->user?->name }}</span>
                        </div>
                    </div>
                @empty
                    <p>No support escalations require attention.</p>
                @endforelse
            </div>
        </section>
    </div>
</section>

<section style="margin-top:28px" aria-labelledby="analytics-heading">
    <div class="az-section-heading">
        <div><p class="az-eyebrow">Last 30 days</p><h2 id="analytics-heading">Marketplace performance</h2></div>
        @if(Route::has('azari.admin.reports.index'))<a class="az-button" href="{{ route('azari.admin.reports.index') }}">Open reports</a>@endif
    </div>

    <div class="az-metric-grid">
        @foreach([
            ['Gross booking value', $analytics['gbv'], config('azari.currency','USD')],
            ['Net revenue', $analytics['net_revenue'], config('azari.currency','USD')],
            ['Occupancy', $analytics['occupancy'], '%'],
            ['ADR', $analytics['adr'], config('azari.currency','USD')],
            ['RevPAR', $analytics['revpar'], config('azari.currency','USD')],
            ['Cancellation rate', $analytics['cancellation_rate'], '%'],
            ['No-show rate', $analytics['no_show_rate'], '%'],
            ['Payment success', $analytics['payment_success_rate'], '%'],
        ] as [$label,$value,$suffix])
            <article class="az-metric-card">
                <div><span>{{ $label }}</span><strong>{{ $suffix === '%' ? number_format((float)$value,1).'%' : $suffix.' '.number_format((float)$value,2) }}</strong></div>
            </article>
        @endforeach
    </div>

    <section class="az-panel" style="margin-top:18px">
        <div class="az-panel__header"><div><p class="az-eyebrow">Conversion</p><h2>Booking funnel</h2></div></div>
        <div class="az-metric-grid">
            @foreach($analytics['funnel'] as $event => $count)
                <article class="az-metric-card"><div><span>{{ Str::headline($event) }}</span><strong>{{ number_format($count) }}</strong></div></article>
            @endforeach
        </div>
    </section>
</section>
@endsection
