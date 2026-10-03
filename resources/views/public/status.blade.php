<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta
        name="robots"
        content="noindex, noarchive"
    >
<title>System Status | Resavar</title>

    <style>
        :root {
            color-scheme: light;
            --page: #052058;
            --surface: #FFFFFF;
            --surface-muted: #FFFFFF;
            --ink: #052058;
            --muted: #052058;
            --line: #052058;
            --brand: #052058;
            --brand-soft: #052058;
            --green: #052058;
            --green-soft: #052058;
            --amber: #052058;
            --amber-soft: #052058;
            --orange: #052058;
            --orange-soft: #052058;
            --red: #052058;
            --red-soft: #052058;
            --grey: #052058;
            --grey-soft: #052058;
            --shadow: 0 15px 45px rgba(5, 32, 88, .07);
        }

        * {
            box-sizing: border-box;
        }

        html {
            background: var(--page);
        }

        body {
            margin: 0;
            background:
                radial-gradient(
                    circle at top right,
                    rgba(5, 32, 88, .055),
                    transparent 34rem
                ),
                var(--page);
            color: var(--ink);
            font-family:
                Inter,
                ui-sans-serif,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            line-height: 1.5;
        }

        a {
            color: inherit;
        }

        .wrap {
            width: min(1060px, calc(100% - 36px));
            margin: 0 auto;
        }

        .topbar {
            padding: 28px 0 22px;
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            border-radius: 9px;
        }

        .brand-copy {
            display: grid;
            gap: 1px;
        }

        .brand-copy strong {
            font-size: 14px;
            letter-spacing: .015em;
        }

        .brand-copy span {
            color: var(--muted);
            font-size: 12px;
        }

        .home-link {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .home-link:hover {
            color: var(--ink);
        }

        main {
            padding: 32px 0 64px;
        }

        .intro {
            margin-bottom: 30px;
        }

        .eyebrow {
            margin: 0 0 8px;
            color: var(--brand);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .11em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            max-width: 760px;
            font-size: clamp(34px, 6vw, 58px);
            line-height: 1.04;
            letter-spacing: -.045em;
        }

        .intro-copy {
            max-width: 670px;
            margin: 16px 0 0;
            color: var(--muted);
            font-size: 16px;
        }

        .overall {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin: 30px 0 24px;
            padding: 24px 26px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .overall-main {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .large-dot {
            width: 15px;
            height: 15px;
            flex: 0 0 auto;
            border-radius: 999px;
            box-shadow: 0 0 0 6px currentColor;
        }

        .overall-copy strong {
            display: block;
            font-size: 18px;
        }

        .overall-copy span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 13px;
        }

        .status-operational {
            color: var(--green);
        }

        .status-degraded {
            color: var(--amber);
        }

        .status-partial_outage {
            color: var(--orange);
        }

        .status-major_outage {
            color: var(--red);
        }

        .status-unknown {
            color: var(--grey);
        }

        .overall.status-operational {
            border-color: #052058;
            background: linear-gradient(
                135deg,
                var(--green-soft),
                #FFFFFF
            );
        }

        .overall.status-degraded {
            border-color: #052058;
            background: linear-gradient(
                135deg,
                var(--amber-soft),
                #FFFFFF
            );
        }

        .overall.status-partial_outage {
            border-color: #052058;
            background: linear-gradient(
                135deg,
                var(--orange-soft),
                #FFFFFF
            );
        }

        .overall.status-major_outage {
            border-color: #052058;
            background: linear-gradient(
                135deg,
                var(--red-soft),
                #FFFFFF
            );
        }

        .overall.status-unknown {
            background: linear-gradient(
                135deg,
                var(--grey-soft),
                #FFFFFF
            );
        }

        .last-checked {
            flex: 0 0 auto;
            color: var(--muted);
            font-size: 12px;
            text-align: right;
        }

        .section {
            margin-top: 24px;
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 20px 22px;
            border-bottom: 1px solid var(--line);
        }

        .section-head h2 {
            margin: 0;
            font-size: 16px;
            letter-spacing: -.01em;
        }

        .section-head span {
            color: var(--muted);
            font-size: 12px;
        }

        .component {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 24px;
            align-items: center;
            padding: 18px 22px;
            border-bottom: 1px solid var(--line);
        }

        .component:last-child {
            border-bottom: 0;
        }

        .component-name {
            margin: 0;
            font-size: 14px;
            font-weight: 750;
        }

        .component-description {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 118px;
            justify-content: flex-end;
            font-size: 12px;
            font-weight: 750;
            white-space: nowrap;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        .history {
            padding: 20px 22px 22px;
        }

        .history-track {
            display: grid;
            grid-template-columns: repeat(48, minmax(2px, 1fr));
            gap: 3px;
            min-height: 34px;
            align-items: stretch;
        }

        .history-bar {
            min-width: 2px;
            border-radius: 3px;
            background: var(--grey);
            opacity: .9;
        }

        .history-bar.status-operational {
            background: var(--green);
        }

        .history-bar.status-degraded {
            background: var(--amber);
        }

        .history-bar.status-partial_outage {
            background: var(--orange);
        }

        .history-bar.status-major_outage {
            background: var(--red);
        }

        .history-bar.status-unknown {
            background: var(--grey);
        }

        .history-scale {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            color: var(--muted);
            font-size: 11px;
        }

        .notice {
            margin-top: 24px;
            padding: 18px 20px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--surface-muted);
            color: var(--muted);
            font-size: 12px;
        }

        .notice strong {
            color: var(--ink);
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 18px;
            margin-top: 16px;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--muted);
        }

        footer {
            padding: 0 0 42px;
            color: var(--muted);
            font-size: 11px;
        }

        @media (max-width: 700px) {
            .wrap {
                width: min(100% - 24px, 1060px);
            }

            .topbar {
                padding-top: 20px;
            }

            .brand-copy strong {
                font-size: 13px;
            }

            main {
                padding-top: 20px;
            }

            .overall {
                align-items: flex-start;
                flex-direction: column;
                padding: 20px;
            }

            .last-checked {
                text-align: left;
            }

            .component {
                grid-template-columns: 1fr;
                gap: 10px;
                padding: 17px 18px;
            }

            .pill {
                justify-content: flex-start;
            }

            .section-head,
            .history {
                padding-left: 18px;
                padding-right: 18px;
            }

            .history-track {
                gap: 2px;
            }
        }

        /* =====================================================
           LIVE STATUS REFRESH
           ===================================================== */

        .component {
            position: relative;
            transition:
                opacity .22s ease,
                filter .22s ease,
                background-color .22s ease;
        }

        .component.is-refreshing .component-status {
            opacity: .52;
            filter: blur(1.4px);
        }

        .component-status {
            transition:
                opacity .2s ease,
                filter .2s ease,
                color .2s ease;
        }

        .component.is-changed {
            animation: azari-status-change .65s ease;
        }

        @keyframes azari-status-change {
            0% {
                background: var(--brand-soft);
            }

            100% {
                background: transparent;
            }
        }

        .status-refresh-state {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 18px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .status-refresh-state::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
        }

        .status-refresh-state.is-checking::before {
            border: 2px solid var(--line);
            border-top-color: var(--brand);
            background: transparent;
            animation: azari-spin .7s linear infinite;
        }

        .status-refresh-state.is-delayed {
            color: var(--amber);
        }

        .status-refresh-state.is-delayed::before {
            background: var(--amber);
        }

        @keyframes azari-spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* =====================================================
           LEGEND
           ===================================================== */

        .legend {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px 22px;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 650;
        }

        .legend-item .legend-dot {
            width: 10px;
            height: 10px;
            flex: 0 0 10px;
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, .035);
        }

        .legend-operational .legend-dot {
            background: var(--green);
        }

        .legend-degraded .legend-dot {
            background: var(--amber);
        }

        .legend-partial .legend-dot {
            background: var(--orange);
        }

        .legend-major .legend-dot {
            background: var(--red);
        }

        .history-bar {
            transition:
                background-color .25s ease,
                opacity .25s ease;
        }

        .overall {
            transition:
                background .3s ease,
                border-color .3s ease;
        }

        @media (prefers-reduced-motion: reduce) {
            .component,
            .component-status,
            .history-bar,
            .overall {
                transition: none;
            }

            .component.is-changed {
                animation: none;
            }
        }

    </style>

    <!-- AZARI_STATUS_STICKY_TOPBAR_V112 -->
    <style>
        .topbar {
            position: sticky !important;
            top: 0 !important;
            z-index: 1000 !important;
            background: rgba(5, 32, 88, .94) !important;
            border-bottom: 1px solid rgba(5, 32, 88, .82);
            -webkit-backdrop-filter: blur(16px);
            backdrop-filter: blur(16px);
        }
    </style>

