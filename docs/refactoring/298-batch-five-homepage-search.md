# Batch 05/18 — Expedia-grade accommodation discovery homepage

**Tracker:** [#298](https://github.com/wbizmo/azari-residences/issues/298). Linked tasks [#264](https://github.com/wbizmo/azari-residences/issues/264) and [#285](https://github.com/wbizmo/azari-residences/issues/285). The source of truth remains the existing Laravel search and booking stack, NOT a second mock search system.

## Delivered implementation

- An editorial, accommodation-only first impression uses the checked-in `resavar-hero.png`, the Batch 04 real-logo navigation and the existing hero/image rendering. The primary "Check availability" action scrolls directly to the **one** homepage search form.
- **Destination first.** The form searches a city, area or property name with the existing accessible ARIA combobox. The existing autocomplete endpoint distinguishes real destinations and property listings, supports keyboard arrows, Enter, Escape, live polite results and typo fallback. A new input invalidates stale autocomplete choices/IDs immediately, even before the debounced fetch completes. Invalid or offline suggestions never stop text-only search.
- Default to a real future two-day-ahead arrival and following departure (rather than missing dates), while respecting remembered dates when present. Check-in/out use native date inputs. Changed checkout minimum now uses *local calendar arithmetic*, not `Date.toISOString()` which can shift the day depending on timezone.
- Real adult/child/room quantities show on desktop as well as mobile; clicking stepper controls updates hidden native GET fields. With scripting blocked, the hero search still submits and links to the existing full availability form for changing guest quantities. Server-side validation, including range and past-date rules, remains the authority.
- A mobile-only fixed shortcut is available **only above the actual form** and hides as the real search enters the viewport; it never blankets results, guest details or the footer. No JS is required to reach the search using the hero button or keyboard anchors.
- The backend already had canonical `AvailabilitySearchController` → `availability.results` URL preservation and `MarketplaceSearchService` date/room/guest/rate quotation. This PR **reuses** that path and changes no hold, payment, booking-approval or Pesapal logic. Results remain a snapshot; final holds always revalidate.
- Reusable `Property::scopeDiscoverable` means homepage features, new listings, destination shortcuts/type counts and destination/property autocomplete only advertise currently **published + available** properties with at least one active published accommodation type, remaining configured room inventory and a public active rate plan. This is strictly preflight **discovery**, not proof of specific date sellability. The listings may still be sold out on a given date; authoritative search/quote handles that.
- Removed date-agnostic legacy "From nightly price" numbers from featured cards. Cards instead instruct guests to enter dates for actual live quotes; no fake badges/reviews/availability counts. Responsive-image paths reuse existing moderated photo selection and image variants. Added compact factual search expectations and real owner listing link, conditional on the route existing.
- Previously cached autocomplete JSON could keep advertising an owner-unpublished listing for ~60 seconds. It is now uncached with `private, no-store` so moderation updates take effect on the next request (subject to backend DB consistency).
- Existing Android app-download panel, `azari-hospitality-welcome.png` and `google-play-badge.png` remain. No new iOS availability claim and no fabricated replacement image. The legacy Google Play URL has not been changed pending independent operator validation of package branding.

## Code touchpoints

- `app/Models/Property.php`, `app/Http/Controllers/PublicSite/HomeController.php`, `app/Services/Search/DestinationSearchService.php`, `app/Http/Controllers/PublicSite/DestinationSearchController.php`: honest, live discovery filters.
- `resources/views/public/home.blade.php`: destination-first date/guests/stay type search, internal hero CTA, sticky mobile shortcut, no unsourced prices, owner CTA and real-image variants.
- `resources/css/reserva/home-search.css`, `resources/css/reserva/reserva.css`: compact navy/white component styles across 320–1440px, focus, responsive grids, mobile safe-area, no layout-blocking JS.
- `resources/js/app.js`, `resources/js/resavar-home-search.js`: prevent stale selection, local-date checkout min, optional small-screen shortcut.
- `tests/Feature/PublicSite/BatchFiveHomeSearchTest.php` and `.github/workflows/reserva-ci.yml`: real DB tests of discoverability, unpublication, GET handoff, range rejection and semantic homepage markup, integrated into CI.

## Test and performance acceptance

```bash
composer install --no-interaction
npm ci && npm run build
php artisan view:cache
php artisan test tests/Feature/PublicSite/BatchFiveHomeSearchTest.php
php artisan test tests/Feature/Auth
php artisan test tests/Feature/Bookings
php artisan test tests/Feature/Architecture
php artisan test
php artisan route:cache
php artisan azari:release-gate --ci
```

In Playwright, validate homepage form from keyboard only, 320/390/768/1024/1440px and 200% zoom, iOS/mobile date inputs, timezone/DST/leap date, page-scroll/no-horizontal-overflow, dest suggestions (type, select, edit, offline fallback, Tab, Escape, outside click, typo), guest controls, actual query URL, no results, disabled JavaScript and stale quote handling. Test loading images with approved and unapproved moderation statuses. Confirm user/owner/admin cross-role transitions and genuine Google Play listing URL. Record Core Web Vitals with a realistic mobile connection: p75 LCP <=2.5s, INP <=200ms, CLS <=0.1. Use unseeded real supply; do not report synthetic inventory as genuine. GitHub Actions can check syntax, Blade, assets and targeted Laravel DB tests but **cannot certify** these real-device or production performance budgets.

## Known remaining work / status

The homepage and site-shell foundations are improved, but #264/#285 should remain open until full **operator-reviewed** browser/visual, Lighthouse/Core Web Vitals and zero-broken-link acceptance; live listings must also pass property-owner image/licence verification and fresh availability. The larger owner/guest/admin page-by-page redesign is tracked separately. Data migrations, destructive financial edits, speculative reviews and third-party flight/activity/dining inventory are out of scope.

## Rollback

Revert this PR, rebuild Vite and clear Laravel view caches. No schema/data migration, booking modifications, financial mutation, credential activation, provider synchronization or production deployment. This may restore less conservative merchandising; review carefully before rollback.
