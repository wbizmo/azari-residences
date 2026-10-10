# Resavar Phase Four, Batch 1: supplier-gated travel requests (#85–#88)

## Implemented in this branch

* Four separately classified product catalogs: transfer, experience, car, flight.
* Contract/safety review and approved-region records for suppliers, with encrypted compliance references.
* Admin-only supplier, indicative quote and activity-slot setup, publish gate and request review API.
* Guest account offer discovery, short-lived nonfinancial travel enquiries, and private request timelines.
* Idempotency: scoped to guest + UUID key, with canonical payload hash; conflicting reuse rejected.
* Experience holds: DB row lock on the finite slot and a sum of active nonexpired requests, to prevent two writers taking the last available space on databases supporting row-level locks.
* Travel requests can optionally link to a guest-owned stay or itinerary. Cancelling a request NEVER mutates the existing stay or booking payment.
* Auditable status transitions; consent is recorded before staff may mark a supplier acknowledgement.
* Authenticated, encrypted, replay-protected partner webhook inbox with supplier+event deduplication, deferred processing and transactional in-app notices.
* Genuine certified adapter reprice required even for creating flight enquiries; stale, unavailable or changed fare fails closed without charges.
* RESAVAR_TRAVEL_REQUESTS_ENABLED=false by default. No supplier receives PII through this module.

## Deliberately NOT shipped as a live travel-booking product

* No partner API credentials, contracted API specifications, ticketing permission, regulatory signoff, supplier sandbox results, or independently reconciled travel payment/refund integration were provided.
* Supplier catalog offers are **indicative only**. Creating a request does not reserve a real vehicle, flight seat, or supplier-held excursion. Even a staff-recorded acknowledgement is **not confirmation**.
* No card charge, PNR, ticket number, driver dispatch, QR voucher, insurance promise, cancellation refund, or flight repricing is fabricated.
* Flight offer publishing is fail-closed unless an installed certified adapter is registered. The default adapter registry is empty.
* Experience slot locks reserve only Resavar's local provisional enquiry capacity, not supplier ticket issuance. Enquiries expire after 15 minutes.
* Renter ID, driver licence, passport and traveler documents are **not requested** by this UI or sent to suppliers. Secure processing must be added after provider/legal review.

## Admin interfaces (existing staff / step-up gates)

* GET /azaridevadmin/travel — list suppliers and outstanding requests (staff only).
* POST /azaridevadmin/travel/suppliers — create pending supplier.
* POST /azaridevadmin/travel/suppliers/{supplier}/approve — step-up + signed contract, licence, insurance and jurisdiction attestation.
* POST /azaridevadmin/travel/offers — create draft quote with itemized base, tax, fee, optional refundable deposit, cancellation/disclosure terms.
* POST /azaridevadmin/travel/offers/{offer}/publish — verified, unexpired supplier only. Flight requires certified integration.
* POST /azaridevadmin/travel/offers/{offer}/slots — finite experience slot creation.
* POST /azaridevadmin/travel/requests/{travelRequest}/review — step-up + supplier acknowledgement reference and guest data-share consent, or decline.

All amounts are **minor units** (pennies/cents/kobo). The first UI release is restricted to USD/EUR/GBP/NGN/CAD (two-decimal currencies). Deposits are disclosed separately. DO NOT flip the feature flag until local and provider validations are signed off.

## Remaining tasks before any #85–#88 issue can be considered fully complete

1. Obtain properly executed supplier contracts, verified licensing/insurance and country-specific consumer-policy approval.
2. Implement vetted transfer/car/experience provider adapters with availability/repricing, signed callbacks, retries, independent supplier order references and after-hours rescue/support.
3. Implement licensed air distribution (GDS/NDC/affiliate) offer refresh, fare lock/reprice, real ticket issuance and servicing. No scraping.
4. Integrate independently reconciled payments, immutable settlement entries, refund/no-show/chargeback workflows and release capacity based on real provider fulfillment.
5. Implement replay-safe ticket/voucher redemption only after real inventory, payment and supplier issuance are live.
6. Run local SQLite/MySQL feature suites and race/load tests plus authenticated, responsive Playwright flows. Verify migrations/rollback and staff step-up policies locally. No Laravel/Playwright GitHub Actions jobs.
7. Validate localized pickup times/DST, airline disruptions, accessibility, insurance and passenger protections in supplier sandbox. Run production smoke checks manually and monitor supplier timeouts/alerts.
8. Roll out per supplier and jurisdiction with the global flag off until each supplier can actually deliver. Ensure operator support and escalation playbooks.

## Migrate / rollback

Run php artisan migrate --force after backing up the database. To disable requests immediately, set
RESAVAR_TRAVEL_REQUESTS_ENABLED=false and run php artisan config:cache.
Rollback only after data retention/audit review: migration 2026_10_10_220000_create_phase_four_supplier_requests.php drops the new travel-only tables; it never rolls back accommodation or payment history.

## Provider webhook setup (disabled until signed partner integration)

Travel callbacks are separately disabled using RESAVAR_TRAVEL_WEBHOOKS_ENABLED=false. Enable only after a class implementing App\\Contracts\\Travel\\TravelSupplierAdapter is installed, tested and registered in config/travel.php, and supplier integration_key references it. The adapter must authenticate raw request bytes with the actual supplier's documented signature and timestamp/replay requirements. Supplier event envelope keys: event_id, event_type (acknowledged / declined / disrupted), travel_request_id and supplier_reference for acknowledgment. POST to /webhooks/travel/{supplier}; the HTTP response only acknowledges durable, encrypted receipt. Use resavar:process-travel-webhooks in a worker/scheduler to update request-only states and in-app notifications. A callback can never confirm ticket issuance, dispatch a driver, settle funds or cancel/refund a stay.
