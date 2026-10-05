@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('section-label', 'Administration overview')

@section('content')
<section class="az-dashboard-hero">
    <div class="az-dashboard-hero__content">
        <p class="az-eyebrow">Reserva Residence Operations</p>
        <h1>Welcome back, {{ auth()->user()->name ?: auth()->user()->username }}.</h1>
        <p>
            Manage the residence portfolio, content and operational foundation from one considered workspace.
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
                <small>Residences in inventory</small>
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
                    <span>Residence data and publication controls are available.</span>
                </div>
            </div>
            <div class="is-planned">
                <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
                <div>
                    <strong>Booking and payment operations</strong>
                    <span>Scheduled for the remaining delivery sprints.</span>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
