# Phase 1 Data Model: Marketing Automation

**Feature**: `002-marketing-automation` | **Date**: 2026-10-08

Conventions: Bagisto style, with `increments('id')` and unsigned int FKs to core tables (`customers`, `channels`, `orders`, `cart`, `cart_rules`, `products` all use int ids). Timestamps on every table. Models follow Contract + Model + Proxy + Repository. Every new model gets a factory.

Existing core tables used (not modified): `customers`, `customer_addresses`, `cart`, `cart_items`, `cart_addresses`, `orders`, `order_items`, `subscribers_list`, `channels` (`timezone`), `locales` (`direction`), `cart_rules`, `cart_rule_coupons`, `gdpr_data_requests`, `products`, `product_inventories`.

---

## MarketingAutomation module

### automation_contacts — *Contact*
One row per reachable person, whether a customer or a guest.

| Column | Type | Notes |
|---|---|---|
| id | increments | |
| uuid | uuid, unique | Used in public tokens |
| channel_id | int FK channels | |
| customer_id | int FK customers, nullable, `onDelete cascade` | |
| email | string(191), nullable | lower-cased |
| phone_e164 | string(20), nullable | normalized (FR-020) |
| phone_raw | string(50), nullable | as entered |
| first_name, last_name | string, nullable | guest snapshot |
| locale | string(10) | locale code at capture time, updated on login/order |
| country | char(2), nullable | used for phone normalization |
| last_order_at | timestamp, nullable | denormalized for win-back and conditions |

Indexes: unique `(channel_id, customer_id)`, `(channel_id, email)`, `(channel_id, phone_e164)`.
Identity rules: look up by customer_id, then email, then phone. When a guest logs in or registers, the guest contact is merged into the customer contact (consents are combined: the latest event wins per channel; enrolments are re-pointed or de-duplicated).

### automation_consents — *Contact Consent (current state)*

| Column | Type | Notes |
|---|---|---|
| id | increments | |
| contact_id | FK automation_contacts cascade | |
| channel | enum `email`,`sms`,`whatsapp` | |
| status | enum `granted`,`revoked`,`none` | |
| source | string(50) | `registration`,`checkout`,`account`,`unsubscribe_link`,`sms_stop`,`whatsapp_optout`,`admin`,`klaviyo`,`migrated_newsletter`,`newsletter` |
| changed_at | timestamp | |

Unique `(contact_id, channel)`.

### automation_consent_events — *Consent history (append-only)*
`id, contact_id FK, channel, status, source, ip (string 45, nullable), user_agent (string 255, nullable), meta json nullable, created_at`. Index `(contact_id, channel, created_at)`.

### automation_flows — *Flow*

| Column | Type | Notes |
|---|---|---|
| id | increments | |
| name | string | |
| template_key | string(50), nullable | `abandoned_cart`,`welcome`,`post_purchase`,`win_back`,`order_status`,`back_in_stock` when created from a template |
| trigger_type | string(50) | `cart_abandoned`,`customer_registered`,`newsletter_subscribed`,`order_placed`,`order_status_changed`,`order_shipped`,`order_completed`,`back_in_stock`,`customer_inactive`,`customer_birthday` |
| trigger_config | json | e.g. `{abandon_delay_minutes: 60}`, `{status: "processing"}`, `{inactive_days: 90}` |
| type | enum `marketing`,`transactional` | FR-010 |
| status | enum `draft`,`active`,`paused` | default `draft` |
| reentry_window_hours | unsigned int, default 168 | FR-006 |
| exit_goals | json | e.g. `[{"type":"order_placed"}]` |
| draft_definition | json | step graph being edited |
| current_version_id | int FK automation_flow_versions, nullable | |
| activated_at, paused_at, resumed_at | timestamp, nullable | `resumed_at` drives the >24 h overdue-skip rule on resume (US2-AS2) |
| created_by | int FK admins, nullable | |

Pivot **automation_flow_channels**: `(flow_id, channel_id)` primary key (FR-001).

State transitions:
```
draft ──activate(valid)──▶ active ──pause──▶ paused ──resume──▶ active
  ▲                          │                  │
  └──────── (edits always go to draft_definition; activate/publish creates a new version)
active|paused ──delete──▶ (soft: cancels enrolments, then hard-deletes flow; rollups retained by flow_id)
```
Activation validation (US2-AS5): has ≥1 send step; every `next`/`yes`/`no` points to an existing key; no cycles without a wait; every WhatsApp step references a synced `approved` template for each provider in scope; SMS/WhatsApp steps have a connected provider for each of the flow's channels; transactional flows contain no `coupon`/`product_grid` (recommended) blocks; coupon blocks reference an auto-generation cart rule.

### automation_flow_versions — *Flow Version (immutable)*
`id, flow_id FK cascade, version (unsigned int), definition json, definition_hash char(64), created_at`. Unique `(flow_id, version)`. No `updated_at`; never updated.

**Step definition schema** (inside `definition.steps[key]`). This is where the spec's *Message Template* entity lives: message content per channel and locale is stored inside the step definition of the immutable flow version, not as a separate table. Only provider-approved WhatsApp templates are a separate table (`automation_whatsapp_templates`), referenced by `template_id`.

