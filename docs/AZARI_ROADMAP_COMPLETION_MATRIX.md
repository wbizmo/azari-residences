# Azari Roadmap Completion Matrix

This file records the repository-specific closure work performed after the malformed Sprint 13–16 payload.

## Governing rule

Sprints 1–6 foundations remain preserved. Existing Sprints 7–12 modules are extended only where a verified defect or missing integration requires it.

## Sprint 13

- Customer and administrator support queues
- Booking-linked categories
- Search, filters, assignment, priority, escalation, resolution and reopening
- Guest-visible replies separated from internal notes
- Private attachment storage with authenticated, authorized, temporary signed downloads
- Dynamic customer contact settings
- Configured destination notification plus customer confirmation
- Permission middleware and pagination

## Sprint 14

- Verified-stay reviews and moderation
- Promotions, featured offers, local guide, testimonials, seasonal messages, booking notices and homepage content records
- Customer communication CMS settings
- Newsletter remains removed

## Sprint 15

- Nineteen canonical report families
- Date, status, provider, currency and booking filters where applicable
- CSV, SpreadsheetML Excel-compatible XLS, A4 PDF and print output
- Audit logs, provider status, communication status, failed jobs, scheduled-task history, masked application-log summary and backup history
- Permission middleware and ten-record pagination

## Sprint 16

- Correlation IDs and hardened response headers
- Friendly production exception responses
- Private-file authorization and upload validation
- Route throttling and login-throttling preservation
- Production audit command
- Backup creation, checksum verification and history
- Daily scheduler hooks
- Deployment, queue, scheduler, mail, Twilio, provider, backup and recovery instructions
- Regression, PHP syntax, route, migration, Blade compilation, feature-test and Vite build gates

## Environment-dependent completion

Live payment, mail and Twilio delivery still require valid production credentials. The production audit reports these deployment conditions without exposing secrets.
