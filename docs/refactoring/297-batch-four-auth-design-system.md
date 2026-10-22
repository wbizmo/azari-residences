# RESAVAR Batch 04/18 — Shared brand, UI controls and authentication foundation

Tracking: [#297](https://github.com/wbizmo/azari-residences/issues/297), issues [#263](https://github.com/wbizmo/azari-residences/issues/263) and [#274](https://github.com/wbizmo/azari-residences/issues/274). This is an incremental integration of the checked-in accommodation-first UI reference, **not** a parallel mock application.

## Delivered code

- Consolidated standalone guest login, registration, administrator sign-in and guest password recovery, reset, verification and password confirmation into the **same** responsive photo-and-form language. The existing checked-in `azari-guest-auth-suite.png` and `azari-admin-reception.png` are background assets, not generated placeholders.
- Use the real `public/images/logo-dark.png` for dark/image regions and `public/images/logo-light.png` for light site navigation with the current dynamic header logo switch; existing `x-brand-logo` provides authenticated page logos. Do not substitute a generated lettermark.
- Removed legacy inline design rules from guest login/register and admin login. One `reserva/auth-foundation.css` stylesheet controls widths, labels, panel spacing, responsive typography, 320px mobile stacking, proper long-form desktop vertical scrolling, focus, password reveal placement, validation feedback and reduced-motion. User/account access logic and actual Laravel endpoints are unchanged.
- Recovery and email verification now reuse a shared `x-azari-auth-shell` that has a real logo, safe account status/errors and usable password fields. No account passwords, recovery tokens or private metadata are displayed in decorative media.
- `auth/customer-register`, if rendered by an older workflow, now **includes the same secured registration view** with a fresh form token. It no longer offers a competing page missing the mandatory registration anti-abuse token.
- `reserva/ui-foundation.css` adds a restrained white/navy shared semantic layer for button variants, inputs, status badges, cards, empty states and focus/disabled behavior; `x-ui.*` Blade components opt in while preserving existing class names and attributes for backwards compatibility.
- Existing `reserva/tokens.css` remains the sole existing navy/white semantic color source. New auth and control dimensions refer to existing palette tokens instead of restyling every legacy module at once. No changes to Laravel policies, access guards, staff authentication middleware, booking/payment routes, owner ledgers or data schema.
- `tests/Feature/Architecture/BatchFourAuthDesignRegressionTest.php` covers logged-out guest login/register/recovery, staff page shell, required honeypots/form tokens, original real assets and accessible component markup.

## Maintained server contract

| Form | Existing action | Fields and protections maintained |
| --- | --- | --- |
| Guest login | `route('login')` | email, password, remember, CSRF, honeypots |
| Guest register | `route('register')` | name, email, password, password confirmation, `_auth_form_token`, honeypots and CSRF |
| Staff login | `route('azari.admin.login.store')` | login/username-or-email, password, remember, CSRF, honeypots and staff-only guard |
| Forgot password | `route('password.email')` | email, `_auth_form_token`, honeypots and CSRF |
| Reset password | `route('password.store')` | route-issued reset token, email, two password fields and CSRF |
| Email verification/confirm | pre-existing `verification.send`, `logout`, `password.confirm` | existing CSRF/session and role restrictions |

## Local acceptance and production gate

```bash
composer install --no-interaction
npm ci && npm run build
php artisan view:cache
php artisan test tests/Feature/Architecture/BatchFourAuthDesignRegressionTest.php
php artisan test tests/Feature/Auth
php artisan test tests/Feature/Architecture/ReservaDesignSystemRegressionTest.php
php artisan test tests/Feature/Architecture/CanonicalAdminAndBrandRegressionTest.php
php artisan test
php artisan azari:release-gate --ci
```

For the actual browser acceptance, run Playwright at 320, 390, 768, 1024, 1440 and 200% zoom for guest sign-in, registration (all fields present, scroll to submit, no horizontal overflow), reset request, token-based reset, confirmation, email verification, staff login/error. Cover all keyboard focus/tabs, password visibility button with accessible name/pressed state, 4.5:1 normal text / 3:1 UI borders, dark media contrast, focus visibility, reduced-motion, Safari/iOS keyboard/viewport behavior, scroll and login-state redirects. Verify security enforcement with real Laravel feature tests, including CSRF, idle token age, registration honeypot, password reset token expiry, staff/guest separation and preserved lockout limits. CI is lightweight; its green status **does not** constitute these tests.

**Scope boundaries:** Issue #274's full rollout to guest/owner/admin workspaces, checkout, PDFs and all public merchandising pages is intentionally **not** declared complete by auth foundation changes. Existing page migration #264–#269 and full visual WCAG QA #273 remain separate acceptance tracks. Issue #263 requires subsequent component-by-component rollout and manual visual review of old overrides. Historic travel UI is still parked under #249.

## Rollback

Revert the single PR and recompile Vite assets; clear Laravel view caches. No database migration, destructive data removal, payment integration or credential rotation is part of this change. Do not deploy to production without operator review and smoke test of all authentication flows and responsive layouts.