| type | fields |
|---|---|
| `wait` | `mode: duration\|until_time\|until_weekday`, `minutes`, `time` (`HH:MM`), `weekdays[]`, `next` |
| `condition` | `rule: {all\|any: [{field, op, value}]}`, `yes`, `no` |
| `send_email` | `subject{locale}`, `preheader{locale}`, `blocks{locale}[]`, `sender_override?`, `validity_hours`, `next` |
| `send_sms` | `body{locale}`, `fallback_channel?: email\|whatsapp`, `fallback_step?` (inline content), `validity_hours`, `next` |
| `send_whatsapp` | `template_id` (automation_whatsapp_templates.id), `variables{n: expression}`, `language{locale}`, `fallback_channel?: sms\|email`, `fallback_step?`, `validity_hours`, `next` |
| `exit` | — |

Condition fields (FR-004): `has_ordered_since_enrolment`, `order_count`, `lifetime_spend`, `last_order_total`, `customer_group_id`, `locale`, `channel_id`, `has_consent.{email|sms|whatsapp}`, `cart_value`, `cart_contains_product`, `cart_contains_category`, `order_contains_product`, `order_contains_category`. Operators: `eq, neq, gt, gte, lt, lte, in, not_in, is_true, is_false`.

### automation_enrolments — *Enrolment*

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | high volume |
| flow_id | FK automation_flows cascade | |
| flow_version_id | FK automation_flow_versions | |
| contact_id | FK automation_contacts cascade | |
| channel_id | FK channels | |
| trigger_key | string(100) | e.g. `cart:45:1728381600`, `order:123` |
| trigger_payload | json | `{cart_id}`, `{order_id}`, `{product_id}` |
| status | enum `active`,`completed`,`converted`,`exited`,`cancelled` | |
| exit_reason | string(50), nullable | `goal_met`,`paused_expired`,`gdpr`,`flow_deleted`,`merged`,`routing_disabled` |
| current_step_key | string(20), nullable | |
| next_run_at | timestamp, nullable | |
| locked_until | timestamp, nullable | |
| lock_token | char(26), nullable | ULID |
| completed_at | timestamp, nullable | |

Indexes: unique `(flow_id, contact_id, trigger_key)` (idempotency); `(status, next_run_at)` (dispatcher); `(contact_id, flow_id, created_at)` (re-entry window).

State transitions:
```
active ──reaches exit / end of graph──▶ completed
active ──exit goal met (e.g. order placed)──▶ converted
active ──contact erased / flow deleted / merged / routing switched──▶ cancelled
active ──step overdue >24h on resume (continues), or explicit exit-condition──▶ exited
```

### automation_enrolment_events — *Journey log*
`id bigIncrements, enrolment_id FK cascade, step_key, event enum(entered, waited, condition_yes, condition_no, sent, skipped, deferred, failed, fallback_used, exited), reason string(50) nullable (no_consent, invalid_contact, no_provider, frequency_cap, quiet_hours, expired, cart_empty, product_unavailable), message_log_id nullable, meta json, created_at`. Index `(enrolment_id, created_at)`. Purged by retention.

### automation_message_logs — *Message Log*

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| uuid | uuid unique | tracking tokens |
| enrolment_id | FK nullable (null for test sends) | |
| flow_id, step_key | | denormalized for rollups |
| contact_id | FK nullable | |
| channel_id | FK channels | |
| message_channel | enum `email`,`sms`,`whatsapp` | |
| is_marketing | boolean | cap counting |
| is_test | boolean default 0 | |
| is_fallback | boolean default 0 | FR-003a |
| provider_connection_id | FK nullable | null for email |
| recipient | string(191) | email or E.164 |
| locale | string(10) | |
| subject | string nullable | |
| rendered_body | mediumText nullable | purged with retention |
| coupon_code | string nullable | R14 idempotency |
| links | json nullable | `{hash: url}` click whitelist |
| status | enum `queued`,`sent`,`delivered`,`read`,`failed`,`bounced` | |
| provider_message_id | string(191) nullable, index | webhook lookup |
| error_code, error_message | nullable | provider reason (US6-AS2) |
| attempts | tinyint | |
| sent_at, delivered_at, opened_at, clicked_at | timestamps nullable | first occurrence |

Indexes: `(contact_id, message_channel, is_marketing, created_at)` (caps); `(flow_id, step_key, created_at)`; `(provider_connection_id, provider_message_id)`.

### automation_message_events
`id bigIncrements, message_log_id FK cascade, type enum(sent, delivered, read, opened, clicked, failed, bounced, unsubscribed, complained), url string nullable, meta json, occurred_at`. Index `(message_log_id, type)`.

### automation_provider_connections — *Provider Connection*

