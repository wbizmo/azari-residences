<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-admin-theme="light">
<head>
    @include('partials.azari-head-assets')

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | Azari Residences Administration</title>

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /*
         * Material Symbols repair.
         */
        .az-admin-body .material-symbols-outlined {
            font-family: 'Material Symbols Outlined' !important;
            font-weight: normal !important;
            font-style: normal !important;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block !important;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            font-feature-settings: 'liga' !important;
            -webkit-font-feature-settings: 'liga' !important;
            -webkit-font-smoothing: antialiased;
            font-variation-settings:
                'FILL' 0,
                'wght' 300,
                'GRAD' 0,
                'opsz' 24;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /*
         * =====================================================
         * ADMIN VIEWPORT AND LONG-CONTENT OVERFLOW REPAIR
         * =====================================================
         *
         * This is intentionally scoped to the administration
         * layout. It repairs pages such as:
         *
         * - System Health
         * - Audit Logs
         * - Activity Logs
         * - Reports
         * - Environment or configuration displays
         * - Pages containing hashes, JSON, URLs or long values
         *
         * No Vite build is required because this CSS is inline.
         */

        html,
        body.az-admin-body {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .az-admin-app,
        .az-admin-main,
        .az-admin-content {
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }

        /*
         * Flex and grid children normally default to min-width:auto,
         * which allows long content to force the entire page wider
         * than the viewport. Force those children to shrink safely.
         */
        .az-admin-main > *,
        .az-admin-content > *,
        .az-admin-content > * > *,
        .az-admin-content .grid > *,
        .az-admin-content [class*="grid-cols-"] > *,
        .az-admin-content [class*="flex"] > * {
            min-width: 0;
        }

        /*
         * Common Azari card, panel and content containers.
         */
        .az-admin-content .az-card,
        .az-admin-content .az-panel,
        .az-admin-content .az-table-card,
        .az-admin-content .az-form-card,
        .az-admin-content .az-content-card,
        .az-admin-content .az-stat-card,
        .az-admin-content section,
        .az-admin-content article {
            max-width: 100%;
            min-width: 0;
        }

        /*
         * Long system values, log records, references and URLs must
         * wrap rather than increasing the document width.
         */
        .az-admin-content p,
        .az-admin-content li,
        .az-admin-content dd,
        .az-admin-content td,
        .az-admin-content th,
        .az-admin-content code,
        .az-admin-content samp,
        .az-admin-content output {
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        /*
         * Preserve readable formatting for JSON, stack traces and
         * log blocks while giving them their own horizontal scroll.
         */
        .az-admin-content pre {
            display: block;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: auto;
            overflow-y: auto;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            -webkit-overflow-scrolling: touch;
        }

        .az-admin-content pre code {
            white-space: inherit;
        }

        /*
         * Prevent inline code from forcing cards wider.
         */
        .az-admin-content :not(pre) > code {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        /*
         * Allow existing table wrappers to scroll instead of
         * dragging the entire page beyond the viewport.
         */
        .az-admin-content .overflow-x-auto,
        .az-admin-content .table-responsive,
        .az-admin-content .az-table-wrap,
        .az-admin-content .az-table-scroll,
        .az-admin-content [class*="table-wrapper"] {
            display: block;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: auto;
            overscroll-behavior-x: contain;
            -webkit-overflow-scrolling: touch;
        }

        /*
         * Tables remain tables, but may not establish a width larger
         * than their immediate scrolling container.
         */
        .az-admin-content table {
            max-width: 100%;
        }

        /*
         * Images, charts, SVGs and embedded content must remain
         * inside their cards.
         */
        .az-admin-content img,
        .az-admin-content svg,
        .az-admin-content canvas,
        .az-admin-content iframe,
        .az-admin-content video {
            max-width: 100%;
        }

        /*
         * Form controls containing long values should shrink within
         * their grid or flex container.
         */
        .az-admin-content input,
        .az-admin-content select,
        .az-admin-content textarea,
        .az-admin-content button {
            max-width: 100%;
            min-width: 0;
        }

        /*
         * Mobile-specific safety for administrative pages.
         */
        @media (max-width: 980px) {
            .az-admin-main {
                width: 100%;
                max-width: 100vw;
                min-width: 0;
                margin-left: 0;
            }

            .az-admin-content {
                width: 100%;
                max-width: 100vw;
                min-width: 0;
                overflow-x: hidden;
            }

            .az-admin-content > * {
                max-width: 100%;
                min-width: 0;
            }

            /*
             * Long logs and tables keep local scrolling instead of
             * making the whole page horizontally draggable.
             */
            .az-admin-content pre,
            .az-admin-content .overflow-x-auto,
            .az-admin-content .table-responsive,
            .az-admin-content .az-table-wrap,
            .az-admin-content .az-table-scroll {
                max-width: calc(100vw - 24px);
            }
        }
        
        /*
 * ADMIN MOBILE TOPBAR POSITION REPAIR
 *
 * The sidebar becomes an off-canvas drawer at 900px, so the
 * fixed topbar must stop reserving the desktop sidebar width.
 */
@media (max-width: 900px) {
    .az-admin-main {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
    }

    .az-admin-topbar {
        right: 0 !important;
        left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
}
    </style>

    @stack('head')
</head>

<body class="az-admin-body">
    <a class="az-skip-link" href="#az-admin-content">Skip to main content</a>

    <div
        class="az-admin-backdrop"
        data-sidebar-backdrop
        hidden
    ></div>

    <div class="az-admin-app">
        @include('admin.partials.sidebar')

        <div class="az-admin-main">
            @include('admin.partials.topbar')

            <main
                id="az-admin-content"
                class="az-admin-content"
                tabindex="-1"
            >
                @foreach (['status' => 'success', 'success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $flashKey => $flashTone)
                    @if (session($flashKey))
                        <div
                            class="az-alert az-alert--{{ $flashTone }}"
                            role="status"
                        >
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >
                                {{ $flashTone === 'danger' ? 'error' : ($flashTone === 'warning' ? 'warning' : 'check_circle') }}
                            </span>

                            <span>{{ session($flashKey) }}</span>
                        </div>
                    @endif
                @endforeach

                @if ($errors->any())
                    <div
                        class="az-alert az-alert--danger"
                        role="alert"
                    >
                        <span
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >
                            error
                        </span>

                        <div>
                            <strong>Please review the highlighted information.</strong>

                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')

    <x-azari-feedback />

    <x-azari-toasts />
</body>
</html>