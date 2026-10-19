# Klaviyo Connector for Bagisto Marketing Automation

Optional connector between [amacraft/bagisto-marketing-automation](../MarketingAutomation/README.md) and **Klaviyo** for **Bagisto 2.3.x**: route selected flow templates to Klaviyo instead of the native sender, mirror consent, sync history and pull unsubscribes back.

## Features

- **Routing** — per sales channel and per flow template, choose *native* or *Klaviyo*. When Klaviyo is routed but no active connection exists, the package falls back to native sending.
- **Historical sync** — push customers, orders (last twelve months) and the catalog of the connection's channel into Klaviyo, chunked through the outbox with progress shown in the admin. Customer sync is scoped to the connection's own channel; customers without a channel are not synced.
- **Consent mirroring** — granted and revoked store consents become Klaviyo subscribe/unsubscribe events; Klaviyo-sourced changes are never echoed back.
- **Unsubscribe pull** — `klaviyo:pull-consent` fetches the events of each unsubscribe metric oldest-first and revokes the matching store consents per channel. The cursor per connection only moves forward once every page was read, so a failed request is retried on the next run.

## Requirements

| | |
|---|---|
| amacraft/bagisto-marketing-automation | ^1.0 (installed automatically) |
| Bagisto | 2.3.x |
| PHP | 8.2 – 8.4 |
| Klaviyo | private API key with **Read/Write** access |

## Installation

With the `packages/Amacraft/*` path repository from the Marketing Automation README in place:

```bash
composer require amacraft/bagisto-klaviyo:^1.0
php artisan migrate
```

Connect an account under **Admin → Settings → Marketing Automation → Klaviyo**: paste the private API key, test the connection, then start a historical sync per entity if you want existing data in Klaviyo.

### Scheduler and queues

The connector adds `klaviyo:flush-outbox` (every minute) and `klaviyo:pull-consent` (every fifteen minutes), both locked and once-per-server. Outbox delivery runs on the `klaviyo` queue — include it in your worker:

```bash
php artisan queue:work --queue=automation,klaviyo,default --tries=3
```

### Multitenancy note

With `spatie/laravel-multitenancy`, add the connector jobs to `not_tenant_aware_jobs`:

```php
\Amacraft\Klaviyo\Jobs\FlushOutboxBatch::class,
\Amacraft\Klaviyo\Jobs\HistoricalSync::class,
```

## How routing works

Each channel picks a platform per flow template key (`Settings → Marketing Automation → Routing`). A flow whose template is routed to Klaviyo is not enrolled natively — the event goes to Klaviyo through the outbox instead. Everything else keeps running natively, so you can move only your abandoned-cart journey to Klaviyo and keep the rest in Bagisto.

## Upgrade

```bash
composer update amacraft/bagisto-klaviyo
php artisan migrate
```

## Uninstall

Run the uninstall command while the connector is still installed, so it can find its migrations:

```bash
php artisan automation:uninstall --force   # drops the automation and Klaviyo tables and settings
composer remove amacraft/bagisto-klaviyo
```

## License

Proprietary. See `LICENSE` for the end-user licence agreement.
