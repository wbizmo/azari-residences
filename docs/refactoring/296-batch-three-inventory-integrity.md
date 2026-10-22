# Resavar Batch 03/18 — Accommodation inventory and occupancy integrity

Tracking [#296](https://github.com/wbizmo/azari-residences/issues/296). In-scope: [#257](https://github.com/wbizmo/azari-residences/issues/257), [#74](https://github.com/wbizmo/azari-residences/issues/74). [#252](https://github.com/wbizmo/azari-residences/issues/252) is already implemented and closed; do not reopen it. The historical external reservation webhook/outbox task [#70](https://github.com/wbizmo/azari-residences/issues/70) is **parked** because external PMS/channel connections are retired by [#249](https://github.com/wbizmo/azari-residences/issues/249) and Batch 01.

## Existing architecture preserved

- Booking holds and canonical availability: `AzariAvailabilityEngine`, property → accommodation type → nights row-lock ordering, old channel reservation blocks retained until formally reconciled.
- Guest total quote: `AzariPricingEngine` for search, booking and immutable accepted pricing snapshots.
- Staff/owner calendar edit: `InventoryBulkUpdateService` preview → expected revision → atomic apply → audited undo with safety rechecks.
- Separate hotel room types, rate plans, minimum/maximum stays, night overrides and manual edit rights.
- Owner yield advice requires human approval, is never automatically activated and uses the same calendar audit log.

## Code changes

1. **Room-count reduction guard.** `CommercialInventoryManager::saveAccommodationType` now checks the new capacity **inside** the existing property and type transaction/lock against (a) future explicit nightly allotments and (b) canonical occupied nights from paid/confirmed/pending-active bookings, active holds and retained conflicting external channel reservations. All lookahead comparisons use the property's calendar timezone. Existing bookings, holds, rate snapshots and ledgers are never modified. Rejections leave previous property publication and room count intact.
2. **Conservative horizon.** The guard covers at least 366 days and extends as far as the latest active future booking/hold/channel block, up to three years. Reductions with future reservations outside that bound require manual reconciliation; they are not silently accepted. Increasing room count remains supported. Owners may lower *unsold* date-specific inventory through the existing audited calendar instead.
3. **Stale preview invalidation.** An inventory preview revision now includes room base/weekend prices, currency, room activation and publication status, property status, rate plans and date-scoped daily rates in addition to existing night overrides and committed stays. An intervening pricing or rate edit invalidates a preview and requires the owner/admin to preview again before `apply`.
4. **Conservative yield suggestions.** `OwnerYieldAdvisor` refuses changes on explicit manually overridden nights, closed arrival/departure dates, daily-rate stop-sells, maintenance/stop-sell nights, active holds/sold-out nights, disputed channel occupancy and inactive/unpublished room types. It checks `AzariAvailabilityEngine::remainingByDate` rather than inventing availability. Existing 5-booking/7-day sample threshold, bounds, preview/explicit acceptance, and audited reversible rate writes remain.
5. **Regression tests.** `BatchThreeInventoryIntegrityTest` covers multi-room confirmed bookings, active holds, future nightly allotments, safe capacity reductions, stale room/daily prices, owner manual overrides, closed nights and February 29.

No new migrations, booking status edits, ledger postings, external webhook endpoints, provider connectivity, new public rate changes or production deployment.

## Review checklist / local acceptance

```bash
composer install --no-interaction
php artisan migrate:fresh --env=testing
php artisan test tests/Feature/Bookings/BatchThreeInventoryIntegrityTest.php
php artisan test tests/Feature/Bookings
php artisan test tests/Feature/PropertyOwners
php artisan test
php artisan azari:release-gate --ci
npm ci && npm run build
```

Use a disposable database and fake provider/storage adapters. Repeat booking/hold vs property room-count reductions under actual **MySQL 8** using competing transactions and ensure no deadlock or oversell; SQLite alone cannot validate row-level locks. Test a zero-inventory blackout, two staff changing a calendar preview, owner vs administrator permissions, repeated undo, expired holds, future dates over 366 days, booked room quantity, leap day, DST transitions, property local midnights, JPY/NGN precision, scheduled/rate-plan edits and public quote/receipt invariants. Measure p95 query count and availability EXPLAIN on a representative large catalogue; run 320/768/1440 Playwright checks on both admin and owner calendar preview/undo. None is implied complete from lightweight CI.

**Known limits / operator acceptance:** The underlying booking engine remains the authority and may change due to a hold immediately after a *preview*; the transaction revalidates and rejects stale edits. Existing live owner/staff forms are retained. Suggested prices are only decision aids; no market pricing data is fabricated. Do not activate provider feeds or auto-accept rates without explicit operator approval. True MySQL contention, complete local Laravel and browser QA and prod-specific traffic checks remain mandatory. Other #74 business features (comparative occupancy/revenue backtesting with large unbiased samples and historical explainability) need further work before commercial automation is approved.

## Rollback

Revert this PR and clear application cache. No schema/data rollback, destructive delete or transformation of existing booking snapshots is needed. Rolling back removes only the *additional* safety guards, so a rollback should occur only after risk review. Do not shorten accepted booking obligations or unpublish a listing automatically to bypass an integrity rejection.
