<style id="azari-canonical-shell-layout">
/*
 * AZARI CANONICAL ADMIN + GUEST SHELL
 * One layout calculation per shell and one breakpoint per sidebar.
 */

html,
body.az-admin-body,
body.az-user-body {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
}

body.az-admin-body *,
body.az-admin-body *::before,
body.az-admin-body *::after,
body.az-user-body *,
body.az-user-body *::before,
body.az-user-body *::after {
    box-sizing: border-box;
}

/* Material Symbols remain readable inside both authenticated shells. */
.az-admin-body .material-symbols-outlined,
.az-user-body .material-symbols-outlined {
    font-family: 'Material Symbols Outlined' !important;
    font-weight: normal !important;
    font-style: normal !important;
    line-height: 1;
    letter-spacing: normal;
    text-transform: none;
    white-space: nowrap;
    word-wrap: normal;
    direction: ltr;
    font-feature-settings: 'liga' !important;
    -webkit-font-feature-settings: 'liga' !important;
    -webkit-font-smoothing: antialiased;
    visibility: visible !important;
    opacity: 1 !important;
}

/* =========================================================
   ADMIN + STAFF SHELL — sidebar breakpoint: 900px
   ========================================================= */
.az-admin-app {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

.az-admin-main {
    width: auto !important;
    max-width: none !important;
    min-width: 0 !important;
    margin-left: var(--az-admin-sidebar-width, 286px) !important;
    padding-top: var(--az-admin-topbar-height, 78px);
    overflow-x: clip !important;
}

.az-admin-topbar {
    right: 0 !important;
    left: var(--az-admin-sidebar-width, 286px) !important;
    width: auto !important;
    max-width: none !important;
    min-width: 0 !important;
}

.az-admin-content {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-x: clip !important;
}

.az-admin-main > *,
.az-admin-topbar > *,
.az-topbar-start,
.az-topbar-actions,
.az-topbar-page,
.az-profile-menu,
.az-admin-content > *,
.az-admin-content section,
.az-admin-content article,
.az-admin-content form,
.az-admin-content [class*="grid"],
.az-admin-content [class*="flex"] {
    min-width: 0 !important;
    max-width: 100%;
}

.az-topbar-start,
.az-topbar-actions {
    flex: 0 1 auto;
}

.az-topbar-page {
    overflow: hidden;
}

.az-topbar-page strong,
.az-topbar-page__eyebrow {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.az-admin-table-wrap,
.az-s78-table-wrap,
.az-responsive-table-shell,
.az-admin-content .overflow-x-auto,
.az-admin-content .table-responsive,
.az-admin-content [class*="table-wrapper"] {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-x: auto !important;
    overflow-y: hidden;
    overscroll-behavior-x: contain;
    -webkit-overflow-scrolling: touch;
}

.az-admin-content img,
.az-admin-content svg,
.az-admin-content canvas,
.az-admin-content iframe,
.az-admin-content video,
.az-admin-content input,
.az-admin-content select,
.az-admin-content textarea,
.az-admin-content button {
    max-width: 100%;
    min-width: 0;
}

.az-admin-content p,
.az-admin-content li,
.az-admin-content dd,
.az-admin-content code,
.az-admin-content samp,
.az-admin-content output {
    overflow-wrap: anywhere;
    word-break: break-word;
}

@media (min-width: 901px) {
    .az-admin-sidebar {
        transform: translateX(0) !important;
        visibility: visible !important;
    }

    .az-sidebar-trigger,
    .az-sidebar-close,
    .az-admin-backdrop {
        display: none !important;
    }
}

@media (max-width: 900px) {
    .az-admin-sidebar {
        transform: translateX(-102%) !important;
    }

    .az-admin-sidebar.is-open {
        transform: translateX(0) !important;
    }

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
    }

    .az-sidebar-trigger,
    .az-sidebar-close {
        display: inline-grid !important;
    }

    .az-admin-content,
    .az-admin-content > * {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }
}

@media (max-width: 680px) {
    .az-admin-topbar {
        gap: 12px;
    }

    .az-topbar-actions {
        margin-left: auto;
    }

    .az-s78-operational-timezone,
    .az-profile-trigger__copy,
    .az-profile-chevron {
        display: none !important;
    }
}

/* =========================================================
   GUEST/CUSTOMER SHELL — sidebar breakpoint: 980px
   ========================================================= */
.az-user-shell {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-x: clip !important;
}

.az-user-main {
    width: calc(100% - var(--az-user-sidebar, 286px)) !important;
    max-width: calc(100vw - var(--az-user-sidebar, 286px)) !important;
    min-width: 0 !important;
    margin-left: var(--az-user-sidebar, 286px) !important;
    overflow-x: clip !important;
}

.az-user-topbar,
.az-user-content {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

.az-user-content {
    overflow-x: clip !important;
}

.az-user-topbar > *,
.az-user-topbar-left,
.az-user-profile-chip,
.az-user-profile-meta,
.az-user-content > *,
.az-user-content section,
.az-user-content article,
.az-user-content form,
.az-user-content [class*="grid"],
.az-user-content [class*="flex"] {
    min-width: 0 !important;
    max-width: 100%;
}

.az-user-table-wrap,
.az-user-content .overflow-x-auto,
.az-user-content .table-responsive,
.az-user-content [class*="table-wrapper"] {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-x: auto !important;
    overflow-y: hidden;
    overscroll-behavior-x: contain;
    -webkit-overflow-scrolling: touch;
}

.az-user-content img,
.az-user-content svg,
.az-user-content canvas,
.az-user-content iframe,
.az-user-content video,
.az-user-content input,
.az-user-content select,
.az-user-content textarea,
.az-user-content button {
    max-width: 100%;
    min-width: 0;
}

@media (min-width: 981px) {
    .az-user-sidebar {
        display: flex !important;
    }

    .az-user-mobile-drawer,
    .az-user-mobile-backdrop,
    .az-user-menu-button,
    .az-user-mobile-bottom {
        display: none !important;
    }
}

@media (max-width: 980px) {
    .az-user-sidebar {
        display: none !important;
    }

    .az-user-main {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
    }

    .az-user-topbar,
    .az-user-content,
    .az-user-content > * {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    .az-user-menu-button {
        display: inline-grid !important;
    }
}

@media (max-width: 720px) {
    .az-user-profile-meta {
        display: none;
    }
}
/* AZARI_USER_TOPBAR_WIDTH_FIX_V1 */
.az-user-topbar {
    width: auto !important;
    max-width: none !important;
    min-width: 0 !important;
}
/* AZARI_USER_TOPBAR_WIDTH_FIX_V1_END */
</style>
