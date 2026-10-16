# Quickstart: Marketing Automation

## Prerequisites

- PHP 8.4, MySQL, a real queue driver (`QUEUE_CONNECTION=database` or `redis`, not `sync`) and the scheduler running.
- Working SMTP settings in `.env` (`MAIL_*`).
- Optional: sandbox accounts for Twilio / Meta WhatsApp Cloud / a regional SMS gateway / Klaviyo.

## Install (dev)

```bash
composer dump-autoload                         # after PSR-4 entries for MarketingAutomation + Klaviyo are added
composer require giggsey/libphonenumber-for-php-lite   # approved dependency (research R8)
php artisan migrate
php artisan automation:install                 # seeds flow templates, migrates newsletter consent (source migrated_newsletter)
php artisan optimize:clear
npm --prefix packages/Webkul/Admin run build   # if admin assets for the flow builder change
```

Run the workers:

```bash
php artisan queue:work --queue=automation,klaviyo,default
php artisan schedule:work
```

Scheduled commands: `automation:dispatch-due` (every minute), `automation:detect-abandoned-carts` (every 5 min), `automation:daily-triggers` (birthday / inactive, hourly with per-channel local-midnight gating), `automation:purge-logs` (daily), `klaviyo:flush-outbox` (every minute), `klaviyo:pull-consent` (every 15 min).

## Validate the main journeys

1. **Providers (US3)**: Admin → Marketing → Automation → Providers → add Twilio SMS → "Test connection" to your phone, which should show *connected*. Add Meta WhatsApp → "Sync templates" lists approved templates. Invalid token gives a plain-language error.
2. **Abandoned cart (US1)**: Use template "Abandoned Cart" and set the delay to 2 minutes (trigger config) and the wait to 2 minutes. Activate. As a guest, add a product, enter an email and phone at checkout, tick the WhatsApp consent box, then leave. After about 2 minutes the email arrives and its restore link rebuilds the cart in a private window. After the wait, the WhatsApp message arrives. Repeat, but place the order after the email: no WhatsApp message is sent, and the enrolment shows `converted`.
3. **Consent (US4)**: Register with only email consent. A flow with email + WhatsApp sends only email, and the journey log shows `skipped – no_consent` (or `fallback_used` if a fallback is set). Click unsubscribe: consent is revoked and the next marketing email is skipped.
4. **Quiet hours**: Set quiet hours to cover the current time. A marketing SMS gets `deferred – quiet_hours`, while a transactional "order shipped" SMS sends immediately.
5. **Reports (US6)**: Flow → Report shows the counts above. The recovered order appears once with its revenue.
6. **Klaviyo (US7)**: Marketing → Klaviyo → connect a test key → sync. Place an order and `Placed Order` appears in Klaviyo within minutes. Unsubscribe the profile in Klaviyo and within 15 minutes store consent shows `revoked (klaviyo)`. Set routing for Abandoned Cart to *Klaviyo*: the native flow stops enrolling.

## Tests

```bash
php artisan test --compact --testsuite="MarketingAutomation Feature Test"
php artisan test --compact --testsuite="MarketingAutomation Unit Test"
php artisan test --compact --testsuite="Klaviyo Feature Test"
vendor/bin/pint --dirty --format agent
```

Local tip: `php artisan automation:dispatch-due --now` runs due steps immediately, and `--travel=+1h` in dev simulates waits.
