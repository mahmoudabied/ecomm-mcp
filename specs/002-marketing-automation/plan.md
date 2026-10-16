# Implementation Plan: Marketing Automation (Email, SMS & WhatsApp Flows + Klaviyo Connector)

**Branch**: `002-marketing-automation` | **Date**: 2026-10-08 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/002-marketing-automation/spec.md`

## Summary

Add a multi-step, multi-channel automation engine to Bagisto as a new module, `Webkul\MarketingAutomation`. Flows are versioned JSON step graphs (wait / condition / send email / SMS / WhatsApp / exit), started by core Bagisto events and scheduled scans. A per-minute, database-driven dispatcher runs enrolments on a dedicated queue. Messages go out through the store mailer (email) and a pluggable HTTP driver layer: Twilio, Vonage, MessageBird, Unifonic, Msegat and Taqnyat for SMS; Meta Cloud API and Twilio for WhatsApp.

Consent, quiet hours, frequency caps (marketing only), per-step fallback channels, locale/RTL-aware block-based email, unique coupons, last-click attribution and daily rollup reports are first-class. A second module, `Webkul\Klaviyo`, syncs profiles, events and catalog to Klaviyo through a transactional outbox, pulls back unsubscribes, and takes over chosen flow types through mutually exclusive routing (native / Klaviyo / disabled).

## Technical Context

**Language/Version**: PHP 8.4 (composer constraint ^8.2) / Laravel 11.x
**Primary Dependencies**: Bagisto 2.x core modules (Core, Customer, Checkout, Sales, CartRule, Product, Inventory, GDPR, Admin, Shop, DataGrid), `konekt/concord`, `prettus/l5-repository`, Laravel HTTP client (Guzzle), Vue 3 inline components (admin builder), Blade mail components. **New (approved 2026-10-08)**: `giggsey/libphonenumber-for-php-lite` (research R8).
**Storage**: MySQL (existing connection). 20 new tables: 17 in MarketingAutomation and 3 in Klaviyo (data-model.md). Credentials use the `encrypted:array` cast. Cache is used for the scheduler heartbeat.
**Testing**: Pest 3 feature and unit tests per package (`packages/Webkul/{MarketingAutomation,Klaviyo}/tests`), `Http::fake()` with provider fixtures, time travel, plus one Playwright smoke test (`tests/e2e-pw`).
**Target Platform**: Linux server, PHP-FPM or Octane. Needs a queue worker and the scheduler.
**Project Type**: Bagisto modules (Laravel packages) inside this monorepo: admin UI, storefront hooks, background jobs and public webhooks.
**Performance Goals**: 99% of due messages dispatched within 5 min of schedule (SC-003). 10k enrolments a day with no storefront slowdown (SC-006): all storefront listeners only queue work and do one indexed insert at most. Klaviyo events within 5 min (SC-008).
**Constraints**: No core package edits (only events, view-render hooks, config merges and ACL/menu additions). Octane-safe (no static or singleton request state). Merchant content is never compiled as Blade. Must work with the Shop and Amacommerce themes. Arabic RTL.
**Scale/Scope**: About 8 provider drivers, 10 trigger types, 6 step types, 6 templates, ~25 admin endpoints, ~8 public endpoints, 20 tables, 6 scheduled commands.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` is still the blank template, so there are no ratified principles. The project rules in `CLAUDE.md` (Laravel Boost guidelines) are used as gates instead:

| Gate | Pre-research | Post-design |
|---|---|---|
| Follow existing conventions (Concord module, Contract/Model/Proxy/Repository, sibling structure) | ✅ | ✅ R1, data-model |
| Don't create new base folders; stay under `packages/Webkul/*` | ✅ | ✅ |
| No dependency changes without approval | ⚠️ | ✅ One new dependency (`libphonenumber-lite`), approved by the user on 2026-10-08 (R8). SDKs avoided (R7). |
| Every change programmatically tested (Pest) | ✅ | ✅ R19, test suites in quickstart |
| Octane safety (no singletons holding request/config, no static accumulation) | ✅ | ✅ R18 (`scoped` ProviderManager) |
| Laravel way (`make:` generators, Form Requests, named routes, factories) | ✅ | ✅ contracts use named routes; factories per model |
| Security (encrypted secrets, signed tokens, no open redirect, webhook signatures) | ✅ | ✅ R9, R11, storefront contract |
| Docs files only when requested | ✅ | ✅ (spec-kit artifacts only) |

**Result**: PASS. The single new dependency was approved on 2026-10-08 (see Complexity Tracking).

## Project Structure

### Documentation (this feature)

```text
specs/002-marketing-automation/
├── plan.md              # This file
├── spec.md              # Feature specification (clarified 2026-10-08)
├── research.md          # Phase 0 decisions R1–R19
├── data-model.md        # Phase 1 tables, state machines, step schema
├── quickstart.md        # Phase 1 setup and validation walkthrough
├── contracts/
│   ├── admin-http.md            # Admin routes, ACL keys, payloads
│   ├── storefront-public-http.md# Restore cart, unsubscribe, tracking, webhooks
│   ├── provider-driver.md       # Provider interfaces, DTOs, launch drivers
│   └── domain-events.md         # Events dispatched by the module
└── tasks.md             # Phase 2 (/speckit.tasks — not created here)
```

### Source Code (repository root)