| Column | Type | Notes |
|---|---|---|
| id | increments | |
| uuid | uuid, unique | webhook URL segment |
| name | string | |
| message_channel | enum `sms`,`whatsapp` | email uses the store mailer |
| driver | string(30) | `twilio`,`vonage`,`messagebird`,`unifonic`,`msegat`,`taqnyat`,`meta_cloud`,`twilio_whatsapp` |
| credentials | text, `encrypted:array` cast | never returned unmasked (FR-017) |
| sender | string(50) | sender ID / from number / phone_number_id |
| webhook_secret | string(64) | generated, for webhook URL + verification |
| status | enum `untested`,`connected`,`error` | |
| last_error | string nullable | |
| last_tested_at | timestamp nullable | |

Pivot **automation_provider_channel_defaults**: `(channel_id, message_channel)` primary key → `provider_connection_id` (FR-016).

### automation_whatsapp_templates
`id, provider_connection_id FK cascade, external_id, name, language string(10), category enum(marketing, utility, authentication), status enum(approved, pending, rejected, paused, disabled), components json, variable_count tinyint, synced_at`. Unique `(provider_connection_id, name, language)`.

### automation_abandoned_carts — *Abandoned Cart Record*

| Column | Type | Notes |
|---|---|---|
| id | increments | |
| cart_id | FK cart cascade, unique | |
| contact_id | FK automation_contacts | |
| channel_id | FK channels | |
| detected_at | timestamp | |
| status | enum `abandoned`,`reopened`,`recovered`,`expired` | |
| restore_token_hash | char(64) unique | SHA-256 of the HMAC-derived token (plain token never stored) |
| restore_token_expires_at | timestamp | default +7 days |
| restored_cart_id | int FK cart nullable, `nullOnDelete` | guest cart created when a customer-owned cart is restored without login; checked when an order is placed |
| recovered_order_id | FK orders nullable | |
| cart_snapshot | json | grand_total, items_count at detection (reporting) |

Transitions: `abandoned → reopened` (cart edited; re-detected later as new `abandoned` with new trigger_key) · `abandoned|reopened → recovered` (order placed) · `abandoned → expired` (token expiry, with no order).

### automation_back_in_stock_subscriptions
`id, contact_id FK, product_id FK products cascade, channel_id, status enum(waiting, notified, cancelled), notified_at nullable`. Unique `(contact_id, product_id, channel_id)`.

### automation_attributions — *Attribution*
`id, order_id FK orders cascade UNIQUE, flow_id, step_key, message_log_id nullable (nullOnDelete), channel_id, model enum(click, delivered), revenue decimal(12,4) (base_grand_total), currency, attributed_at`. One per order (SC-007).

### automation_step_stats — *Daily rollup*
`id bigIncrements, date date, flow_id, step_key string(20) (`_flow` for flow-level), channel_id, metric enum(enrolled, sent, delivered, opened, clicked, skipped, failed, unsubscribed, converted), skip_reason string(50) default '', count unsigned int, revenue decimal(14,4) default 0`. Unique `(date, flow_id, step_key, channel_id, metric, skip_reason)`, updated with `upsert` increments. Never purged.

### Configuration (core_config via `system.php`, channel-scoped)
`marketing_automation.general.enabled`, `.email.sender_name`, `.email.sender_address` (FR-013), `.sending.quiet_hours_enabled|start|end`, `.sending.cap_count` (default 2), `.sending.cap_window_hours` (24), `.abandoned_cart.restore_link_days` (7), `.attribution.click_window_hours` (120), `.attribution.delivered_window_hours` (24), `.retention.log_months` (12), `.routing.{flow_type}` ∈ `native|klaviyo|disabled` (default `native`).

---

## Klaviyo module

### klaviyo_connections
`id, channel_id FK unique, api_key text encrypted, public_key string nullable, status enum(connected, error, disconnected), last_error, list_id string nullable (email list for subscriptions), sms_list_id nullable, consent_cursor timestamp nullable, unsubscribed_metric_ids json nullable, connected_at`.

### klaviyo_outbox
`id bigIncrements, connection_id FK cascade, kind enum(profile, event, catalog_item, catalog_delete, subscription), unique_id string(191), payload json, status enum(pending, sending, sent, failed), attempts tinyint, available_at timestamp, last_error text nullable, sent_at nullable`. Unique `(connection_id, kind, unique_id)` (no duplicates). Index `(status, available_at)`. Sent rows are purged after 30 days.

### klaviyo_sync_jobs
`id, connection_id FK, entity enum(customers, orders, catalog), status enum(queued, running, completed, failed), cursor_id unsigned int default 0, processed unsigned int, total unsigned int, started_at, finished_at, last_error`. Shown as progress (FR-035).

**Event mapping** (FR-034): `Viewed Product` ← storefront JS beacon (`navigator.sendBeacon`) to the uncached route `POST klaviyo/track/viewed-product`, because product pages are response-cached and server-side view hooks don't run on cache hits; `Added to Cart` ← `checkout.cart.add.after`; `Started Checkout` ← `checkout.load.index`; `Placed Order` + `Ordered Product`×N ← `checkout.order.save.after`; `Fulfilled Order` ← order status `completed`; `Cancelled Order` ← `sales.order.cancel.after`; `Refunded Order` ← `sales.refund.save.after`. `unique_id` pattern: `{channel_code}:{metric_slug}:{entity_id}[:{line_id}]`.
