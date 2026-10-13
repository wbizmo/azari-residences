# Accommodation-only cutover: historical data preservation and rollback (#251)

## Goal

Retire new non-accommodation inventory, suppliers, partner API channels, multi-product assemblies and dining bookings without deleting historical records or accidentally touching confirmed Resavar accommodation reservations, booking payments or owner balances.

This note maps the code-level boundaries observed in October 2026. **It does not authorize a production deployment or destructive migration.**

## Historical database records (retain, do not drop)

| Record | Existing database objects | Required treatment |
| --- | --- | --- |
| Travel supplier & product catalogue | `travel_suppliers`, `travel_offers`, `travel_experience_slots` | Retain original partner, offer, terms, prices, licence/review provenance. Never republish or schedule requests. |
| Travel requests & events | `travel_requests`, `travel_request_events`, `travel_fulfillments`, vouchers and refund receipts | Keep original status, request ID, quote, supplier acknowledgment, user ownership, event timeline, historic vouchers and financial references. Guest/account archive is read-only and tenant-scoped. |
| Multi-product itineraries | `trip_itineraries`, `trip_assemblies`, assembly steps and compensations | Do not replay old fulfillment/compensation jobs. Keep original step evidence and any independent refund ledger entries. Display non-sensitive summary in archive. |
| Dining requests | `dining_partners`, `dining_requests`, supplier confirmation fields | Keep historical status and verification/audit reference. No new reservations or arrival reminders. |
| External calendar/channel connections | `channel_connections`, `channel_reservations`, channel event inbox and reconciliation tables | Preserve origin, seen/changed timestamps and *existing* inventory blocking from imported reservations until each booking is independently reconciled. Do not sync new external changes. |
| Accommodation inventory / bookings | `properties`, `accommodation_types`, calendars, bookings and room/unit allocations | Core Resavar platform; keep fully operational, with conflict and concurrency safeguards. |
| Payment evidence | `payments`, refund records, owner ledger, withdrawal and commission tables | Keep unchanged. A legacy travel *quote* is never proof that payment succeeded. All financial corrections must use normal ledger and reconciliation controls. |

## Runtime cutover guards

- #250 turns on `RESAVAR_ACCOMMODATION_ONLY=true` by default and binds a **410 retirement guard** to non-accommodation routes, including all mutation endpoints and historic third-party supplier webhooks.
- Existing public legacy travel marketing URLs redirect to accommodation search with permanent redirects. Existing route names are preserved for compatibility with caches/bookmarks where applicable.
- `azari:sync-channels`, `resavar:process-travel-webhooks`, and dining reminders are not scheduled while accommodation-only is enabled; manually invoked commands also guard the mode. Previously queued `SyncChannelConnection` jobs no-op before any external provider I/O.
- Existing Pesapal and accommodation payment callbacks, quote/availability, booking reminders and guest communications must **not** be put behind the travel retirement guard.
- #251 adds separate **GET-only** read-only guest and admin archives. Guest records are queried by authenticated `user_id`; archive views only render sanitized status/title/date references, never private preferences, encrypted compliance documents or gateway secrets. Admin route is administrator-only.
- The archive is not a new booking engine: it performs no supplier API call, status change, payout, refund or confirmation action. The original encrypted model fields and historical financial tables remain intact.

## Production acceptance / rollback

1. Operator takes and verifies a restorable database/media backup; record running migration set, queue depth, schedule and the deployment SHA.
2. Inspect **pending channel reservations**, supplier inbox records, unsettled refunds, trip compensations and owner ledger balances before cutover; reconcile unresolved obligations manually.
3. Drain/pause legacy provider jobs and retries before enabling new code; **do not delete** pending events or historical cancellation / refund evidence. Verify Pesapal and accommodation scheduler remain live.
4. Confirm guest/account archive tenant isolation and admin audit permissions; ensure no sensitive bank details, secrets, travel documents or preferences appear in HTML/logs.
5. Confirm accommodation inventory, search, payment callbacks, receipts, support, owner creation and admin property creation still work. Verify sitemap and retired 301/410 outcomes.
6. Roll back **the application deployment as one unit** if needed. Because #251 introduces no migrations, do **not** run destructive `migrate:rollback` on existing historical tables.
7. A temporary `RESAVAR_ACCOMMODATION_ONLY=false` toggle exposes legacy route behavior and must **not** be used as the normal recovery path without review, provider authorization and reconciliation. Prefer rolling back only the application release after recording outstanding supplier financial obligations.
8. Owner remains responsible for live payment/provider verification and deployment acceptance. Local PHP/DB/browser regression proof and lightweight CI are separate gates.

## Post-cutover follow-up

- Confirm long-term retention and data protection rules with counsel/finance. Do not erase financial or dispute evidence through routine cleanup.
- If deletion/expiration policy is mandated, create a separate reviewed schema/data-migration PR with immutable export proofs, retention windows, tenant access rules and rollback fixtures.
- Archive UI is deliberately modest; redesign visual consistency under #263–#274, not by duplicating the old flight or dining journey.
