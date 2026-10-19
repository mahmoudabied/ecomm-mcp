# Marketing Automation for Bagisto

Flows, abandoned-cart recovery and consent-safe messaging for **Bagisto 2.3, 2.4 and 2.5** — email, SMS and WhatsApp journeys driven by storefront events, with a visual flow builder, reporting and GDPR tooling in the admin.

Works out of the box on a stock Bagisto store: `composer require` plus one install command. No host-file edits, no build step.

## Features

- **Flow builder** — draft, version and activate journeys built from prebuilt templates (abandoned cart recovery, welcome series, post-purchase review, win-back, order status updates, back-in-stock alerts) or your own step graphs: triggers, delays, conditions, email/SMS/WhatsApp sends, coupon steps, exits.
- **Abandoned-cart recovery** — idle-cart detection with signed, expiring restore links (guest carts included), click attribution and order-goal conversion tracking.
- **Consent engine** — per-channel consent with full event history, double opt-in for guests (signed confirmation links by mail, or a provider message for SMS/WhatsApp), quiet hours, frequency caps and one-click unsubscribe (RFC 8058).
- **Providers** — Twilio (SMS/WhatsApp), Vonage, MessageBird, Unifonic, Msegat, Taqnyat and Meta Cloud API, with signature-verified webhooks, template sync and delivery receipts.
- **Back-in-stock** — scoped transactional notifications with phone validation, per-IP/per-phone/per-email throttling and trigger-key rotation for repeat notifications.
- **Reporting** — flow report, per-enrolment journey drawer, message events, attribution (click and conversion windows) and a consent audit command.
- **GDPR** — contact data erasure on customer deletion, a downloadable marketing-data export and configurable retention purging.
- **Optional Klaviyo connector** — see `amacraft/bagisto-klaviyo`.

## Requirements

| | |
|---|---|
| Bagisto | 2.3.x, 2.4.x, 2.5.x (Laravel 11, 12, 13) |
| PHP | 8.2 – 8.4 (Bagisto 2.5 itself requires 8.4) |
| Database | MySQL 8 |
| Queue | `database` or `redis` (messages are queued) |
| Languages shipped | English, Arabic (fallback: English) |

## Installation

The packages are not on Packagist. Unzip the download into your Bagisto root so you get `packages/Amacraft/MarketingAutomation` (and `packages/Amacraft/Klaviyo`), then register them as a path repository in your root `composer.json`:

```json
"repositories": {
    "amacraft": {
        "type": "path",
        "url": "packages/Amacraft/*"
    }
}
```

```bash
composer require amacraft/bagisto-marketing-automation:^1.0
php artisan automation:install
```

`automation:install` runs `php artisan migrate` (which also applies any other pending migrations of your application), publishes the precompiled styles to `public/vendor/marketing-automation`, migrates existing newsletter subscribers into email consent and prints the remaining setup steps.

Finally add the scheduler to cron if it is not there yet:

```
* * * * * cd /path/to/bagisto && php artisan schedule:run >> /dev/null 2>&1
```

### Cron and queues

Four commands run on the scheduler: `automation:dispatch-due` (every minute), `automation:detect-abandoned-carts` (every five minutes), `automation:daily-triggers` (hourly) and `automation:purge-logs` (daily). All hold a 10-minute lock and run on one server only, which needs a cache store with atomic locks shared by all servers (`redis`, `database` or `memcached`) when you run more than one.

Message sending runs on queues. Set `QUEUE_CONNECTION=database` (or `redis`) and run a worker that listens to the package queues:

```bash
php artisan queue:work --queue=automation,klaviyo,default --tries=3
```

Queue names are configurable in `config/marketing-automation.php` (publish with `php artisan vendor:publish --tag=ma-config`): set `MA_QUEUE` and `KLAVIYO_QUEUE`, or `null` to use the connection's default queue.

### Styles on a stock store

The storefront and admin snippets rely on a small precompiled stylesheet (the stock bundles purge utilities they have never seen). Every rule is scoped under the `.ma-root` wrapper of the package views, so it never changes your theme's own markup. `automation:install` publishes it to `public/vendor/marketing-automation/`; re-publish any time with `php artisan vendor:publish --tag=ma-css --force`.

### Customising views and translations

```bash
php artisan vendor:publish --tag=ma-views   # resources/views/vendor/marketing_automation
php artisan vendor:publish --tag=ma-lang    # lang/vendor/marketing_automation
```

## Provider setup

Configure connections under **Admin → Settings → Marketing Automation → Providers**, then set a connection as the channel default and send a test message.

Every driver needs an API sender id plus its credentials:

