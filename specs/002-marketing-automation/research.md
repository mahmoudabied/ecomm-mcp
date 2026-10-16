# Phase 0 Research: Marketing Automation

**Feature**: `002-marketing-automation` | **Date**: 2026-10-08

All Technical Context unknowns are resolved below. Each entry: Decision / Rationale / Alternatives considered.

---

## R1. Packaging

**Decision**: Two new Concord modules inside this codebase:

- `packages/Webkul/MarketingAutomation` (namespace `Webkul\MarketingAutomation`): flow engine, channels, providers, consent, content, reporting (US1–US6).
- `packages/Webkul/Klaviyo` (namespace `Webkul\Klaviyo`): Klaviyo connector (US7). It depends on `MarketingAutomation` for contacts, consent and flow routing.

Both are registered in `bootstrap/providers.php`, `config/concord.php`, `composer.json` PSR-4, `phpunit.xml` testsuites and `tests/Pest.php`, the same way as `Marketing` and `GDPR`.

**Rationale**: Matches how every Bagisto domain ships (Contracts, Models + Proxy, Repositories via `prettus/l5-repository`, Providers, Database/Migrations, Resources/views/lang). Klaviyo is P3 and independently shippable, so a separate module keeps it optional.

**Alternatives**: Extending `Webkul\Marketing` (rejected: it's a core package, and mixing in risks upstream merge conflicts). A single package for everything (rejected: Klaviyo couldn't be shipped or disabled on its own).

---

## R2. Flow execution model (scheduling)

**Decision**: Database-driven scheduler, not delayed queue jobs.

- Each `automation_enrolments` row has `status`, `current_step_key`, `next_run_at`, `locked_until`.
- `automation:dispatch-due` runs every minute (`withoutOverlapping`). It claims due rows in chunks of 500 with an atomic `UPDATE … SET locked_until = now()+5min, lock_token = ? WHERE next_run_at <= now() AND (locked_until IS NULL OR locked_until < now()) LIMIT 500`. It then dispatches one `ProcessEnrolmentStep` job per row on the `automation` queue.
- The job runs steps synchronously until it reaches a wait, a deferral (quiet hours or cap), or an exit, then writes the new `next_run_at`.
- Pause sets `flows.status = paused`. The dispatcher skips enrolments of paused flows. On resume, steps more than 24 h overdue are logged as `skipped – expired` and the flow moves past them (spec US2-AS2).

**Rationale**: Pause/resume, exit goals, editing and GDPR cancellation all need mutable schedule state. Delayed jobs can't be cancelled or inspected and are lost when the queue is flushed. A `(status, next_run_at)` index lets one query per minute handle 10k+ enrolments a day (SC-003, SC-006). The lock token makes claims idempotent across overlapping workers.

