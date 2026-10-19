# Changelog

## 1.0.0 — 2026-10-19

First stable release.

### Marketing automation

- Flow engine: triggers, conditions, delays, email/SMS/WhatsApp steps, coupons, exits, re-entry windows and versioned activation.
- Prebuilt templates: abandoned cart recovery, welcome series, post-purchase review, win-back, order status updates, back-in-stock alerts.
- Abandoned-cart detection with signed restore links, guest support and order-goal conversion.
- Consent engine with double opt-in for guests (queued, rate-limited confirmations that never downgrade an existing grant), signed confirmation links, per-channel history, quiet hours and frequency caps.
- Providers: Twilio (SMS/WhatsApp), Vonage, MessageBird, Unifonic, Msegat, Taqnyat, Meta Cloud API — with webhooks, template sync and delivery receipts.
- Back-in-stock subscriptions with transactional sending, phone validation, per-IP/per-phone/per-email throttling and re-subscribe key rotation.
- Admin: flow builder, providers, health page, flow report, journey drawer, datagrids with ACL.
- GDPR: erasure on customer deletion, marketing-data export, retention purging, consent audit command.
- Installable as a composer package with `automation:install` / `automation:uninstall` (with `--pretend`); publishable config, views, translations and precompiled styles scoped under `.ma-root`; configurable queues; scheduler mutexes with a 10 minute expiry and `onOneServer`.

### Klaviyo connector

- Channel/template routing with native fallback.
- Historical sync for customers, orders and catalog scoped to the connection channel.
- Consent mirroring and an unsubscribe pull that queries each unsubscribe metric, resolves profiles from `included` and keeps its cursor when a request fails.
