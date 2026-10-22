# Batch 02 / 18 — Supply onboarding, verified claims and real sellability

**Issue:** [#295](https://github.com/wbizmo/azari-residences/issues/295), with active implementation gaps [#288](https://github.com/wbizmo/azari-residences/issues/288) and [#77](https://github.com/wbizmo/azari-residences/issues/77). No production deployment or provider activation is authorized.

## Existing capability retained

- [#254](https://github.com/wbizmo/azari-residences/issues/254): administrator-created draft properties, ownership agreements, multiple room/rate types.
- [#255](https://github.com/wbizmo/azari-residences/issues/255): individual/hotel operator registration, private proof-of-control uploads, partial drafts and resubmission.
- [#256](https://github.com/wbizmo/azari-residences/issues/256): staff review, media moderation, publication digest, reversible unpublishing.

These completed issues are not reopened and no second listing engine is created. The historical [#71](https://github.com/wbizmo/azari-residences/issues/71) has two halves: **owner registration and verified local property supply are in scope**, but PMS/OTA/channel account onboarding, credential mapping, synchronization and certification are **parked** under the explicit accommodation-only decision [#249](https://github.com/wbizmo/azari-residences/issues/249). Do not use this batch to enable supplier APIs or synchronize booking calendars.

## Code delivered

- Admin-only **Supply readiness** screen at `/azaridevadmin/supply-readiness` with selection of future arrival/departure dates (maximum 30 nights). Portfolio stage counts are labeled portfolio-wide; results are labeled *this page only* and paginated, so dashboard data does not imply all properties were scanned on each request.
- Each property is classified as **draft**, **reviewed**, **published but not date-bookable**, or **bookable for selected dates**. Staff can inspect the reasons and follow links to edit the property or configure rates/calendar. No guest, legal evidence, bank account data, or private photo paths are rendered.
- Check must have current staff publication review, published `available` status, genuine reviewed photographs stored on the public media disk, signed owner agreement and current manually verified private owner proof (for modern owner submissions), current rate + active same-property payment and cancellation policies, tax/currency and basic listing completeness. This is stricter than the `is_published` flag alone.
- Date eligibility uses `AzariAvailabilityEngine::assertRules`, `availableForProperty` and `AzariPricingEngine::quote` for **the same room/rate/occupancy**. Positive verified quote is shown in its native currency; it is not a hold or payment request and does not override bookings, maintenance, imported reservation blocks, closed-to-arrival or rate restrictions.
- Owner/owner-agreement changes revoke previously verified property claims with audit evidence. A published property immediately becomes private until a new owner agreement, photo rights and publication review are completed. The historical property ID, previous reservations and financial references are preserved.
- Read-only weekly `resavar:report-supply-readiness` command produces aggregate counts by status and destination for a future one-night example stay. The scheduler writes JSON into private storage `reports/accommodation-supply/YYYY-MM-DD.json`; no external service receives it and no database, inventory or ledger rows are modified.
- Existing guest public fact claims only appear when independently staff verified and unexpired. Mere presence of a photo or owner's assertion is not a verified badge.

## Validation and limits

```bash
php artisan test tests/Feature/PropertyOwners/SupplyReadinessBatchTwoTest.php
php artisan test tests/Feature/PropertyOwners
php artisan test tests/Feature/Bookings
php artisan test
php artisan route:cache
php artisan schedule:list
php artisan resavar:report-supply-readiness --check-in=2026-11-03 --json
php artisan azari:release-gate --ci
npm run build
```

Use a disposable DB with isolated fake public/private disks for automated tests. On a separate MySQL 8 instance, verify simultaneous owner reassignment and staff publishing, stale proof change, imported external blocked inventory, rate stop-sell, DST and local-date behavior, finite last-room inventory, an expired claim and previously paid booking preservation. Test 320/390/768/1440 responsive staff screens, keyboard date entry, pagination, guest cross-role denial and copy semantics. **GitHub CI remains lightweight; passing it does not assert Laravel/MySQL/Playwright acceptance.**

The date-scoped report is a **sample**, not a permanent promise: owners may change rates, sell the last room or unpublish immediately afterward. It is not a search result guarantee. Existing live public discovery still requires a separate cross-page eligibility sweep (homepage, destinations, availability, recommendations and saved items) with conservative feature rollout and production supply QA under #264/#265/#285/#286 and #288 before declaring the zero-unverified-public-listing requirement complete. Do not activate fake seed properties, synthetic reviews, or any cross-product provider integration to inflate inventory.

## Rollback and operator handoff

- This batch adds **no database migrations** and never rewrites historical financial documents, owner balances, old booking states or claim verification evidence.
- To revert, roll back this PR as one application release, clear Laravel config/route caches, and stop the weekly report scheduler entry. Keep any private aggregate reports as internal operating evidence subject to company retention policy.
- The owner must authorize production rollout, verify real property-control documents and photo rights, reconcile historical channel inventory, smoke-test public search with real dates and verify independent live payment settlement. No production deploy or payment credential change is part of code merge.
- The broader trust/abuse workflow in #77 still needs false-claim complaints, owner appeals, dispute routing, ongoing guest incident scoring and production QA; third-party mapping in #71 remains parked by product decision.
