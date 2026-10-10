# Resavar UI acceptance gallery

This is a **local-only** gallery and visual fixture, not a production route, customer-facing component or deployment artifact.

Open `docs/ui/resavar-component-gallery.html` in a browser. It imports the real Resavar design token, shared component and toggle styles. The fixture covers the dark/light button contract, keyboard focus, a dark navy surface, text fields, a single native checkbox (without duplicate pseudo-switches), a horizontally scrollable owner calendar and an A4 print example.

## Shared component contract

| Surface | Required behavior | Fixture |
|---|---|---|
| Primary action | Navy `#052058` fill and white text; hover retains readable text | Check availability |
| Secondary action | White fill, navy border and navy text | Change dates |
| Inverse panel | White text on navy, with white focus outline | Continue booking |
| Controls | Visible input labels and solid 3px keyboard outline | Destination, Guests |
| Toggles | One accessible input per visual control, navy/white state contrast | Allow rate, Stop sell |
| Status/alerts | Text label plus semantic status, never color alone | Price changed notice |
| Owner calendar | No document-level horizontal overflow at 320px | Calendar fixture |
| Receipts / documents | A4 print uses real text, clear line items, explicit totals | Sample receipt |

The bright blue and orange brand tokens are **not** used for small body text on white. Use the dedicated `--reserva-blue-text` and `--reserva-orange-text` where colored text is necessary.

## Local visual evidence

Run the gallery browser specs on a machine with Playwright installed:

```bash
npx playwright test tests/browser/resavar-component-gallery.spec.js --browser=chromium
```

The test records `playwright-artifacts/resavar-gallery-320.png`, `resavar-gallery-390.png`, `resavar-gallery-768.png`, `resavar-gallery-1280.png` and the A4 PDF. Those screenshots are generated **only when tests run** and must be reviewed before accepting layout changes.

The PHP test `tests/Feature/PhaseTwo/ResavarDesignTokenContrastTest.php` verifies the navy/white, readable colored text and focus tokens against WCAG contrast thresholds. The browser suite verifies viewport overflow, visible focus and print artifacts; it does not replace manual screen-reader testing of every authenticated admin/guest page.

## Release checklist

- Verify property, guest, owner, admin, email and PDF surfaces at 320, 390, 768 and 1280px, including 200% zoom.
- Confirm no duplicate switch tracks, squeezed withdrawal inputs, blue-on-blue icon visibility or dark-logo-on-white mismatches.
- Use local Playwright visual comparison for search, checkout, booking modification, owner calendar, guest inbox and print views.
- Check PDFs separately for A4 paper clipping, proper tax/payment disclosure and actual payment-state correctness.
- Do not run full Playwright or Laravel suites in GitHub Actions. Production smoke/acceptance remains with the site owner.

This document defines the expected contract and local fixtures; it is not a claim that those visual tests were executed in the GitHub-connected editing environment.
