# Phase Three last five: #80–#84 implementation and release gates

This PR adds safe, test-backed slices for the final five Phase Three tickets. **It does not certify these five issues complete.** Keep all five issues open until the outstanding contract and operational requirements below pass. Production checks and deployment remain with the operator.

## #80 Scoped affiliate API

- `GET /api/partners/v1/stays` requires `Authorization: Bearer <issued-secret>` with `stays.read` scope. Only public, availability-qualified properties and their canonical quotes are returned. Response has no customer or owner PII.
- `POST /api/partners/v1/intents` requires `intents.create` and a key of 16–100 characters. Partner-scoped idempotency, payload fingerprints, a DB row lock, expiry, and current bookability are enforced. It returns a **redirect-only, unconfirmed intent** and a link back to the ordinary guest checkout. It does **not** book, charge, or attribute commission. Price and inventory must be quoted again in the normal flow.
- `partner_clients.key_hash` is SHA-256 of a randomly generated token. Never store or log the plaintext token. The database holds no enabled partner by default. Staff must verify terms, create/rotate the key securely and grant only contractual scopes. Apply network and edge quotas in addition to the application per-IP throttle.
- Pending: partner booking authority, contracted commission attribution/reversal, webhook signature transport, production rate/permission contracts, usage dashboard, load tests, certified onboarding.

## #81 Consent-gated campaigns

- Staff routes under `/azaridevadmin/marketing-campaigns` create draft campaigns, allow explicitly authorized approval, enumerate non-PII delivery metrics and pause campaigns. Approval requires step-up. Drafts do not send.
- `LifecycleCampaignService::reserve()` strictly requires verified email, profile marketing consent, communication-preference marketing consent, approved campaign window, and a seven-day global cap. An idempotent unique key prevents duplicate reservations. User opt-out suppresses all unsent reservations.
- **Reservations are not dispatched to the email provider.** Delivery worker, robust unsubscribe mechanism, per-template preview, saved-search price alerts and bounce handling must be finished before turning any campaign on for customers. No SMS, WhatsApp or browser push is reintroduced.

## #82 Reproducible analytics

- Versioned funnel report uses idempotent AnalyticsEvent counters; verified payments determine paid-booking conversions instead of trusting client-side analytics. Experiment variation is deterministic keyed HMAC without sensitive attributes, and cannot manipulate price by itself. Owner counts are isolated by owner and currency.
- Pending: complete event instrumentation, session conversion denominator semantics, cohort retention, owner ADR/RevPAR, daily aggregates, trustworthy minimum-size peer benchmarks, bounded exports and experiment results.

## #83 Immutable agreement versioning and statement export

- Future-effective contracts are created only through staff + step-up approval; monotonically increasing versions reject retroactive changes. **These records do not retroactively recalculate the existing 100% owner-earning ledger.** No new commission is charged by this patch.
- `/account/owner-statement.csv?currency=USD&from=YYYY-MM-DD&to=YYYY-MM-DD` exports only authenticated owner's posted ledger rows, in UTC, with spreadsheet-formula injection protection; streaming avoids loading an entire year's ledger in memory. Totals are separated by currency and expressly **not represented as provider-reconciled**.
- Pending: contract-driven settlement posting after legal/owner approvals, tax-specific invoice/credit note rules, immutable allocation per successful provider payment, external settlement-file reconciliation, multi-currency adjustments.

## #84 Privacy-aware recommendations

- `/account/recommendations` ranks only canonical, currently bookable search results, using own recent views and saved amenities; the default explanation is availability. Results use the same quote currency and amount as the search engine, with re-quote required at checkout.
- `/account/recommendations/preferences` opt-out deletes history; `/account/recommendations/recent` clears history. Property pages stop collecting browsing history while opted out. No cross-user signals or sensitive inference. Error fallback leaves ordinary exploration available.
- Suggested stays are accessible from saved searches and work without JavaScript.
- Pending: expanded nearby alternatives, explicit behavioral consent policy by market, verified-value labels, A/B exposure instrumentation, mobile browser visual checks and at-scale relevance benchmarks.

## Validation and constraints

- Local PHP 8.4 / SQLite Laravel tests including access denial, deterministic retries, opt-out and ledger isolation. Keep GitHub Actions lightweight, no Laravel/Playwright test suite added to CI.
- Contract-only partner API, zero enabled partners, zero live/automatic marketing dispatch, and no new money mutation are deliberate production-safe defaults.
- Security cutover: provision secrets out of band; review sensitive logs; require MySQL 8/InnoDB race and real provider sandbox tests before externally enabling partner integrations or monetary contracts.
- Migrations reversible after normal dependency sequencing. Roll back before activating downstream records; never drop partner or financial history in production without approved retention and export plans.
