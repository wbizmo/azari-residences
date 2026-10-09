# Phase One completion: GitHub implementation versus operator acceptance

**Epic:** #38 | **Production/staging acceptance:** #123

Phase One has two separate gates. GitHub PRs and lightweight CI prove code was integrated and syntax/build checks passed; they do **not** demonstrate correct behavior under live contention or across real payment providers. Conversely, an operator test failure creates a new code issue and blocks final acceptance. Do not close the parent epic until both gates pass.

## GitHub and local development responsibility

| Issue | Required code/deliverables | GitHub status / outstanding code acceptance |
| --- | --- | --- |
| #42 inventory | One authoritative availability projection; all caller parity; 7/30/365-day profiling and migration map | Inventory engine shipped earlier. Caller-map completeness and local date-boundary parity tests require final signoff. |
| #43 booking holds | Idempotent state machine, atomic booking/hold consumption, indexed uniqueness, deadlock-safe lock order | Base protections shipped earlier. Local concurrent DB test harness and crash-recovery coverage must be verified. |
| #44 pricing/docs | Immutable quote snapshot, discount/tax rounding parity and receipt/PDF reconciliation | Snapshot safeguards shipped earlier. Admin receipt cannot select a newer failed payment (PR #122). Full surface-by-surface PDF parity remains for validation. |
| #45 booking amendments | Date/guest/room/extras consent, repricing, idempotency | **Issue closed**, implementation merged in #110–#117; subject to overall final runtime acceptance. |
| #46 cancellation | Frozen policy, deposits, no-shows, retried refunds, late/early-stay exceptions, maker/checker override | Refund entitlement and duplicate external settlement safety (PR #122) repaired. High-risk override approval and early-departure policy still require separate acceptance. |
| #47 provider refund | Actual adapter dispatch, callback validation, durable idempotency, verified settlement and exception queue | Provider foundations and fail-closed guards #112/#121, cancellation replay #122. Multi-provider daily reconciliation report and local duplicate callback regression coverage remain acceptance criteria. |
| #48 owner accounting | Owner credit/debit ledger, payout reservation, chargeback, statement/reconciliation | Existing ledger and dispute reversal. Manual uncertain payout recovery now requires independent reviewer (ops PR). Full daily owner settlement parity remains acceptance. |
| #49 calendars | Safe parser, single-flight sync, stale/fail-closed sellability, missing-event grace, restricted URLs | PR #122 improves truncation protection, single-flight sync and no-silent-inventory-release. Local concurrency and channel export interoperability remain to verify. |
| #50 queues | Durable worker, bounded retry/dead-letter, operator controls, heartbeat and incident response | Existing health/heartbeat; restricted operator failed-job replay and route audit (ops PR). Local simulated crash/restart test remains. |
| #51 security | Guest/owner/staff ownership, privileged workflows, file privacy, audit and threat model | Middleware/audit baseline earlier; added dangerous-route expectations (ops PR). Full privileged step-up/privacy signoff remains outstanding. |
| #52 capacity | Search/checkout reproducible dataset, query plans, percentile metrics and backpressure | Search benchmark now reports p50/p95/p99, query distribution and memory (ops PR). Production-sized dataset and checkout load test still needed. |
| #53 backups | Encrypted DB/private media, verified isolated restore, offsite, safe rollback and smoke | Encrypted archive and guarded restore command #118–#120. Private-media backup/offsite capability still requires implementation/verification. |

**Important:** "Requires final signoff" above is not proof of completion. When a capability has unfinished code scope, it remains a GitHub responsibility, even though #123 captures external acceptance separately.

## Local validation (must remain outside lightweight GitHub Actions)

Run on an isolated development database with a deliberately non-production APP_KEY and test payment credentials:

~~~bash
php artisan test --filter="CancellationQuotePolicyTest|CancellationSettlementFlowTest|ChannelImportReliabilityTest|AdminVerifiedReceiptTest"
php artisan test --filter="FailedJobRetryAuthorizationTest|UnknownPayoutOutcomeTest|SearchBenchmarkSmokeTest|PhaseOneBCAuthorizationOwnershipTest"
php artisan azari:authorization-audit --strict
php artisan resavar:benchmark-search --runs=50 --json
~~~

Then run the full project PHP feature suite and the existing local Playwright workflow before asserting no regressions. Run true MySQL parallel contention tests against a disposable target database, not Hostinger production. A passing SQLite suite cannot establish MySQL deadlock or oversell guarantees.

## Operator responsibility