**Alternatives**: `dispatch()->delay()` per step (rejected: can't be cancelled, and the sync driver runs it inline). Event sourcing (rejected: more machinery than needed).

---

## R3. Flow definition and versioning

**Decision**: Draft and published versions are stored as JSON graphs.

- `automation_flows.draft_definition` (JSON) is what the builder edits.
- Activation validates the draft, then writes an immutable `automation_flow_versions` row (`version` int, `definition` JSON, `definition_hash`). The flow's `current_version_id` points to it.
- Definition shape: `{ "entry": "s1", "steps": { "s1": { "type": "send_email", "next": "s2", ... }, "s3": { "type": "condition", "rule": {...}, "yes": "s4", "no": "s5" } } }`. Message content (per locale) is embedded in the step, so a version is fully self-contained (FR-007).
- Enrolments reference `flow_version_id` + `current_step_key`.

**Rationale**: An immutable snapshot is exactly what FR-007 asks for. JSON avoids versioned copies of many relational rows, and per-step reporting keys off a stable `step_key` that carries across versions when the step wasn't removed.

**Alternatives**: Relational `flow_steps` rows copied per version (rejected: copy-on-write across steps, content and branches is error-prone and gives no query benefit, because steps are always loaded as a whole graph).

---

## R4. Triggers and ingestion

**Decision**: Listen to existing Bagisto events and turn them into `TriggerEvent` DTOs, which `EnrolmentService::enrol()` processes on the queue.

| Trigger | Source |
|---|---|
| customer registered | `customer.registration.after` |
| newsletter subscribed | `customer.subscription.after` |
| order placed | `checkout.order.save.after` |
| order status changed / completed | `sales.order.update`, `sales.order.cancel.after`, `sales.invoice.save.after` (status diff) |
| order shipped | `sales.shipment.save.after` |
| cart abandoned | scheduled scan (R5) |
| back in stock | `catalog.product.update.after` + inventory save; qty goes 0 → >0 for products with subscriptions (core has no stock-alert feature, so this module adds `automation_back_in_stock_subscriptions` and a storefront "Notify me" form) |
| inactive N days | daily scan of last order date |
| birthday | daily scan of `customers.date_of_birth` in the channel's timezone |

Idempotency: `automation_enrolments` has a unique `(flow_id, contact_id, trigger_key)`, where `trigger_key` is something like `order:123` or `cart:45:2026-10-08T10`. Re-entry window: before inserting, check for any enrolment of the same flow and contact created within `reentry_window_hours`.

**Rationale**: Uses public extension points only, with no core edits. The unique key makes duplicate events harmless (edge case: duplicate trigger events).

---

## R5. Abandoned cart detection

**Decision**: `automation:detect-abandoned-carts` runs every 5 minutes. It selects `cart` rows where `is_active = 1`, `items_count > 0`, `updated_at` is older than the configured delay, and a contact exists (`customer_id`, `customer_email`, or a `cart_addresses.phone`). It upserts `automation_abandoned_carts` keyed by `cart_id`. A cart update after detection resets the record (`checkout.cart.update.after` / `add.after` set `status = reopened`), so a fresh idle period is needed. Order placement (`checkout.order.save.after`) marks the record `recovered`, fires exit goals and cancels pending steps (SC-005).

Guest capture: Bagisto writes `cart.customer_email` and billing-address phone when the guest submits the address step. A small storefront JS hook on the email/phone field blur posts to `POST /automation/checkout-contact` so contacts are captured before the address step is submitted (spec assumption: no exit-intent popups).

Guest → login merge: the listener on `customer.after.login` re-points the contact and abandoned-cart record to the customer, then cancels the duplicate enrolment (edge case: one enrolment keyed to the customer).

---

## R6. Restore-cart link

**Decision**: HMAC-derived token `{id}.{base64url(HMAC-SHA256(id|detected_at, APP_KEY))}`, rebuilt whenever a link is rendered, so the plain token is never stored. Its SHA-256 is stored on `automation_abandoned_carts.restore_token_hash` with `restore_token_expires_at` (default 7 days). `GET /automation/cart/restore/{token}` re-activates the cart into the session (for guests it sets `cart_id` in the session; for customers it merges into their active cart through `Cart` facade methods), then redirects to `/checkout/cart` with UTM and click tracking. Expired or unknown tokens go to the cart page with a flash message.

**Rationale**: Works on any device without login (FR-012). Storing only the hash means a DB leak doesn't expose usable links.

---

## R7. Provider abstraction

**Decision**: Driver interfaces in `Contracts/Providers` with one class per vendor, all built on Laravel's `Http` client (Guzzle is already installed). No vendor SDKs.

```php
interface MessagingProvider {
    public function send(OutboundMessage $message): SendResult;      // sent|queued + provider_message_id, or retryable/permanent error
    public function testConnection(ProviderConnection $c, string $to): SendResult;
    public function parseStatusWebhook(Request $r, ProviderConnection $c): array; // StatusUpdate[]
    public function verifyWebhook(Request $r, ProviderConnection $c): bool;
}
interface SupportsInboundOptOut { public function parseInbound(Request $r, ProviderConnection $c): ?InboundMessage; }
interface WhatsAppTemplateSource { public function fetchTemplates(ProviderConnection $c): array; } // WhatsAppTemplate[]
```

Drivers at launch: SMS: `twilio`, `vonage`, `messagebird`, `unifonic`, `msegat`, `taqnyat`. WhatsApp: `meta_cloud`, `twilio_whatsapp`. Registered in `config/automation-providers.php` (`key => [class, channel, credential fields schema]`), so adding Cequens or Victory Link only means adding a class and a config entry (FR-015).

Error classes: HTTP 429/5xx/timeouts are `retryable`. 4xx auth or invalid-number errors are `permanent`, which triggers the fallback channel (FR-003a). Retries use the job's `backoff()` `[60, 300, 900, 3600, 7200, …]` capped at 24 h total by `retryUntil()` (FR-019).

**Rationale**: Each vendor API is a single REST call. SDKs would add six-plus dependencies, and the project rules require approval for those.

**Alternatives**: `laravel-notification-channels/*` packages (rejected: these are new dependencies, have uneven coverage of MENA gateways, and none support template sync).

---

## R8. Phone normalization

**Decision**: Use `giggsey/libphonenumber-for-php-lite` to normalize to E.164. The region comes from the customer address country, falling back to the channel's default country (`core()->getConfigData('sales.shipping.origin.country')`).

**Approved (2026-10-08)**: This is the only new Composer dependency. It is kept behind `PhoneNormalizer`, so it can be swapped out later.

**Alternatives**: A hand-rolled table of GCC/MENA calling codes (rejected: it can't validate number length or type per country).

---

## R9. Credentials storage

**Decision**: `automation_provider_connections.credentials` uses the `encrypted:array` cast (APP_KEY). The admin API returns only masked values (`••••last4`). On update, an empty secret field means "keep the existing value" (FR-017).

---

## R10. Email content: editor and rendering

**Decision**: Content is stored as a block JSON array per locale: `[{type: "header"|"text"|"image"|"button"|"product_grid"|"cart_items"|"order_summary"|"coupon"|"footer", props: {...}}]`. The admin editor is a Vue 3 inline component (`<v-automation-email-editor>`) in the admin Blade layout, matching Bagisto's admin Vue pattern (reorder, edit props, live preview iframe). Rendering is server-side: each block type maps to a Blade component producing table-based, inline-styled email HTML. The wrapper sets `dir` and alignment from `locales.direction`, so RTL works for Arabic (FR-029).

Variables use `{{ customer.first_name | default: "there" }}` syntax, parsed by a small whitelisted `TemplateRenderer` (regex tokenizer, no `eval`, no Blade compilation of merchant input). The same renderer handles SMS and WhatsApp bodies. WhatsApp sends map variables to the approved template's `{{1}}…{{n}}` parameters (FR-014, FR-027).

**Rationale**: Compiling merchant-authored Blade would be remote code execution. A whitelist renderer is safe and testable.

**Alternatives**: GrapesJS/Unlayer (rejected: heavy new frontend dependencies and licensing). MJML (rejected: needs Node at runtime).

---

## R11. Tracking (opens, clicks, unsubscribe)

**Decision**:

- Open pixel: `GET /automation/t/o/{messageUuid}.gif`.
- Click: `GET /automation/t/c/{messageUuid}/{linkHash}?u=…` redirects only to URLs recorded for that message (no open redirect).
- Unsubscribe: `GET /automation/unsubscribe/{token}` shows a confirm page. `POST` on the same URL handles RFC 8058 one-click (`List-Unsubscribe-Post: List-Unsubscribe=One-Click`).

Tokens are message UUIDs plus HMAC (`hash_hmac('sha256', uuid, APP_KEY)`). Tracking endpoints write `automation_message_events` asynchronously (queued).

---

## R12. Consent model

**Decision**: `automation_contacts` unifies customers and guests (nullable `customer_id`, `email`, `phone_e164`, `locale`, `channel_id`, `country`). `automation_consents` holds the current state per `(contact_id, channel)`. `automation_consent_events` is the append-only history (state, source, IP, user agent, timestamp).

- Install migration/command: for each `subscribers_list` row with `is_subscribed = 1`, create a contact and grant email consent with source `migrated_newsletter` (clarification Q1). SMS and WhatsApp start without consent.
- Bi-directional sync with Bagisto newsletter: listening to `customer.subscription.after` updates email consent. When email consent is revoked, `subscribers_list.is_subscribed` is set to 0.
- Opt-in checkboxes: added via Blade view-render events (`bagisto.shop.customers.signup_form...`, checkout address and account profile hooks) in both the Shop and Amacommerce themes. Unchecked by default.

---

## R13. Quiet hours, frequency caps, timezones

**Decision**: Admin settings live in `Admin/Config/system.php`-style config under `marketing_automation.sending.*`, scoped per channel: `quiet_hours_start`, `quiet_hours_end`, `cap_per_channel_count`, `cap_window_hours`. All time math uses `channels.timezone`, falling back to `config('app.timezone')`. These apply only to marketing messages (clarification Q4).

- Quiet hours: `next_run_at` becomes the next quiet-hours end.
- Cap: count `automation_message_logs` where `is_marketing = 1` for the contact and channel within the window (index `(contact_id, channel, is_marketing, created_at)`). If the cap is hit, defer to the moment the oldest message in the window expires. If that is past the step's `validity_hours` (default 24), skip with `skipped – frequency cap`.

---

## R14. Coupons

**Decision**: The coupon block references a `cart_rule_id`, which must have `coupon_type = specific` and `use_auto_generation = 1`. At render time a single `cart_rule_coupons` row is created (`usage_limit = 1`, `usage_per_customer = 1`, `expired_at` from block props), using the `CartRuleCouponRepository` code format. It's created once per message log (idempotent on retry, because the code is stored on the log).

---

## R15. Attribution and reporting

**Decision**:

- On `checkout.order.save.after`, find the contact's most recent `clicked` event within the click window (default 120 h). If there is none, find the most recent `delivered` SMS/WhatsApp without click tracking within 24 h. Insert into `automation_attributions` with unique `order_id`, so each order is counted once, last click wins (FR-031, SC-007).
- Metrics: `automation_step_stats` daily rollup `(date, flow_id, step_key, channel_id, metric, count, revenue)`, incremented in the same transaction as the log or event write. Reports sum the rollups. The journey log reads `automation_message_logs` and `automation_enrolment_events`.
- Retention: `automation:purge-logs` (daily) deletes logs and events older than `retention_months` (default 12). Rollups and attributions are kept (FR-032a).

**Rationale**: Rollups make report queries O(days × steps) and keep them correct after log purge.

---

## R16. GDPR integration

**Decision**: Listen for GDPR data request status changes on the existing `gdpr_data_requests` flow (`customer.account.gdpr-request.*` events, plus admin processing).

- Export: add consent records, enrolments and message logs to the export payload.
- Erasure: cancel enrolments, delete the contact, consents, consent events, logs and message events. Rollups keep only counts.

Customer deletion (`customer.delete.after`) runs the same erasure.

---

## R17. Klaviyo connector

**Decision**: Klaviyo REST API with the header `revision: 2024-10-15` and auth `Authorization: Klaviyo-API-Key <private key>`, via the `Http` client.

- Profiles: `POST /api/profile-import`, plus subscription jobs `POST /api/profile-subscription-bulk-create-jobs` for email/SMS consent.
- Events: `POST /api/events` with `unique_id = "{store}-{event}-{entity}-{id}"`. Klaviyo de-duplicates events on `unique_id`, which gives no duplicates (FR-037, SC-008).
- Catalog: `POST /api/catalog-items` / `PATCH`, and bulk jobs for the historical sync.
- Outbox: `klaviyo_outbox` (transactional outbox pattern). Domain listeners insert a row in the same DB transaction. The `klaviyo:flush-outbox` job (every minute plus on-demand dispatch) sends batches, retries with back-off and marks rows `sent`.
- Historical sync: `klaviyo_sync_jobs` with a per-entity cursor and processed/total counts, shown as admin progress (FR-035).
- Consent pull: every 15 minutes, poll `GET /api/events?filter=equals(metric_id,"<Unsubscribed metric ids>"),greater-than(datetime,<cursor>)` and revoke consent with source `klaviyo` (FR-036). Polling is used because webhooks need a paid add-on.
- Routing (FR-038): per channel and flow type config `marketing_automation.routing.{flow_type} ∈ {native, klaviyo, disabled}`. The native `EnrolmentService` refuses to enrol unless the value is `native`. The `klaviyo` value can only be selected while a Klaviyo connection is active.

---

## R18. Queue, scheduler, Octane

**Decision**: Jobs run on named queues `automation` (sends) and `klaviyo`. The admin dashboard shows a setup health check that warns when `QUEUE_CONNECTION=sync` (the current `.env`) or when the scheduler heartbeat (cache key written by `automation:dispatch-due`) is older than 5 minutes. All services are stateless. Provider drivers are resolved per call through `ProviderManager` (registered with `scoped`, not `singleton`), so they're safe under Octane.

---

## R19. Testing strategy

**Decision**: Pest 3 feature tests in `packages/Webkul/MarketingAutomation/tests/Feature` and `packages/Webkul/Klaviyo/tests/Feature`, bound in `tests/Pest.php` to new `MarketingAutomationTestCase` / `KlaviyoTestCase` classes, plus Unit tests for the renderer, normalizer and condition evaluator. External HTTP is faked with `Http::fake()` and contract fixtures per provider. Time is controlled with `$this->travelTo()`, and queues with `Queue::fake()` / sync driver in tests. Factories are provided for every new model. Playwright e2e (existing `tests/e2e-pw`) covers one smoke flow: the abandoned cart email with restore link.
