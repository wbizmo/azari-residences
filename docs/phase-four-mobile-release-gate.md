# Resavar Phase Four: Native companion release decision

Issue #90 explicitly makes native implementation conditional on measured PWA demand.

## Decision as of this delivery

**NO-GO until the gate is backed by real verified cohorts.** Do not create an
unmaintained second financial booking stack. The PWA is already implemented.
The decision endpoint \`azari.admin.travel.mobile-gate\` reports installs,
return visits, and attributable paid bookings separately, in a rolling 90-day
window. Missing telemetry = unmet gate, not positive evidence.

## Required evidence

1. Instrument unique opt-in install, authenticated returning PWA sessions, and
   independently paid reservations attributable to PWA. Count distinct users
   and bookings, not clicks. Secure collection and obtain privacy review.
2. Collect at least 90 days and compare against documented minimum launch
   economics. Thresholds default 250 installs / 75 returning / 30 paid bookings;
   these are **planning assumptions**, not measured business goals.
3. Product lead approves separately using \`RESAVAR_NATIVE_PRODUCT_APPROVED\`.
4. Before native delivery: implement OAuth2 authorization-code + PKCE using a
   supported hardened provider, registered redirect URI allowlist, ephemeral
   session tokens, revocation and remote device logout, versioned API contracts,
   idempotency, app-store disclosures, accessibility/performance, and testing.
5. Native clients must call existing Laravel reservation/payment handlers:
   never cache PII or run an independent payment calculation.

No native build, push registration or mobile booking/payment API is activated
by this PR. The completed scope is the **evaluate** prerequisite, not
unconditionally implementing an unapproved application.