</head>

<body>
@php
    $labels = [
        'operational' => 'Operational',
        'degraded' => 'Degraded',
        'partial_outage' => 'Partial outage',
        'major_outage' => 'Major outage',
        'unknown' => 'Status delayed',
    ];

    $headlines = [
        'operational' => 'All systems operational',
        'degraded' => 'Some systems are experiencing degraded performance',
        'partial_outage' => 'A service disruption has been detected',
        'major_outage' => 'A significant service disruption has been detected',
        'unknown' => 'Current status is temporarily delayed',
    ];
@endphp

<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" href="/">
            <img
                src="/public/images/logo-light.png"
                alt=""
                aria-hidden="true"
            >

            <span class="brand-copy">
                <strong>Resavar</strong>
                <span>System Status</span>
            </span>
        </a>

        <a class="home-link" href="/">
            Return to site
        </a>
    </div>
</header>

<main>
    <div class="wrap">
        <section class="intro">
            <p class="eyebrow">Platform availability</p>

            <h1>System status</h1>

            <p class="intro-copy">
                Current operational health for Resavar guest-facing
                and transaction-processing services.
            </p>
        </section>

        <section id="status-overall" class="overall status-{{ $overall }}">
            <div class="overall-main">
                <span
                    class="large-dot"
                    aria-hidden="true"
                ></span>

                <div class="overall-copy">
                    <strong id="status-headline">
                        {{ $headlines[$overall] ?? 'Current status unavailable' }}
                    </strong>

                    <span id="status-overall-label">
                        {{ $labels[$overall] ?? 'Unknown' }}
                    </span>
                </div>
            </div>

            <div id="status-last-checked" class="last-checked">
                @if($checkedAt)
                    Last checked
                    {{ $checkedAt->diffForHumans() }}
                @else
                    Waiting for the next automated check
                @endif
            </div>
        </section>

        @if($stale)
            <div class="notice">
                <strong>Status checks are delayed.</strong>
                The information below may be older than expected.
            </div>
        @endif

        <section class="section">
            <div class="section-head">
                <h2>Platform components</h2>
                <span>Current state</span>
            </div>

            @forelse($components as $component)
                @php
                    $state = $component['status'] ?? 'unknown';
                @endphp

                <article
                    class="component"
                    data-status-component="{{ $component['key'] ?? '' }}"
                >
                    <div>
                        <p class="component-name">
                            {{ $component['name'] ?? 'Platform service' }}
                        </p>

                        <p class="component-description">
                            {{ $component['description'] ?? 'Service health.' }}
                        </p>
                    </div>

                    <div
                        class="pill component-status status-{{ $state }}"
                        data-component-status
                    >
                        <span
                            class="dot"
                            aria-hidden="true"
                        ></span>

                        {{ $labels[$state] ?? 'Unknown' }}
                    </div>
                </article>
            @empty
                <article class="component">
                    <div>
                        <p class="component-name">
                            Automated status
                        </p>

                        <p class="component-description">
                            Waiting for the next health snapshot.
                        </p>
                    </div>

                    <div class="pill status-unknown">
                        <span
                            class="dot"
                            aria-hidden="true"
                        ></span>

                        Status delayed
                    </div>
                </article>
            @endforelse
        </section>

        <section class="section">
            <div class="section-head">
                <h2>Recent platform health</h2>
                <span>Approximately 24 hours</span>
            </div>

            <div class="history">
                <div
                    id="status-history-track"
                    class="history-track"
                    aria-label="Recent platform health history"
                >
                    @forelse($historyBars as $bar)
                        <span
                            class="history-bar status-{{ $bar }}"
                            title="{{ $labels[$bar] ?? 'Unknown' }}"
                        ></span>
                    @empty
                        @for($i = 0; $i < 48; $i++)
                            <span
                                class="history-bar status-unknown"
                            ></span>
                        @endfor
                    @endforelse
                </div>

                <div class="history-scale">
                    <span>24 hours ago</span>
                    <span>Now</span>
                </div>
            </div>
        </section>

        <div
            id="status-refresh-state"
            class="status-refresh-state"
            aria-live="polite"
        >
            Status up to date
        </div>

        <div class="notice">
            <strong>About these checks.</strong>
            {{ $notice }}

            Status reflects the Resavar platform's own service
            availability and processing readiness. Upstream service
            incidents may take time to surface through application
            health checks.

            <div class="legend" aria-label="Status legend">
                <span class="legend-item legend-operational">
                    <span class="legend-dot" aria-hidden="true"></span>
                    Operational
                </span>

                <span class="legend-item legend-degraded">
                    <span class="legend-dot" aria-hidden="true"></span>
                    Degraded
                </span>

                <span class="legend-item legend-partial">
                    <span class="legend-dot" aria-hidden="true"></span>
                    Partial outage
                </span>

                <span class="legend-item legend-major">
                    <span class="legend-dot" aria-hidden="true"></span>
                    Major outage
                </span>
            </div>
        </div>
    </div>
