# Resavar accommodation-first UI: canonical design brief

This is the durable design reference for epic #249, UI issues #263–#274 and draft PR #275. The original interactive HTML proof of concept was supplied in the associated design conversation. **Do not treat the preview's hardcoded numbers or mock property cards as production data.**

## Scope and design identity

Resavar is an accommodation marketplace for hotels, rooms, apartments, serviced stays, villas and residences. User journeys: find a stay, submit a manual request, receive owner/admin review, pay through Pesapal, and manage the stay. Hotel/property owners submit listings, manage availability and rates, message guests, and request **manual** withdrawals. Administrators create properties directly, review new listings/requests and approve withdrawals separately from recording actual bank transfers. No flights, cars, transfers, external travel inventory, dining bookings or multi-product packages.

Visual character: quietly premium, editorial and welcoming. Keep navy `#052058` as the primary ink/action color against pure white and cool low-contrast surface blues; high-contrast text. Use considered serif editorial display accents with consistent readable sans text for labels/forms, wide photography crops, asymmetrical hero proportions, spacious grids, rounded yet restrained cards and prominent stay-search forms. Avoid shiny gradients, oversized badges, generic icon spam or multiple unrelated design systems.

## Use the real repository assets

- `public/images/logo-light.png` on **white or light** header/cards (navy/dark mark).
- `public/images/logo-dark.png` on **navy or dark** regions (white/light mark).
- Use the existing `<x-brand-logo>` component, and ensure the header logo switches in sync with its background.
- `public/images/resavar-hero.png` for the main accommodation hero.
- `public/images/azari-guest-auth-suite.png` and/or `public/images/azari-auth-lounge.png` for sign-in, registration and recovery.
- `public/images/azari-residence-fallback.png` and moderated listing media for property cards.
- Preserve the **existing** app-download section, `azari-hospitality-welcome.png`, and `google-play-badge.png`, retaining validated working app-store links. Do not invent iOS download availability.
- Prefer existing image component/source and responsive image derivatives; all decorative crops still need suitable alt handling.

## Reusable layouts

1. **Guest public**: crisp logo/nav, editorial accommodation hero, one prominent destination/dates/guests search, type chips, filtered property grid, rates, property detail gallery and request CTA, transparent availability/status, owner-listing CTA, existing app-download section, policies, footer.
2. **Guest account**: shared sidebar/mobile drawer, My Stays (not Trips), requests awaiting approval, payment action through Pesapal, documents, support, messages and profile.
3. **Property owner**: overview, properties/applications, calendar and rate plans, booking requests, messages, earnings, withdrawals. Withdrawal form requires currency, amount and free-text bank/receiving details; admin reviews before any manual payment.
4. **Admin**: staff-only operations overview, create/assign property, owner applications, booking approvals, payments/Pesapal reconciliation, manual withdrawal review, CMS, support, staff, permissions and audit trail. No supplier panel or automated payout action.
5. **Authentication**: shared split-photo shell (the first incremental implementation in PR #275) with real mark, responsive photo panel, form constraints, validation, password reveal/accessibility and preserved CSRF/honeypot/account roles.

## UI conventions

- Use a **square with an upward-right pointing arrow** (external-link / Material Symbols `open_in_new`) where an external-link affordance is needed. Do not use a bare diagonal ↗ glyph. Internal navigation may use ordinary chevrons as appropriate.
- One button/input/select/textarea/component system with consistent heights, borders, typography, focus ring, disabled and loading states.
- Text and icon contrast must meet WCAG AA: 4.5:1 ordinary text; 3:1 large text and essential control borders. Navy-on-navy and white-on-white are prohibited.
- Support keyboard focus, 200% zoom, reduced motion, screen reader messages and visual widths 320/390/768/1024/1440.
- Responsive mobile layouts must retain search and booking controls, avoid squeezed currency fields, overflowing price tables and horizontal form scrolling.
- Keep actual Laravel routes, identity/privacy checks and existing payment correctness. Do not make mock HTML UI a parallel application.

## Rollout checklist

- Issue #274 and draft PR #275: auth foundation and real logo.
- Issue #263: design tokens/components.
- Issue #264: public homepage including existing app download.
- Issue #265: results, detail and booking.
- Issue #266: guest account.
- Issue #267: owner workspace and withdrawals.
- Issue #268: admin workspace.
- Issue #269: receipts, invoices, notification templates.
- Issue #273: screenshot and accessibility QA.
- Preserve PHP 8.4 local verification, lightweight CI and separately owner-run production/Pesapal acceptance. **Nothing in the mockup authorizes deployment or changes to financial logic.**
