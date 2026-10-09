# Phase One inventory contract and caller map

The day-level source of truth is \`AzariAvailabilityEngine\`. Dates are property-local booking dates; **check-in inclusive, check-out exclusive**. Quantity is **accommodation-type units**, never a property-level Boolean when accommodation types exist. Restrictions (advance notice, minimum stay, stop-sell, closed-to-arrival/departure, adult/child capacities) are evaluated independently of committed quantity.

| Path or component | Source | Important invariant |
| --- | --- | --- |
| Public availability/results | \`MarketplaceSearchService\` → \`AzariAvailabilityEngine\` | SQL prefilter + canonical quantity; no property-wide fallback with typed inventory |
| Public quote and hold | \`AzariAvailabilityController\` → \`AzariAvailabilityEngine\`, \`AzariPricingEngine\` | Recheck rules and quantity, lock property/type/inventory date rows |
| Checkout booking | \`BookingCreationService\` | Held quote compared with current canonical repricing before commit |
| Owner date editor | \`OwnerCommercialInventoryController\` → \`InventoryBulkUpdateService\` | Cannot reduce sellable units below existing commitments |
| Admin calendar | \`AzariAvailabilityEngine::calendar\` | Exactly the same available quantities by day; missing/foreign/disabled requested room type is **unavailable**, never legacy available |
| Channel reservations | \`ChannelAvailabilityService\` → \`AzariAvailabilityEngine::committedQuantityByDate\` | Active remote events and stale fail-closed feeds reduce sellable units |
| Legacy public compatibility | \`AvailabilityService::isAvailable\`, \`::quote\` | Type-aware quantity; quote delegated to \`AzariPricingEngine\`, identical currency/tax/fees to checkout |
| Legacy properties without accommodation types | \`AzariAvailabilityEngine::legacyAvailable\` | Compatibility only if property has **zero** accommodation-type records; inactive types must never expose legacy availability |

## Regression matrix

- Foreign/missing/inactive requested room type: unavailable in both direct booking and calendar.
- One-unit confirmed booking spanning 2028-02-28 to 2028-03-01: Feb 28 and leap day Feb 29 blocked, Mar 1 free; adjacent check-out is exclusive.
- Holds, maintenance, channel reservations and stop-sell account for per-night capacity. Quantity cannot be negative or silently fall back to a property-wide check.
- Calendar window: 7, 30 and 365 days; direct read rejects windows beyond 366 days, while booking-rule limits remain separately enforced.
- Legacy quote must have the same \`nightly_breakdown\`, subtotal, discounts, fees, tax, currency and final total as an equivalent \`AzariPricingEngine::quote\`.

## Local testing and performance

Run \`php artisan test --filter='PhaseOneInventorySafetyTest|PhaseOneQuoteIntegrityTest'\` in a disposable environment, then full local Laravel and MySQL contention suites. Benchmark 7/30/365-day calendar reads with actual query-count and p50/p95/p99 samples. The repo supplies a search benchmark, but **no timings or capacity baselines are asserted here without running it**.

**Known complexity gate:** search's date-level SQL availability prefilters scale with nights. The large-horizon MySQL query-plan and latency budget must be checked before closing performance issue #52. No inference from a passing GitHub Actions syntax check is valid.


## MySQL contention fixture (local only)

`tests/local/phase1-mysql-contention.php` forks six **independent MySQL connections** and uses synchronized starts. The fixture requires `APP_ENV=testing`, PHP `pcntl`, a migrated disposable `mysql`/`mariadb` database whose name contains `test`, `sandbox` or `isolat`, and Composer development dependencies for factories. It refuses other environments and never contacts payment gateways:

```bash
APP_ENV=testing DB_CONNECTION=mysql php tests/local/phase1-mysql-contention.php
```

The script verifies exactly one hold for a one-unit room across six simultaneous customers, then independently races six identical checkout requests against the same held token. Expected: six identical requests return the same **one booking**, the hold is consumed, and no extra booking is inserted. Any unknown worker error fails the gate. Use a disposable database with no live user data, isolate network routes, and keep the MySQL server's deadlock/lock-timeout logs for evidence.

The checkout transaction now locks **property → hold → accommodation type → inventory dates**, matching the property's serialization boundary in hold acquisition. This prevents a prior **hold → property** lock-order inversion with expired-hold cleanup. The fixture does not replace the broader financial, provider, deadlock and actual production-shaped acceptance in #123.
