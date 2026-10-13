# #253 Accommodation booking request and approval lifecycle

## Persistence, compatibility and activation

`config('reserva.bookings.require_approval')` defaults to **true** (`RESAVAR_BOOKINGS_REQUIRE_APPROVAL=true`) for new canonical checkout requests. Existing bookings, payments and provider references are not rewritten. A booked hold still goes through the existing `BookingCreationService` locking, user binding, idempotent hold token, rate selection and frozen quote checks. On submission, a `booking` row has canonical status `pending`, an `accommodation_booking_approvals` row has `submitted`, and a bounded `expires_at` review deadline (24 hours by default). The request temporarily reserves available inventory until the deadline, then the existing expiry command frees it. It is **not a confirmed stay**.

| Guest concept | Existing booking.status | approval.status | Can pay? |
| --- | --- | --- | --- |
| Request submitted | `pending` | `submitted` | No |
| Under review / alternative offered | `pending` | `under_review` | No |
| Approved, payment due | `pending_payment` | `approved` | Yes, subject to identity verification and checkout expiry |
| Confirmed | `confirmed` / appropriate existing paid state | `approved` | Per frozen payment policy |
| Declined | `cancelled` | `declined` | No |
| Deadline passed | `cancelled` | `expired` | No |
| Guest-cancelled | `cancelled` | original approval state | No |

The existing instant-booking behavior remains available under an explicit operator-approved `RESAVAR_BOOKINGS_REQUIRE_APPROVAL=false`; this is a backward-compatible rollback path for **new** bookings, not a way to bypass approval of already-submitted requests. The legacy direct `POST /bookings` route is retired with HTTP 410 while manual approval is active because it skips canonical locked holds.

## Review transactions and integrity

Owner-only review is scoped to `properties.owner_id`; staff review requires `bookings.edit` permission via the existing role system. Authorization is rechecked **inside** the review transaction, not merely in the Blade interface. A unique database constraint permits exactly one approval row per booking. Approvals acquire property and booking locks, check current published room/rate eligibility, room quantity and current authoritative quote before transitioning to `pending_payment`. The existing availability engine still manages concurrency and checkout holds. Repeat approval/review actions do not create duplicate transitions.

Declines require a human-readable reason and use the existing audited cancellation workflow, which releases availability but does **not** claim money has been refunded. Proposals contain alternative dates and a message only: they do not modify the stay, reserve new rooms or silently change prices. The guest must create and accept a **new** quote and request.

Payment initiation, deferred confirmation, administrative lifecycle transitions and booking PDF generation check approval on the server. Booking confirmation PDFs are unavailable until the confirmed/paid lifecycle, and an approval alone is not evidence of payment. Pesapal remains the existing payment processor and is not modified.

## Expiry and operational rollout

`azari:expire-unpaid-bookings` also expires submitted, under-review and approved-but-unpaid requests through the standard unpaid booking expiry path. The persisted booking becomes cancelled, the approval becomes expired, and the status history distinguishes preapproval expiry from postapproval payment expiry. The existing job must remain scheduled.

**Operator runbook:** Take a verified DB backup, deploy code/migration as one controlled release, keep the scheduler and Pesapal callbacks enabled, verify owner review permissions and booking requests across real accounts, and check that old paid/confirmed references remain unaffected. Roll back code and configuration only after reviewing any newly approved requests, captured payments and outstanding refund obligations; do not destructively roll back historical bookings. Live payment/provider acceptance remains operator-owned.

## Follow-up regression work (#271)

Run local PHP 8.4 Laravel / MySQL integration tests and genuine parallel owner approval/hold contention; check pending request TTL release and ability to rebook, voucher totals and deferred-payment policy, all payment callback variants, document/receipt gates, route cache, admin/owner IDOR, guest status timeline, owner/guest messaging and browser layouts at 320/390/768px. GitHub CI is lightweight syntax/build only; it is not proof of these tests. New approval notifications and in-app messaging are further covered in #262.
