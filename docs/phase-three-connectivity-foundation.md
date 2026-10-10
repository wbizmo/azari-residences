# Resavar Phase Three: OTA connectivity foundation (#69, #70, #71)

This first code delivery is a **fail-closed provider-contract and disconnect safety foundation**, not an official Booking.com, Expedia, Airbnb or PMS integration. No entitlement, sandbox credentials or certification for those providers has been supplied; every such capability remains disabled by default.

## Contracts and adapter availability

`ChannelCapabilityRegistry` exposes a versioned manifest and supported capability flags. iCal remains the only enabled adapter. iCal imports calendar occupancy and exports a calendar feed; it cannot push rates/availability, cancel reservations with partners, send messages or receive signed API webhooks. `ChannelAdapterManager` rejects unapproved providers rather than silently falling back to iCal.

## Safe disconnect and reconciliation boundary

Owner and administrative *remove* actions now suspend/retire the connection **without deleting** external reservation history. Signed iCal import URLs are cleared and public export tokens rotate; the old export feed stops working. Connections with future active external stays enter `disconnected_pending_reconciliation` and the canonical availability engine and marketplace date filtering block affected accommodation types, even if the provider import is paused. The existing import worker refuses to apply any snapshots after disconnect, including responses returned by an in-flight request.

This is deliberately conservative. There is **no automatic release** after disconnect. Build the privileged reconciliation workflow only after the supplier truth, conflicting internal stays, retained external booking references and property ownership have been verified. Never manually clear the pending status without this procedure.

## Validation and release notes

- Unit and feature regression: capability flag truthfulness, idempotent disconnect, token revocation, historical reservation retention, booking-availability fail-closed, unauthorized cross-owner operations, stale import refusal, public quote filtering and reactivation refusal.
- Run full local PHP/Laravel tests, not in GitHub Actions; use disposable SQLite and separate MySQL 8/InnoDB concurrency tests before production cutover.
- Existing iCal connections remain active. Only a deliberate delete/pause action now performs reversible-safe retention instead of hard deletion. No schema migration is required.
- No API credentials were introduced in this batch. Provider-specific encrypted credential vault, authenticated webhook inbox/outbox, event ordering, room/rate mapping and approved sandbox certification remain future #69–#71 work.
- Live provider / production verification is the operator's responsibility.