</main>

<footer>
    <div class="wrap">
        Automated operational information only.
        No infrastructure, provider or account information is
        published on this page.
    </div>
</footer>

<script>
(() => {
    'use strict';

    const endpoint = '/status/data';
    const pollEveryMs = 60_000;
    const requestTimeoutMs = 8_000;

    const labels = {
        operational: 'Operational',
        degraded: 'Degraded',
        partial_outage: 'Partial outage',
        major_outage: 'Major outage',
        unknown: 'Status delayed',
    };

    const headlines = {
        operational: 'All systems operational',
        degraded: 'Some systems are experiencing degraded performance',
        partial_outage: 'A service disruption has been detected',
        major_outage: 'A significant service disruption has been detected',
        unknown: 'Current status is temporarily delayed',
    };

    const allowedStatuses = new Set(
        Object.keys(labels)
    );

    let requestInFlight = false;
    let lastSuccessfulUpdate = Date.now();

    const refreshState = document.getElementById(
        'status-refresh-state'
    );

    const overallCard = document.getElementById(
        'status-overall'
    );

    const headline = document.getElementById(
        'status-headline'
    );

    const overallLabel = document.getElementById(
        'status-overall-label'
    );

    const lastChecked = document.getElementById(
        'status-last-checked'
    );

    const historyTrack = document.getElementById(
        'status-history-track'
    );

    function safeStatus(value) {
        return allowedStatuses.has(value)
            ? value
            : 'unknown';
    }

    function setRefreshState(mode, message) {
        if (!refreshState) {
            return;
        }

        refreshState.classList.remove(
            'is-checking',
            'is-delayed'
        );

        if (mode === 'checking') {
            refreshState.classList.add(
                'is-checking'
            );
        }

        if (mode === 'delayed') {
            refreshState.classList.add(
                'is-delayed'
            );
        }

        refreshState.textContent = message;
    }

    function beginChecking() {
        setRefreshState(
            'checking',
            'Checking current status…'
        );

        document
            .querySelectorAll(
                '[data-status-component]'
            )
            .forEach((row) => {
                row.classList.add(
                    'is-refreshing'
                );
            });
    }

    function finishChecking() {
        document
            .querySelectorAll(
                '[data-status-component]'
            )
            .forEach((row) => {
                row.classList.remove(
                    'is-refreshing'
                );
            });
    }

    function updateOverall(status) {
        status = safeStatus(status);

        if (!overallCard) {
            return;
        }

        for (const state of allowedStatuses) {
            overallCard.classList.remove(
                `status-${state}`
            );
        }

        overallCard.classList.add(
            `status-${status}`
        );

        if (headline) {
            headline.textContent =
                headlines[status]
                ?? headlines.unknown;
        }

        if (overallLabel) {
            overallLabel.textContent =
                labels[status]
                ?? labels.unknown;
        }
    }

    function updateComponents(components) {
        if (!Array.isArray(components)) {
            return;
        }

        for (const component of components) {
            if (
                !component
                || typeof component.key !== 'string'
            ) {
                continue;
            }

            const row = document.querySelector(
                `[data-status-component="${CSS.escape(component.key)}"]`
            );

            if (!row) {
                continue;
            }

            const pill = row.querySelector(
                '[data-component-status]'
            );

            if (!pill) {
                continue;
            }

            const newStatus = safeStatus(
                component.status
            );

            const oldStatus = Array
                .from(pill.classList)
                .find(
                    (name) =>
                        name.startsWith('status-')
                );

            const normalizedOld = oldStatus
                ? oldStatus.replace(
                    'status-',
                    ''
                )
                : null;

            for (const state of allowedStatuses) {
                pill.classList.remove(
                    `status-${state}`
                );
            }

            pill.classList.add(
                `status-${newStatus}`
            );

            pill.innerHTML = '';

            const dot = document.createElement(
                'span'
            );

            dot.className = 'dot';
            dot.setAttribute(
                'aria-hidden',
                'true'
            );

            pill.appendChild(dot);
            pill.append(
                document.createTextNode(
                    labels[newStatus]
                    ?? labels.unknown
                )
            );

            if (
                normalizedOld
                && normalizedOld !== newStatus
            ) {
                row.classList.remove(
                    'is-changed'
                );

                void row.offsetWidth;

                row.classList.add(
                    'is-changed'
                );
            }
        }
    }

    function updateHistory(history) {
        if (
            !historyTrack
            || !Array.isArray(history)
        ) {
            return;
        }

        const states = history
            .slice(-48)
            .map(safeStatus);

        if (states.length === 0) {
            return;
        }

        historyTrack.innerHTML = '';

        for (const status of states) {
            const bar = document.createElement(
                'span'
            );

            bar.className =
                `history-bar status-${status}`;

            bar.title =
                labels[status]
                ?? labels.unknown;

            historyTrack.appendChild(bar);
        }
    }

    function formatCheckedAt(value) {
        if (!value) {
            return 'Last check time unavailable';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return 'Last check time unavailable';
        }

        const seconds = Math.max(
            0,
            Math.round(
                (Date.now() - date.getTime())
                / 1000
            )
        );

        if (seconds < 60) {
            return 'Last checked just now';
        }

        const minutes = Math.floor(
            seconds / 60
        );

        if (minutes === 1) {
            return 'Last checked 1 minute ago';
        }

        if (minutes < 60) {
            return `Last checked ${minutes} minutes ago`;
        }

        const hours = Math.floor(
            minutes / 60
        );

        return hours === 1
            ? 'Last checked 1 hour ago'
            : `Last checked ${hours} hours ago`;
    }

    async function poll() {
        if (requestInFlight) {
            return;
        }

        requestInFlight = true;

        const controller =
            new AbortController();

        const timer = window.setTimeout(
            () => controller.abort(),
            requestTimeoutMs
        );

        beginChecking();

        try {
            const response = await fetch(
                `${endpoint}?t=${Date.now()}`,
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                    cache: 'no-store',
                    credentials: 'same-origin',
                    signal: controller.signal,
                }
            );

            if (!response.ok) {
                throw new Error(
                    `Status request returned ${response.status}`
                );
            }

            const data = await response.json();

            if (
                !data
                || data.ok !== true
                || typeof data.overall !== 'string'
                || !Array.isArray(data.components)
            ) {
                throw new Error(
                    'Malformed status response'
                );
            }

            updateOverall(
                data.stale
                    ? 'unknown'
                    : data.overall
            );

            updateComponents(
                data.components
            );

            updateHistory(
                data.history
            );

            if (lastChecked) {
                lastChecked.textContent =
                    formatCheckedAt(
                        data.checked_at
                    );
            }

            lastSuccessfulUpdate =
                Date.now();

            if (data.stale) {
                setRefreshState(
                    'delayed',
                    'Status data is delayed'
                );
            } else {
                setRefreshState(
                    'ready',
                    'Status up to date'
                );
            }
        } catch (error) {
            /*
             * Never erase or downgrade existing status merely because
             * this browser failed to refresh it. Keep the last known
             * values visible and report only that live refresh is delayed.
             */

            const elapsed =
                Date.now()
                - lastSuccessfulUpdate;

            setRefreshState(
                'delayed',
                elapsed > 300_000
                    ? 'Live updates are temporarily delayed'
                    : 'Unable to refresh just now — showing last known status'
            );
        } finally {
            window.clearTimeout(timer);

            finishChecking();

            requestInFlight = false;
        }
    }

    /*
     * Do not immediately blur the server-rendered page on load.
     * Give the visitor a stable first paint, then quietly verify.
     */
    window.setTimeout(
        poll,
        5_000
    );

    window.setInterval(
        poll,
        pollEveryMs
    );

    /*
     * Refresh when the tab becomes visible again, but still respect
     * the in-flight guard.
     */
    document.addEventListener(
        'visibilitychange',
        () => {
            if (
                document.visibilityState
                === 'visible'
            ) {
                poll();
            }
        }
    );
})();
</script>

</body>
</html>