| Driver | Credentials |
|---|---|
| Twilio (SMS / WhatsApp) | Account SID, Auth Token, Messaging Service SID or sender number |
| Vonage | API key, API secret |
| MessageBird | Access key |
| Unifonic | App SID |
| Msegat | Username, API key |
| Taqnyat | Bearer token |
| Meta Cloud API (WhatsApp) | Access token, phone number id (digits), WABA id (digits), app secret |

WhatsApp sending always uses an approved template; sync templates on the connection before sending. Webhooks are signature-verified (Twilio) or secret-verified (Meta, and regional drivers via the URL secret).

**Behind a proxy?** If your load balancer does not pass `X-Forwarded-Proto`/`X-Forwarded-Host`, Twilio signature checks fail because the request URL differs from the public one. Set `MA_PUBLIC_BASE_URL` (published config: `marketing-automation.webhooks.public_base_url`) to the public store URL, or configure TrustProxies.

## Double opt-in

`Settings → Marketing Automation → Consent` exposes **Require double opt-in for guests** (on by default). Guest consent requests (checkout capture, signup checkboxes) then stay *pending* until the signed confirmation link is followed — by mail, or for SMS/WhatsApp through the provider. WhatsApp confirmations need an approved utility template with one body variable; set its name with `MA_WHATSAPP_CONFIRM_TEMPLATE` (default `consent_confirm`). Logged-in customers grant directly. A guest request never downgrades a consent that is already granted.

Confirmations are sent from the queue, never during checkout, and are capped at 3 per email address or phone per day and at `MA_CONFIRM_DAILY_LIMIT` (default 500) per channel per store per day, so scripted sign-ups cannot run up your SMS bill.

## Full page cache

Bagisto's full page cache serves storefront pages to guests from a cache. The package's forms use Bagisto's CSRF token replacement, so cached pages stay valid. The back-in-stock form follows the product page cache: it appears once Bagisto refreshes the cached page of a product that went out of stock.

## Optional Klaviyo connector

`composer require amacraft/bagisto-klaviyo` adds routing to Klaviyo per flow template (native vs Klaviyo), a historical profile/order/catalog sync scoped to the connection's channel, consent mirroring and an unsubscribe pull that revokes matching store consents. See its README.

## Multitenancy note

If your store uses `spatie/laravel-multitenancy`, declare the package jobs as not tenant aware (the package runs on the shared database):

```php
// config/multitenancy.php
'not_tenant_aware_jobs' => [
    // ...
    \Amacraft\MarketingAutomation\Jobs\AttributeOrder::class,
    \Amacraft\MarketingAutomation\Jobs\IngestTrigger::class,
    \Amacraft\MarketingAutomation\Jobs\ProcessBackInStockSubscriptions::class,
    \Amacraft\MarketingAutomation\Jobs\ProcessEnrolmentStep::class,
    \Amacraft\MarketingAutomation\Jobs\ProcessWebhook::class,
    \Amacraft\MarketingAutomation\Jobs\RecordMessageEvent::class,
    \Amacraft\MarketingAutomation\Jobs\SendConsentConfirmation::class,
    \Amacraft\MarketingAutomation\Jobs\SyncWhatsAppTemplates::class,
],
```

## Upgrade

```bash
composer update amacraft/bagisto-marketing-automation
php artisan migrate
php artisan vendor:publish --tag=ma-css --force
```

## Uninstall

```bash
php artisan automation:uninstall --pretend   # optional: print the SQL without changing anything
php artisan automation:uninstall --force
composer remove amacraft/bagisto-klaviyo     # if installed
composer remove amacraft/bagisto-marketing-automation
```

Run the uninstall command **before** `composer remove`. It resets every migration of the package (and of the Klaviyo connector, when installed), whatever batch it ran in, deletes every stored `marketing_automation.*` and `klaviyo.*` setting and removes the published styles in `public/vendor/marketing-automation`. Data cannot be recovered.

## FAQ

**Do I need a build step or Node on the server?**
No. The snippets ship with precompiled CSS, published by the install command.

**Does the package send marketing messages without consent?**
No. Marketing sends require a granted consent; pending and revoked consents never send. Transactional journeys (back-in-stock) are scoped to the subscriber's request.

**Where do webhooks point?**
`https://your-store/automation/webhooks/{driver}/{connection-uuid}` — the URL is shown on the provider page, including the Meta verify token.

**Which locales are included?**
English and Arabic ship with the package; English is the fallback.

**Is multi-store supported?**
Yes. Connections, flows, quiet hours, caps and Klaviyo routing are channel-scoped, and every generated link is built on the message's own channel hostname.

## License

Proprietary. See `LICENSE` for the end-user licence agreement.