Use [Phase One production/staging acceptance issue #123](https://github.com/wbizmo/azari-residences/issues/123). It tracks **only** environment-dependent exercises: MySQL parallel contention and realistic load, actual provider callbacks/refund settlement, persistent worker crash recovery, channel-provider failures, privileges and IDOR in staging, offsite DB/private-media restoration, deployment rollback and final smoke.

Attach redacted evidence (commands, expected/observed invariants, date, environment, measured RPO/RTO, signoff) against each checkbox. File regressions as new GitHub code issues.

## Privileged password step-up

For booking cancellations, no-show overrides, owner withdrawal requests/profile changes, staff payout dispatch/reconciliation, provider refund actions, dispute resolution and payment provider settings, the route now requires a password confirmation within the previous **15 minutes**. This is distinct from login/session authentication and role permission checks; users must still pass those checks. Missing/stale confirmation redirects to \`password.confirm\` (JSON requests receive HTTP 428). The action is **not automatically replayed**, preserving financial idempotency and preventing stale POST data.

User and staff payout forms display an actionable confirm-password link. After confirming, return to the original page and submit the action again. The auth controller and route use the existing Laravel password-confirmation flow; the middleware never receives or logs the password itself.

Local regression: \`php artisan test --filter='PhaseOnePrivilegedStepUpTest'\`. Staging must verify actual staff session expiry, revocation, permission downgrade and the password reset path without disclosing credentials.

## Release discipline

- GitHub Actions remains syntax, frontend build and lightweight release gates. **No PHPUnit or Playwright in Actions.**
- Merge reviewed, passing code PRs into main; **do not deploy or change the live database automatically**.
- Never mark a provider refund successful on local request acceptance alone, or a backup "restored" merely because its checksum passes.
- Only close #38 when repository functionality is code-complete **and** the production/staging evidence in #123 is complete.


## Local operational fault-injection and synthetic capacity fixtures

Both scripts require `APP_ENV=testing` and a separately named disposable database containing `test`, `sandbox` or `isolat` in the resolved DB name. They refuse `:memory:` or a production/default database. Do not run them against Resavar production.

1. **Queue crash/retry:** configure database queue and `DB_QUEUE_RETRY_AFTER=4`. Run `php tests/local/phase1-queue-worker-crash.php` (optionally `RESAVAR_TEST_PHP=/path/to/php`). This dispatches a **synthetic** idempotent database effect into `system_heartbeats`, starts a subprocess worker, kills only that worker after the DB commit but before the queue ACK, then starts a second worker after visibility expiration. The invariant is exactly one unique effect and zero pending copies of that test job. This does not call live payment providers or prove external exactly-once delivery.
2. **Synthetic capacity data:** with a freshly migrated disposable DB, set `RESAVAR_ALLOW_SYNTHETIC_SEED=I_ACCEPT_DISPOSABLE_DB`, `RESAVAR_SYNTH_LISTINGS` (1–100000), `RESAVAR_SYNTH_BOOKINGS` (0–1000000) and `RESAVAR_SYNTH_DAYS` (7, 30 or 365). Run `php tests/local/phase1-synthetic-capacity.php`. It inserts batch-capped nightly inventory and historical completed bookings, with no remote provider calls. The 100k × 365 case can exceed storage resources; operators must assess disk/DB capacity first. Then benchmark `php artisan resavar:benchmark-inventory TYPE_ID --runs=20 --json` and attach actual explain plans and measured p50/p95/p99 to #123.

Neither script is invoked by GitHub Actions. They are for local or isolated development testing only; external production/staging, provider settlement and offsite restore evidence remains with the operator under #123.


## Early departure after check-in

Staff with `bookings.edit` can record a confirmed, **occupied** early departure after at least one property-local completed night and before the originally booked check-out day. The endpoint additionally requires recent password confirmation and a documented reason. The booking transitions to `checked_out`, releasing future sellability under canonical inventory status rules, with a full status-history and audit event.

The **original check-out date, pricing snapshot, fee/tax and policy snapshot remain immutable** so that invoices and dispute records retain contractual truth. An informational unused-night subtotal from the booked line items is recorded, but it is **not** treated as automatic refund entitlement because promotions, fees, taxes, prepaid deposit policy and nonrefundable terms differ. An exception can be requested for an *audited* early departure only, reviewed by a different finance administrator, and reserved through `RefundService` without assuming provider settlement. Missing external refund evidence always fails closed pending reconciliation.

Local regression: `php artisan test --filter=BookingEarlyDepartureSafetyTest`. Operator acceptance: confirm property-local departure boundaries and manual policy review with real gateway/refund evidence; record under #123. No automatic partial-stay refund happens when policy is unknown.

## Independent checkout latency and explain plan driver

`tests/local/phase1-checkout-load.php` creates 4–24 distinct held, one-unit synthetic properties and uses separate forked MySQL processes with synchronized checkout starts. It measures each successful checkout's p50/p95/p99 elapsed time and DB query-count distribution, and captures raw MySQL EXPLAIN rows for date-range inventory and booking-index lookups. It refuses non-MySQL connections, non-testing app environments, real DB names, missing `pcntl`, or a non-array mail driver.

```bash
APP_ENV=testing DB_CONNECTION=mysql MAIL_MAILER=array RESAVAR_LOCAL_LOAD_WORKERS=12 \
  php tests/local/phase1-checkout-load.php
```

Run only in a disposable migrated database, with working Composer dev factories. Compare against `resavar:benchmark-inventory` and the one-room race test. This is synthetic **service-layer** checkout throughput, not end-to-end real payment or traffic performance, and no timings are claimed until measured. Full provider-connected and production-shaped targets remain in operator task #123.
