# Phase Three: issues #70–#74, first implementation batch

This batch adds **limited, safe foundations across the next five issues**, not a claim that their full production acceptance checklists are finished. All changes are backward compatible with existing Phase Two booking/payment facts. Existing booked totals, refunds, settlement currency and already issued invoices are never rewritten by the new services. Production and certified OTA/PMS validation remain separate.

## #70: Authenticated inbox and transactional outbox

New tables `channel_webhook_inbox`, `channel_outbox` and an encrypted `channel_connections.webhook_secret` provide event IDs scoped to connections, replay-window signatures, encrypted raw payload storage, per-connection uniqueness, payload-conflict rejection and rollback-safe publication intents. The signed `POST /webhooks/channels/{connection}` intake is only enabled for the explicitly flagged **resavar_sandbox** connection when `RESAVAR_SANDBOX_WEBHOOKS=true` and must remain **false in production**. Requests are HMAC-SHA256 authenticated over the timestamp, event identifier and exact raw bytes; timestamp window ±300 seconds; 64 KiB payload cap; throttle 30/minute. Duplicate authenticated events acknowledge without duplicate effects, and 50 direct service retries are tested. The inbox is **capture-only**: it makes no new booking or channel reservation state changes. The outbox is **queue-only**: it does not transmit to any outside provider until contract-approved publisher, dead-letter and reconciliation logic is implemented. Do not expose a sandbox signing secret in admin views or logs.

**Still required:** provider-certified signatures/OAuth and secret rotation, chronological reservation reconciliation, durable poison-event/dead-letter workers, outbound publish acknowledgement and conflict triage dashboard, MySQL multi-worker race tests, approved partner certification. Issue remains open.

## #71: Mapping and health groundwork

Owner and admin iCal forms now expose accommodation type mapping. Existing property/type ownership guards remain authoritative, and a read-only owner-owned `channels/{connection}/health` endpoint returns explicit currency mismatches, stale sync, missing room mapping and future external reservation counts. `certified_for_ari_publication` is always false for iCal. Disconnect remains fail-closed. No cross-owner access.

**Still required:** comprehensive provider mapping wizard, bulk dry-run differences, owner certification checklist, contractual API rate-plan mapping, credential management and safe reconnect/reconciliation workflow. Issue remains open.

## #72: Auditable FX reference and immutable quote locks

The `fx_reference_rates` and `fx_quote_locks` tables store exact source/target minor units, a fixed eight-decimal rate and a short-lived lock. `FxQuoteService` rejects stale/future/unsupported rates and integer overflow instead of using floating-point currency math. It applies half-up minor-unit rounding and has explicit zero-/three-decimal currency support, but only configured supported currencies can be quote-locked.

**There is intentionally no automatic payment currency conversion:** a preferred browsing currency is not evidence of a supported gateway charge currency. FX sources must be finance-verified, and checkout payment, refund, reconciliation, processor and owner ledger integration is not yet performed. Never advertise a live FX charge until these are complete. Issue remains open.

## #73: Initial locale selection, English fallback and reviewed common navigation

Adds French and Spanish core navigation translation keys, a CSRF-protected locale selector, session persistence and saved user preference, and an origin-bounded return URL. Untranslated account/legal/payment documents continue to use English through Laravel fallback. The language selector has a no-JavaScript submit path. This is **not** complete multilingual site coverage or reviewed jurisdiction-specific legal text.

**Still required:** full Blade/email/document strings, address/timezone formats and pluralization audit, translated policies with human/legal review, hreflang/canonical locale SEO, end-to-end authenticated messaging and checkout locale propagation. Issue remains open.

## #74: Opt-in, read-only bounded pricing advice

The owner-owned property/room yield-preview endpoint computes booked room nights from confirmed stays for at most 31 future dates in a bounded query, requires a minimum sample, refuses oversold cases and returns evidence plus a floor/ceiling-clamped suggestion. It is **read-only**: prices, invoice totals and connected channel ARI are not changed, and an owner can choose to use existing audited preview/apply inventory controls manually.

**Still required:** historical booking snapshots, lead-time benchmarks, configurable property-specific algorithms, human overrides, backtesting, legal/contract rate floors, explicit owner opt-in automated rate rules, versioned change audit and certified partner rate delivery. Issue remains open.

## Local validation and rollout

- Local isolated Phase Three feature suite and full Laravel regressions with PHP 8.4/SQLite, 50-event dedupe retries, negative owner boundaries and no live external calls.
- Keep full Laravel/Playwright/MySQL tests **out of GitHub Actions**. GitHub CI only runs lightweight syntax/build gates.
- Apply migrations to a **disposable staging** database first. `channel_connections.webhook_secret` remains null for all existing connections; legacy iCal connections are unaffected.
- Do not enable the sandbox webhook flag in production or attempt to issue an external FX rate in the absence of a verified source.
- Run MySQL 8 multi-connection contention, authenticated visual Playwright, real provider sandbox, currency reconciliation and multilingual checkout/document reviews before declaring any of the five issues complete.
