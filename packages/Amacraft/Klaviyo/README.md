# Klaviyo Connector for Bagisto Marketing Automation

Optional connector between [amacraft/bagisto-marketing-automation](../MarketingAutomation/README.md) and **Klaviyo** for **Bagisto 2.3.x**: route selected flow templates to Klaviyo instead of the native sender, mirror consent, sync history and pull unsubscribes back.

## Features

- **Routing** — per sales channel and per flow template, choose *native* or *Klaviyo*. When Klaviyo is routed but no active connection exists, the package falls back to native sending.
- **Historical sync** — push customers, orders (last twelve months) and the catalog of the connection's channel into Klaviyo, chunked through the outbox with progress shown in the admin. Customer sync is scoped to the connection's own channel.
- **Consent mirroring** — granted and revoked store consents become Klaviyo subscribe/unsubscribe events; Klaviyo-sourced changes are never echoed back.
- **Unsubscribe pull** — `klaviyo:pull-consent` fetches unsubscribe events with `include=profile,metric` and revokes the matching store consents per channel, keeping a cursor per connection.

## Requirements

| | |
|---|---|
| amacraft/bagisto-marketing-automation | same version (installed automatically) |
| Bagisto | 2.3.x |
| PHP | 8.2 – 8.4 |
| Klaviyo | private API key with **Read/Write** access |

## Installation

```bash
composer require amacraft/bagisto-klaviyo
php artisan vendor:publish --tag=klaviyo-config # if you published the MA config before
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

```bash
composer remove amacraft/bagisto-klaviyo
php artisan automation:uninstall --force   # also drops Klaviyo tables and settings
```

## License

Proprietary. See `LICENSE` for the end-user licence agreement.
