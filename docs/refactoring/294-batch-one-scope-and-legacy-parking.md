# Resavar Batch 01 / 18 — accommodation-only scope and parked legacy records

**Tracker:** [#294](https://github.com/wbizmo/azari-residences/issues/294) • **Current product decision:** [#249](https://github.com/wbizmo/azari-residences/issues/249) • **No deployment authorized by this document.**

## Product boundary and issue disposition

Active inventory is limited to hotels, resorts, apartments, serviced apartments, villas, residences and holiday homes. Owners may register and manage moderated property inventory; guests may request/resume bookings; staff may review them. Pesapal remains a booking-payment integration subject to independent settlement verification. In-app guest/property messaging, local in-stay service requests, financial ledgers and receipts remain in scope.

- **Historical reference, completed:** #250 (retired endpoints and navigation) and #251 (data-preserving archives and rollback plan) remain closed. Their implementation is retained, not rebuilt or reopened.
- **Active product governance:** #37 and #249 remain high-level trackers, not permission to ship anything beyond accommodation.
- **Parked cross-product ambitions:** the external-provider/channel portions of #40, travel-platform epic #41 and #86 (activities), #87 (rental cars), #88 (flights), #89 (multi-product packages) and #91 (dining) are historical planning records only. Do **not** implement, advertise or reactivate them without a fresh written product decision. Accommodation-relevant Phase 3 work remains independently sequenced in subsequent batches.
- **Out of scope now:** flight/PNR inventory, airport transfers, car rental, third-party activities, restaurant table booking, cross-product assembly, OTA/GDS/PMS supplier synchronization, external calendar pushes, new partner-api booking intents and their webhooks. Never claim real availability from a legacy catalog.

## Existing isolation and this batch's enforcement

Retired travel routes retain their names but answer **410** after authentication where applicable; old public marketing links redirect to accommodation search. Supplier/channel webhooks, supplier administration, dining endpoints, partner API and legacy channel-export endpoints are behind the retirement middleware. Previously queued channel sync jobs no-op. Travel/channel/dining scheduler entries are excluded. Historical guest and staff travel archives are authenticated, no-store and GET-only; original travel, dining, supplier, channel, imported reservations and financial tables are **not dropped**.

Batch 01 adds two regression barriers:
1. `config/reserva.php` forces `accommodation_only=true` whenever `APP_ENV=production`, even if `RESAVAR_ACCOMMODATION_ONLY=false`. Non-production compatibility tests may opt out explicitly; production cannot revive unsupported services by flipping an environment switch.
2. `azari:release-gate --ci` now fails if the accommodation-only flag is false, if any known retired entry point loses its middleware, if a historical archive gains a write verb or retirement guard, or if an unsupported supplier/channel/dining command re-enters the scheduler. This is a lightweight deterministic code/config gate, **not** a financial or security certification.

No new provider credentials, schema changes, external calls, payment initiation or modifications to legacy records are introduced.

## Local acceptance — run against a disposable migrated database

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm run build
php artisan config:cache
php artisan route:cache
php artisan azari:release-gate --ci
php artisan test tests/Feature/Architecture/AccommodationOnlyScopeTest.php \
  tests/Feature/Architecture/AccommodationHistoricalArchiveTest.php
php artisan test tests/Feature/Bookings tests/Feature/Payments
php artisan route:list --path=webhooks
php artisan schedule:list
```

Also run the **full local Laravel suite**, legacy archive IDOR and role-negative tests, MySQL/InnoDB booking/inventory contention, local desktop/mobile Playwright navigation, old URL redirects, sitemap/link checks, Pesapal callbacks and real financial reconciliation in an isolated authorized test setting. The GitHub CI pipeline intentionally performs only syntax/build/release gates. **A passing CI check alone does not establish Batch 1 production acceptance.**

Failure cases to verify: stale webhook cannot create or amend a travel request, a queued pre-cutover channel job performs no provider I/O, a legacy URL returns a correct 410/301, user A cannot read user B's archive, staff archive cannot be written to, and an old imported reservation continues to block sale until appropriately reconciled. Do not delete history, replay supplier settlement/compensation, or force-release blocked stock.

## Controlled handoff and rollback

1. Operator captures verified restorable DB and private-media backups, running schema version and release SHA; audits pending external supplier events, imported channel reservations, unsettled refunds, open disputes and owner ledgers.
2. Drain/pause former provider workers and callbacks. Do not purge old events, vouchers, financial references or queued failure evidence. Check the active Pesapal webhook/reconciliation paths and room inventory before accepting bookings.
3. Deploy via a reviewed, separately approved change; ensure production `APP_ENV=production`, rebuild config/route cache, and execute release gate, archive permissions, 301/410 probes, booking/payment smoke and observability checks.
4. Revert the application release on regression, with all legacy tables untouched. **Do not** run destructive rollbacks or set the environment flag to bypass this product decision; deliberate cross-product reactivation requires a new scoped decision, legal/provider review, tests and code changes.
5. Log operator acceptance and provider/live outcomes separately in release issue #123 and batch #311. This issue is not itself proof of production safety.

Related detail: [#251 data preservation map](251-accommodation-only-data-preservation.md), [operations runbook](../operations-runbook.md), [deployment handoff](../RESAVAR_DEPLOYMENT_HANDOFF.md).