```text
packages/Webkul/MarketingAutomation/
├── src/
│   ├── Config/                 # acl.php, menu.php, system.php, automation-providers.php
│   ├── Console/Commands/       # Install, DispatchDue, DetectAbandonedCarts, DailyTriggers, PurgeLogs
│   ├── Contracts/              # Model contracts + Providers/ (MessagingProvider, SupportsInboundOptOut, WhatsAppTemplateSource)
│   ├── Database/{Migrations,Factories,Seeders}/   # FlowTemplateSeeder
│   ├── DataGrids/              # FlowDataGrid, ProviderDataGrid, EnrolmentDataGrid
│   ├── DTOs/                   # OutboundMessage, SendResult, StatusUpdate, TriggerEvent, ...
│   ├── Engine/                 # EnrolmentService, StepRunner, ConditionEvaluator, FlowValidator, Steps/{Wait,Condition,SendEmail,SendSms,SendWhatsApp,Exit}
│   ├── Enums/                  # FlowStatus, FlowType, EnrolmentStatus, MessageChannel, ConsentStatus, SendStatus ...
│   ├── Http/
│   │   ├── Controllers/Admin/  # Flow, FlowTest, Report, Provider, Health
│   │   ├── Controllers/Shop/   # CartRestore, CheckoutContact, Unsubscribe, BackInStock, MarketingPreferences
│   │   ├── Controllers/        # Tracking, Webhook
│   │   └── Requests/           # Form requests per endpoint
│   ├── Jobs/                   # ProcessEnrolmentStep, SendMessage, RecordMessageEvent, ProcessWebhook, SyncWhatsAppTemplates
│   ├── Listeners/              # Customer, Order, Cart, Shipment, Newsletter, Product, Gdpr
│   ├── Mail/                   # AutomationMail (List-Unsubscribe headers)
│   ├── Models/ + *Proxy        # Contact, Consent, ConsentEvent, Flow, FlowVersion, Enrolment, EnrolmentEvent, MessageLog, MessageEvent, ProviderConnection, WhatsAppTemplate, AbandonedCart, BackInStockSubscription, Attribution, StepStat
│   ├── Providers/              # MarketingAutomationServiceProvider, ModuleServiceProvider, EventServiceProvider
│   ├── Repositories/
│   ├── Rendering/              # TemplateRenderer (variables), EmailBlockRenderer, CouponIssuer, Recommendations
│   ├── Services/               # ConsentService, ContactResolver, PhoneNormalizer, SendPolicy (quiet hours/caps), AttributionService, StatsRecorder, ProviderManager, Providers/{Twilio,Vonage,MessageBird,Unifonic,Msegat,Taqnyat,MetaCloud,TwilioWhatsApp}
│   ├── Routes/                 # admin-routes.php, shop-routes.php, public-routes.php
│   └── Resources/{views/admin,views/shop,views/emails/blocks,lang/{en,ar},assets/js}
└── tests/
    ├── Feature/                # Flows, Engine, AbandonedCart, Consent, Providers, Webhooks, Tracking, Reports, Gdpr
    ├── Unit/                   # TemplateRenderer, ConditionEvaluator, PhoneNormalizer, SendPolicy
    ├── Fixtures/Providers/{driver}/*.json
    └── MarketingAutomationTestCase.php

packages/Webkul/Klaviyo/
├── src/
│   ├── Config/                 # acl.php, menu.php
│   ├── Console/Commands/       # FlushOutbox, PullConsent
│   ├── Database/{Migrations,Factories}/
│   ├── Http/{Controllers/Admin,Requests}/
│   ├── Jobs/                   # FlushOutboxBatch, HistoricalSync
│   ├── Listeners/              # Order, Cart, Checkout, Product, Customer, Consent → outbox
│   ├── Models/ + Proxies       # Connection, OutboxItem, SyncJob
│   ├── Providers/
│   ├── Services/               # KlaviyoClient, PayloadMapper, OutboxWriter
│   ├── Routes/                 # admin-routes.php, shop-routes.php (viewed-product beacon)
│   └── Resources/{views,lang}
└── tests/{Feature,KlaviyoTestCase.php}

# Registration touch points (config only, no core logic edits)
bootstrap/providers.php · config/concord.php · composer.json (PSR-4) · phpunit.xml (testsuites) · tests/Pest.php (uses())
packages/Webkul/AmacommerceTheme + Shop views: consent checkboxes injected via view_render_event hooks
```

**Structure Decision**: Two Bagisto Concord modules following the `Marketing` / `GDPR` package layout. The engine (US1–US6) and the Klaviyo connector (US7) are separate so the connector ships later on its own and can be left out.

## Delivery order (for /speckit.tasks)

1. **Foundation**: module scaffolding, contacts, consent, newsletter migration, config/ACL/menu, health check.
2. **US3**: providers, drivers and template sync. **US4** core: consent UI, unsubscribe, quiet hours and caps.
3. **US2 + US1**: engine (versions, enrolment, dispatcher, steps, fallback), abandoned cart detection and restore link, templates, test sends.
4. **US5**: block editor, renderer, locales/RTL, coupons, recommendations.
5. **US6**: tracking, webhooks, attribution, rollups, journey log, retention purge.
6. **GDPR** export/erasure.
7. **US7**: Klaviyo module (outbox, historical sync, consent pull, routing).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| New dependency `giggsey/libphonenumber-for-php-lite` (approved 2026-10-08) | Reliable E.164 normalization and validation for local MENA formats (FR-020). Bad numbers waste paid SMS and hurt WhatsApp quality rating. | A hand-written calling-code table can't validate number length or type per country. |
| Second module (`Klaviyo`) | P3 connector must be shippable and removable on its own | A single module would couple Klaviyo listeners and tables to every install |
| DB-driven scheduler instead of delayed jobs | Pause/resume, exit goals, GDPR cancellation and version binding need mutable schedule state | Delayed queue jobs can't be cancelled or inspected and are lost when the queue is flushed (R2) |
