# Resavar Phase Two: GitHub code versus operator acceptance

**GitHub epic:** #39 · **Child issues:** #54–#68 · **Last audited source head:** `e6a7da66cd0457e771e1b25a4da67d711ae1d7eb` (10 Oct 2026).

This document is a truthful implementation inventory, **not** evidence that every feature passed a current-head local test run or production acceptance. The project owner has reserved live deployment, payment provider checks, operational acceptance and production validation.

## Phase Two code implementation evidence

| Issue | Source implementation | Relevant recent merged PRs | Remaining validation |
| --- | --- | --- | --- |
| #54 Geo/map discovery | Viewport bounds, antimeridian, clustering, stable cursor, original filters and canonical list/map quote | #191, #218, #222 | Real dense-city/browser and MySQL query-plan profiling |
| #55 Flexible dates and price calendar | ±1/3/7 dates, typo/diacritic suggestions, month-long real stay quotes, property-local dates | #170, #188, #194, #229 | DST, 13-month boundaries and live quote/hold integration |
| #56 Room and rate comparison | Individual published room/rate, cancellation/payment policy snapshots, totals and selectable rate | #172, #190 | Reprice/checkout and long-rate-list E2E |
| #57 Checkout and payment | Canonical fee/tax display, independent payment retry, callback race checks, authorization | #176, #215, #220 | Provider sandbox, interrupted callbacks, real MySQL contention |
| #58 Verified listings | Verified claims/evidence, media review and gallery alt/rights holder/status across public surfaces | #180, #225, #231, #233 | Manual image-policy QA, responsive gallery and storage migration |
| #59 Guest reviews | Completed verified stay, duplicate lock, moderation, appeals, helpful-vote limits, PII checks, pseudonyms | #174, #192, #193, #216, #236 | Review abuse/security test execution |
| #60 Owner inventory | Atomic 367-day edit, preview/revision, committed/maintenance restrictions, undo, week/month board | #198, #214, #219, #223 | MySQL deadlock/lost-update/concurrent booking stress |
| #61 Owner staff permissions | Verified single-use invites, property-scoped ACL, instant revoke, owner-only role admin | #195, #227 | Execute all negative permission suites |
| #62 Operations | Task assignments, evidence/checklist, SQL triage, optimistic edits, private malware scan | #199, #223, #228 | Local evidence scanner and contention fixtures |
| #63 Booking messaging | Two-sided ACL, durable queue, dedupe, scanner/retention, unread, secure polling | #175, #186, #187, #220, #234, #235 | Mobile/browser polling and delayed outbox-worker QA |
| #64 Trips | Individual booking changes/cancellation/payment docs, secure share, multi-stay grouping | #221, #226 | Modification/refund state and sharing E2E |
| #65 Arrival/check-in | Identity/readiness safeguards, property-local checkpoint reminders, revised-date dedupe | #177, #181, #232 | DST property-local reminder and arrival approval E2E |
| #66 Support | Guest case/messaging, escalating SLA, lifecycle CAS, linked booking actions and authorized refund requests | #171, #184, #213, #220, #230 | Refund provider settlement and staffed SLA drills |
| #67 Mobile/offline | PWA install/navigation, AES-GCM itinerary, expiry and consent removal; **no browser push** | #153, #157, #167, #216 | Offline browser upgrade, low-end device, account removal QA |
| #68 Design/accessibility | Navy/white contrast, print components, keyboard focus, gallery, local Playwright fixtures | #152, #224 | Run 320/390/768/1280px browser, screen-reader and A4 PDF reviews |

The implementation includes changes from many earlier merged PRs not duplicated in this table. **Production smoke tests and provider settlements must never be inferred from merged PRs.**

## Required local code validation (not GitHub Actions)

Run in a full development checkout with PHP 8.4, required Laravel extensions, Composer dependencies, a disposable development database, Node and a locally installed Playwright runner:

```bash
composer install
npm ci
php artisan migrate --force
php artisan route:list
php artisan test --testsuite=Feature
php artisan test tests/Feature/PhaseTwo
npm run build
npx playwright test tests/browser --browser=chromium
```

Use a **disposable** database for destructive feature tests. Before running migrations or making financial mutations, take a production backup and do **not** aim tests at production. For MySQL-specific validation, use MySQL 8/InnoDB with strict grouping enabled and two or more independent connections: simultaneous holds, conflicting inventory previews, parallel checkout redirects/webhooks, duplicate refund requests, and support-ticket reopen/close races.

Inspect the generated local screenshot artifacts at 320, 390, 768, 1280px, and the A4 sample. Verify keyboard focus, source contrast, admin/owner/guest documents, logo-light on white, logo-dark on dark, toggle singularity and horizontal overflow. Run live gateway and external provider sandbox checks only when the operator authorizes them.

**Current environment limit:** The GitHub-connected editor cannot fetch the repository into its shell (GitHub DNS resolution blocked). The local PHP installation has PDO but lacks `dom`, `mbstring`, `pdo_sqlite` and `xmlwriter`. Accordingly the tests authored on these late Phase Two PRs **were not run locally here**. Historical passing counts in #39 refer to **earlier** source snapshots and do not validate today's combined head. CI was not used as a merge gate.

## Approved notification scope

Preserve guest/property in-app messages, in-app notifications and transactional email. Browser push, SMS and WhatsApp notifications were explicitly removed from Phase Two by the owner. They must not be reintroduced as a shortcut to satisfying former notification acceptance criteria.

## Release sign-off

- [ ] Current-head Laravel feature and property/inventory tests run and pass
- [ ] Local MySQL concurrency and payment provider sandbox cases pass
- [ ] Browser keyboard/responsive/contrast/print screenshots reviewed
- [ ] Moderated media, owner permissions, offline itinerary and guest sharing confirmed
- [ ] Operator performs production deployment, migrations, provider smoke checks and acceptance

A checked box here must correspond to executed evidence, not merely authored tests. The epic may be marked implementation-delivered without representing operator production acceptance; separately disclose all unexecuted checks in the release report.
