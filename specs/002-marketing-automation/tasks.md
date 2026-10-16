---
description: "Executable task list for 002-marketing-automation"
---

# Tasks: Marketing Automation (Email, SMS & WhatsApp Flows + Klaviyo Connector)

**Input**: `/var/www/html/bagisto/specs/002-marketing-automation/` — plan.md, spec.md, research.md (R1–R19), data-model.md, contracts/*.md, quickstart.md
**Tests**: REQUIRED. Project rule (CLAUDE.md): every change must be programmatically tested with Pest. Each story phase writes its tests first, then implementation, then runs them.
**Organization**: Grouped by user story. Story phases are ordered by dependency: US3 (providers) → US2 (engine) → US1 (abandoned cart) → US4 (consent) → US5 → US6 → US7. US1–US4 are all P1.

## Format: `- [ ] T### [P?] [US#?] Description`

- **[P]**: Can run in parallel. It touches different files and doesn't depend on any unfinished task in the same phase.
- **[US#]**: User story from spec.md. Setup, Foundational and Polish tasks have no story label.

---

## 0. READ FIRST — Implementer Conventions (apply to EVERY task)

These rules are implied by every task. Where a task says "per conventions", it means this section.

**Paths and namespaces**
- `MA` = `packages/Webkul/MarketingAutomation` → namespace `Webkul\MarketingAutomation\` → `MA/src/`
- `KL` = `packages/Webkul/Klaviyo` → namespace `Webkul\Klaviyo\` → `KL/src/`
- Tests: `MA/tests/Feature/*Test.php`, `MA/tests/Unit/*Test.php`, `KL/tests/Feature/*Test.php`
- Table prefix: `automation_` (MA) and `klaviyo_` (KL). Never modify core Bagisto tables or core package code. The only exceptions are the registration files listed in Phase 1 and the Amacommerce theme views (our own package).

**Copy these sibling files for structure (read them before writing the equivalent)**
| You are writing | Mirror this existing file |
|---|---|
| Model contract (empty interface) | `packages/Webkul/Marketing/src/Contracts/Event.php` |
| Model | `packages/Webkul/Marketing/src/Models/Campaign.php` (with `HasFactory` + `newFactory()`) |
| Model proxy | `packages/Webkul/Marketing/src/Models/EventProxy.php` |
| Repository | `packages/Webkul/Marketing/src/Repositories/EventRepository.php` (extends `Webkul\Core\Eloquent\Repository`, `model()` returns the **Contract** FQCN string) |
| Factory | `packages/Webkul/Marketing/src/Database/Factories/EventFactory.php` |
| Concord module provider | `packages/Webkul/Marketing/src/Providers/ModuleServiceProvider.php` |
| Package service provider | `packages/Webkul/Marketing/src/Providers/MarketingServiceProvider.php` + config merge from `packages/Webkul/Admin/src/Providers/AdminServiceProvider.php::registerConfig()` |
| Admin DataGrid | `packages/Webkul/Admin/src/DataGrids/Marketing/Communications/EventDataGrid.php` |
| Admin route file | `packages/Webkul/Admin/src/Routes/marketing-routes.php` |
| Admin index page (Blade + DataGrid + Vue) | `packages/Webkul/Admin/src/Resources/views/marketing/communications/events/index.blade.php` (uses `<x-admin::layouts>`) |
| Admin config section (system.php) | `packages/Webkul/Admin/src/Config/system.php` (entries with `key`, `name`, `info`, `sort`, `fields[]`, `channel_based`) |
| Storefront view hook listener | `packages/Webkul/SocialShare/src/Providers/EventServiceProvider.php` (`Event::listen('bagisto.shop....', fn ($viewRenderEventManager) => $viewRenderEventManager->addTemplate('view::name'))`) |
| Admin feature test | any file in `packages/Webkul/Admin/tests/Feature/` (uses `$this->loginAsAdmin()`) |
| Shop feature test | any file in `packages/Webkul/Shop/tests/Feature/` |

**PHP rules**
- Use `declare` style like the siblings (no `declare(strict_types=1)` unless siblings use it). Use constructor property promotion, explicit return types and param types, PHPDoc with array shapes, and curly braces always.
- Enums: PHP backed string enums in `MA/src/Enums/`, TitleCase case names (e.g. `case Active = 'active';`).
- Models: use the `casts()` method (not `$casts`) for new models. JSON columns cast to `array`. Credentials cast to `encrypted:array`.
- Every model has: Contract, Model, Proxy, Repository, Factory. Register the model in that module's `ModuleServiceProvider::$models`.
- Container bindings: use `$this->app->scoped()` or `bind()`. **Never** `singleton()` for anything holding request, config or connection state (Octane rule).
- Migrations: `packages/.../src/Database/Migrations/2026_10_08_0000NN_create_<table>_table.php`, anonymous class style like siblings. FKs to core tables use `unsignedInteger` (core ids are `increments`). Add the indexes exactly as listed in `data-model.md`.
- Translations: every user-facing string goes through `trans('marketing_automation::app.<path>')`. Add keys to BOTH `MA/src/Resources/lang/en/app.php` and `MA/src/Resources/lang/ar/app.php`. Arabic can be a reasonable translation; if unsure, copy the English text. Klaviyo uses the `klaviyo::app.` namespace.
- Config reads: `core()->getConfigData('marketing_automation.<group>.<section>.<field>', $channelCode)`.
- HTTP calls to providers and Klaviyo: `Illuminate\Support\Facades\Http` only, with `->timeout(15)->connectTimeout(5)`. No vendor SDKs.
- After editing PHP files run `vendor/bin/pint --dirty --format agent`.

**Test rules**
- Pest 3 syntax (`it('...', function () { ... });`). Test files bind automatically through `tests/Pest.php` (T008). DB is wrapped in `DatabaseTransactions` by `Tests\TestCase`.
- Fake all HTTP with `Http::fake([...])` and load JSON fixtures from `MA/tests/Fixtures/Providers/{driver}/*.json`. Never call real APIs.
- Control time with `$this->travelTo(now()->setTime(...))`. Use `Queue::fake()` only when asserting dispatch; otherwise let jobs run (the phpunit queue driver is `sync`, so check `phpunit.xml`).
- Run only the affected tests: `php artisan test --compact packages/Webkul/MarketingAutomation/tests/Feature/<File>Test.php`.
- **A task that says "test" is done only when the test file exists AND passes.** Implementation tasks are done when their phase's tests pass.

**Glossary (use these exact names in code)**
Contact, Consent, ConsentEvent, Flow, FlowVersion, Enrolment, EnrolmentEvent, MessageLog, MessageEvent, ProviderConnection, WhatsAppTemplate, AbandonedCart, BackInStockSubscription, Attribution, StepStat. "Message channel" means email/sms/whatsapp (`MessageChannel` enum). "Sales channel" means a Bagisto `channels` row (`channel_id`).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create the empty module so it boots, autoloads, migrates and runs tests.

- [X] T001 Run `composer require giggsey/libphonenumber-for-php-lite` from `/var/www/html/bagisto` (approved, research R8). Verify `composer.json` lists it under `require`.
- [X] T002 Create the directory skeleton (empty `.gitkeep` where a dir would otherwise be empty): `packages/Webkul/MarketingAutomation/src/{Config,Console/Commands,Contracts/Providers,Database/Migrations,Database/Factories,Database/Seeders,DataGrids,DTOs,Engine/Steps,Enums,Http/Controllers/Admin,Http/Controllers/Shop,Http/Requests,Jobs,Listeners,Mail,Models,Providers,Repositories,Rendering,Services/Providers,Routes,Resources/views/admin,Resources/views/shop,Resources/views/emails/blocks,Resources/lang/en,Resources/lang/ar}` and `packages/Webkul/MarketingAutomation/tests/{Feature,Unit,Fixtures/Providers}`.
- [X] T003 Add PSR-4 entries to `/var/www/html/bagisto/composer.json` `autoload.psr-4`: `"Webkul\\MarketingAutomation\\": "packages/Webkul/MarketingAutomation/src"`. Add to `autoload-dev.psr-4` (create the key if missing; keep existing entries): `"Webkul\\MarketingAutomation\\Tests\\": "packages/Webkul/MarketingAutomation/tests"`. Run `composer dump-autoload`.
- [X] T004 Create `packages/Webkul/MarketingAutomation/src/Providers/ModuleServiceProvider.php` extending `Webkul\Core\Providers\CoreModuleServiceProvider` with an empty `protected $models = [];` (filled in later tasks). Register it in `/var/www/html/bagisto/config/concord.php` `modules` array after `\Webkul\Marketing\Providers\ModuleServiceProvider::class`.
- [X] T005 Create `packages/Webkul/MarketingAutomation/src/Providers/MarketingAutomationServiceProvider.php`.
  - `register()`: `mergeConfigFrom` `Config/menu.php` → `menu.admin`, `Config/acl.php` → `acl`, `Config/system.php` → `core`, `Config/automation-providers.php` → `automation-providers`. Register console commands when `runningInConsole()`, starting with an empty array.
  - `boot()`: `loadMigrationsFrom(__DIR__.'/../Database/Migrations')`; `loadTranslationsFrom(__DIR__.'/../Resources/lang', 'marketing_automation')`; `loadViewsFrom(__DIR__.'/../Resources/views', 'marketing_automation')`; admin routes via `Route::middleware(['web', 'admin', \Webkul\Core\Http\Middleware\NoCacheMiddleware::class])->prefix(config('app.admin_url'))->group(__DIR__.'/../Routes/admin-routes.php')`; shop routes via `Route::middleware(['web', 'shop'])->group(__DIR__.'/../Routes/shop-routes.php')`; public routes via `Route::middleware(['web'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(__DIR__.'/../Routes/public-routes.php')`; `$this->app->register(EventServiceProvider::class)`; schedule placeholder via `callAfterResolving(Schedule::class, fn (Schedule $schedule) => null)`.
  - Register in `/var/www/html/bagisto/bootstrap/providers.php` after `MarketingServiceProvider::class` (add the `use` import).
- [X] T006 [P] Create `packages/Webkul/MarketingAutomation/src/Providers/EventServiceProvider.php` extending `Illuminate\Foundation\Support\Providers\EventServiceProvider` with an empty `protected $listen = [];` and a `boot()` that calls `parent::boot()`. Use this file for every listener later.
- [X] T007 [P] Create the route files `packages/Webkul/MarketingAutomation/src/Routes/admin-routes.php`, `shop-routes.php`, `public-routes.php`, each containing only `<?php use Illuminate\Support\Facades\Route;`, and empty config files `packages/Webkul/MarketingAutomation/src/Config/{menu.php,acl.php,system.php,automation-providers.php}` returning `[]`. Also create `packages/Webkul/MarketingAutomation/src/Resources/lang/en/app.php` and `ar/app.php` returning `[]`.
- [X] T008 Create `packages/Webkul/MarketingAutomation/tests/MarketingAutomationTestCase.php`: namespace `Webkul\MarketingAutomation\Tests`, `class MarketingAutomationTestCase extends Tests\TestCase { use \Webkul\Admin\Tests\Concerns\AdminTestBench, \Webkul\Core\Tests\Concerns\CoreAssertions; }`. Add `uses(Webkul\MarketingAutomation\Tests\MarketingAutomationTestCase::class)->in('../packages/Webkul/MarketingAutomation/tests');` to `/var/www/html/bagisto/tests/Pest.php`. Add two testsuites to `/var/www/html/bagisto/phpunit.xml` inside `<testsuites>`: `MarketingAutomation Feature Test` → `packages/Webkul/MarketingAutomation/tests/Feature`, and `MarketingAutomation Unit Test` → `packages/Webkul/MarketingAutomation/tests/Unit`.
- [X] T009 Create the smoke test `packages/Webkul/MarketingAutomation/tests/Feature/ModuleBootTest.php`: assert `app()->getProviders(MarketingAutomationServiceProvider::class)` is non-empty, and `trans('marketing_automation::app.test')` returns a string. Run it with `php artisan test --compact packages/Webkul/MarketingAutomation/tests/Feature/ModuleBootTest.php` and make it pass.

**Checkpoint**: `php artisan migrate` runs clean, `php artisan about` boots, and the smoke test passes.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Enums, DTOs, core tables and models, contact/consent services, phone normalization, the variable renderer, the provider abstraction, ACL/menu/config skeleton, install command and health check. **No user story can start until this phase is done.**

### Enums & DTOs

- [X] T010 [P] Create backed string enums in `packages/Webkul/MarketingAutomation/src/Enums/`:
  - `MessageChannel` (Email='email', Sms='sms', Whatsapp='whatsapp')
  - `ConsentStatus` (Granted, Revoked, None)
  - `ConsentSource` (Registration='registration', Checkout='checkout', Account='account', UnsubscribeLink='unsubscribe_link', SmsStop='sms_stop', WhatsappOptout='whatsapp_optout', Admin='admin', Klaviyo='klaviyo', MigratedNewsletter='migrated_newsletter', Newsletter='newsletter')
  - `FlowStatus` (Draft, Active, Paused)
  - `FlowType` (Marketing, Transactional)
  - `TriggerType` (CartAbandoned='cart_abandoned', CustomerRegistered='customer_registered', NewsletterSubscribed='newsletter_subscribed', OrderPlaced='order_placed', OrderStatusChanged='order_status_changed', OrderShipped='order_shipped', OrderCompleted='order_completed', BackInStock='back_in_stock', CustomerInactive='customer_inactive', CustomerBirthday='customer_birthday')
  - `StepType` (Wait='wait', Condition='condition', SendEmail='send_email', SendSms='send_sms', SendWhatsapp='send_whatsapp', Exit='exit')
  - `EnrolmentStatus` (Active, Completed, Converted, Exited, Cancelled)
  - `JourneyEvent` (Entered, Waited, ConditionYes='condition_yes', ConditionNo='condition_no', Sent, Skipped, Deferred, Failed, FallbackUsed='fallback_used', Exited)
  - `SkipReason` (NoConsent='no_consent', InvalidContact='invalid_contact', NoProvider='no_provider', FrequencyCap='frequency_cap', QuietHours='quiet_hours', Expired='expired', CartEmpty='cart_empty', ProductUnavailable='product_unavailable', RoutingDisabled='routing_disabled')
  - `MessageStatus` (Queued, Sent, Delivered, Read, Failed, Bounced)
  - `MessageEventType` (Sent, Delivered, Read, Opened, Clicked, Failed, Bounced, Unsubscribed, Complained)
  - `SendStatus` (Accepted='accepted', RetryableError='retryable_error', PermanentError='permanent_error')
  - `ProviderStatus` (Untested, Connected, Error)
  - `RoutingTarget` (Native, Klaviyo, Disabled)

  All values are lowercase snake_case strings matching data-model.md.
- [X] T011 [P] Create `final readonly` DTO classes in `packages/Webkul/MarketingAutomation/src/DTOs/`, with properties exactly as in `contracts/provider-driver.md` § DTOs: `OutboundMessage`, `WhatsAppTemplateCall`, `SendResult` (add static constructors `accepted(string $id)`, `retryable(string $code, string $message)`, `permanent(string $code, string $message)`), `StatusUpdate`, `InboundMessage`, `WhatsAppTemplateData` (externalId, name, language, category, status, components array, variableCount int). Also add `TriggerEvent` (TriggerType $type, int $channelId, ?int $customerId, ?string $email, ?string $phone, string $triggerKey, array $payload, ?string $locale).

### Migrations (data-model.md is the source of truth for columns and indexes)

- [X] T012 [P] Migration `packages/Webkul/MarketingAutomation/src/Database/Migrations/2026_10_08_000001_create_automation_contacts_table.php`: columns and indexes per data-model.md § automation_contacts. FKs: `channel_id` → channels cascade, `customer_id` → customers cascade (nullable).
- [X] T013 [P] Migration `2026_10_08_000002_create_automation_consents_table.php` (same dir): automation_consents and automation_consent_events per data-model.md. Use `string('channel', 20)` and `string('status', 20)` (not DB enums) so enum changes need no migration; this applies to ALL enum-like columns in this module.
- [X] T014 [P] Migration `2026_10_08_000003_create_automation_provider_connections_table.php`: automation_provider_connections, automation_provider_channel_defaults (composite primary `(channel_id, message_channel)`, `provider_connection_id` FK cascade), automation_whatsapp_templates. `credentials` is `text`. `uuid` column is unique (used in webhook URL). `webhook_secret` is `string(64)`.
- [X] T015 [P] Migration `2026_10_08_000004_create_automation_flows_table.php`: automation_flows, automation_flow_channels (pivot, composite primary), automation_flow_versions (unique `(flow_id, version)`, no `updated_at`: use `timestamp('created_at')->useCurrent()`). Add `current_version_id` as a nullable unsignedInteger with NO FK constraint (avoids circular FK), and index it. Include nullable `activated_at`, `paused_at` and `resumed_at` timestamps.
- [X] T016 [P] Migration `2026_10_08_000005_create_automation_enrolments_table.php`: automation_enrolments (`bigIncrements`) and automation_enrolment_events, with the unique `(flow_id, contact_id, trigger_key)` and indexes `(status, next_run_at)` and `(contact_id, flow_id, created_at)`.
- [X] T017 [P] Migration `2026_10_08_000006_create_automation_message_logs_table.php`: automation_message_logs and automation_message_events, with all indexes from data-model.md.
- [X] T018 [P] Migration `2026_10_08_000007_create_automation_abandoned_carts_table.php`: automation_abandoned_carts (unique cart_id, unique restore_token_hash, nullable `restored_cart_id` FK → cart `nullOnDelete`) and automation_back_in_stock_subscriptions.
- [X] T019 [P] Migration `2026_10_08_000008_create_automation_attributions_table.php`: automation_attributions (unique order_id) and automation_step_stats (unique `(date, flow_id, step_key, channel_id, metric, skip_reason)`, with `skip_reason` defaulting to `''`).

### Models, contracts, proxies, repositories, factories

- [X] T020 Create Contract + Model + Proxy + Repository + Factory for each of: `Contact`, `Consent`, `ConsentEvent`, `Flow`, `FlowVersion`, `Enrolment`, `EnrolmentEvent`, `MessageLog`, `MessageEvent`, `ProviderConnection`, `WhatsAppTemplate`, `AbandonedCart`, `BackInStockSubscription`, `Attribution`, `StepStat`. Files go in `packages/Webkul/MarketingAutomation/src/{Contracts,Models,Repositories,Database/Factories}/`. Register all 15 models in `packages/Webkul/MarketingAutomation/src/Providers/ModuleServiceProvider.php`. Requirements:
  - `$table` set explicitly (e.g. `automation_contacts`). `$guarded = ['id']` is acceptable for these internal models.
  - `casts()`: JSON columns → `array`, enum columns → their enum class from T010, timestamps → `datetime`, `ProviderConnection::credentials` → `encrypted:array`, `MessageLog` booleans → `boolean`.
  - Relationships use `*Proxy::modelClass()`, as in `Campaign.php`:
    - Contact `hasMany` consents/enrolments, `belongsTo` customer (`Webkul\Customer\Models\CustomerProxy`) and channel (`Webkul\Core\Models\ChannelProxy`)
    - Flow `hasMany` versions/enrolments, `belongsToMany` channels via `automation_flow_channels`, `belongsTo` currentVersion
    - Enrolment `belongsTo` flow/flowVersion/contact, `hasMany` events
    - MessageLog `hasMany` events
  - `Contact` and `ProviderConnection` and `MessageLog` auto-fill `uuid` in a `creating` model event (`Str::uuid()`). `ProviderConnection` also fills `webhook_secret` with `Str::random(40)`.
  - `FlowVersion` sets `const UPDATED_AT = null;` and throws `LogicException` in an `updating` event (immutable).
  - Factories produce valid rows (use `Webkul\Core\Models\Channel::query()->first()` for channel_id; create customers with `Webkul\Customer\Models\Customer::factory()`). Add factory states: `Flow::factory()->active()`, `->transactional()`; `Consent::factory()->granted(MessageChannel $c)`; `ProviderConnection::factory()->sms('twilio')`, `->whatsapp('meta_cloud')`, `->connected()`.
- [X] T021 Unit test `packages/Webkul/MarketingAutomation/tests/Feature/ModelsTest.php`: for each factory, `create()` succeeds. `ProviderConnection` credentials are stored encrypted (raw DB value via `DB::table(...)->value('credentials')` is not the plain JSON). Updating a `FlowVersion` throws. Run and pass.

### Core services

- [X] T022 [P] Create `packages/Webkul/MarketingAutomation/src/Services/PhoneNormalizer.php` with `normalize(?string $raw, ?string $countryCode): ?string`. It returns E.164 (`+9665…`) using `libphonenumber\PhoneNumberUtil::getInstance()`, or `null` when empty, unparsable or `!isValidNumber`. The default country comes from `$countryCode ?? core()->getConfigData('sales.shipping.origin.country') ?? 'SA'`. Write `packages/Webkul/MarketingAutomation/tests/Unit/PhoneNormalizerTest.php` with a dataset:
  - `'0501234567','SA'` → `'+966501234567'`
  - `'+20 100 123 4567',null` → `'+201001234567'`
  - `'00971501234567','AE'` → `'+971501234567'`
  - `'123','SA'` → `null`
  - `'',null` → `null`

  (Unit tests that call `core()` must live in `tests/Feature`; keep this one pure by passing the country explicitly.)
- [X] T023 Create `packages/Webkul/MarketingAutomation/src/Services/ContactResolver.php`:
  - `resolve(int $channelId, ?int $customerId, ?string $email, ?string $phone, ?string $country = null, ?string $locale = null, array $names = []): Contact`. It looks up by customer_id, then lower-cased email, then normalized phone, within the channel. It creates the contact if not found, and fills any missing email/phone/names/locale/country on an existing match. When customer_id is given it pulls email/phone/first/last name from the `customers` row if those args are null. When locale is null it uses `app()->getLocale()`.
  - `forCustomer(Customer $customer, int $channelId): Contact`.
  - `merge(Contact $guest, Contact $customerContact): Contact`. For each message channel, keep the consent row with the later `changed_at`. Move consent_events, enrolments (when the target already has an enrolment with the same `(flow_id, trigger_key)`, cancel the guest's with `exit_reason='merged'`), abandoned carts and back-in-stock subscriptions to the customer contact, then delete the guest contact. Wrap it in `DB::transaction`.
  - Bind it with `bind()` (not singleton).
- [X] T024 Create `packages/Webkul/MarketingAutomation/src/Services/ConsentService.php`:
  - `grant(Contact $c, MessageChannel $ch, ConsentSource $src, ?Request $req = null): Consent`
  - `revoke(...)` with the same signature
  - `status(Contact $c, MessageChannel $ch): ConsentStatus` (missing row → `None`)
  - `hasMarketingConsent(Contact $c, MessageChannel $ch): bool` (true only when Granted)

  Each grant or revoke upserts `automation_consents` and inserts an `automation_consent_events` row (ip and user agent from the request when given). It then dispatches `Event::dispatch('marketing_automation.consent.changed', [$consent, $event])`. If the status is unchanged it still writes the event but does not dispatch. When revoking email, also set `subscribers_list.is_subscribed = 0` for that email + channel (newsletter sync, research R12).
- [X] T025 Feature test `packages/Webkul/MarketingAutomation/tests/Feature/ContactConsentTest.php`:
  - resolve creates, then finds the same contact by customer_id, by email (case-insensitive) and by phone (`'0501234567'` and `'+966501234567'` are the same contact)
  - merge keeps the latest consent and moves enrolments
  - grant then revoke writes 2 events, the status is Revoked, and the `marketing_automation.consent.changed` event is dispatched twice (`Event::fake([...])` for that event only)
  - revoking email unsubscribes the `subscribers_list` row

  Run and pass.
- [X] T026 [P] Create `packages/Webkul/MarketingAutomation/src/Rendering/TemplateRenderer.php` (research R10):
  - `render(string $template, array $context): string` replaces tokens matching `/\{\{\s*([a-z_]+(?:\.[a-z_]+)*)\s*(?:\|\s*default:\s*"([^"]*)")?\s*\}\}/i`. The value is looked up with `data_get($context, $path)`. If it's null or an empty string, use the default text; if there's no default, use an empty string. Arrays and objects render as an empty string (blocks handle lists).
  - Output is NOT HTML-escaped by this class. Callers escape for email with `e()` before inserting into HTML (EmailBlockRenderer does this).
  - Must never call `eval`, `Blade::render`, or `view()` on merchant input.
  - `variables(): array` returns the whitelist of documented paths: `customer.first_name, customer.last_name, customer.email, store.name, store.url, cart.total, cart.items_count, cart.restore_url, order.increment_id, order.total, order.tracking_url, order.review_url, coupon.code, unsubscribe_url`.

  Unit test `packages/Webkul/MarketingAutomation/tests/Unit/TemplateRendererTest.php`:
  - `'Hi {{ customer.first_name | default: "there" }}'` with first_name `'Sara'` → `'Hi Sara'`; with it missing → `'Hi there'`
  - an unknown token → `''`
  - `'{{ customer.first_name }}'` with value `'<b>'` stays `'<b>'` (escaping is the caller's job)
  - a string containing `@php` / `{!! !!}` is returned unchanged except for tokens

  Run and pass.
- [X] T027 Create `packages/Webkul/MarketingAutomation/src/Rendering/ContextBuilder.php` with `forEnrolment(Enrolment $e): array` and `forSample(int $channelId, string $locale): array`. It builds the `$context` array used by TemplateRenderer and block renderers, with keys `customer` (first_name, last_name, email), `store` (name from `core()->getCurrentChannel()` / the channel's name, url via `route('shop.home.index')`), `cart` (only when trigger_payload has cart_id: total formatted via `core()->formatPrice($cart->grand_total, $cart->cart_currency_code)`, items_count, items array of `{name, qty, price_formatted, image_url, url}`), `order` (when order_id: increment_id, total formatted, items array, tracking_url = `route('shop.customers.account.orders.view', $id)`, review_url = first product URL + `#review`), `locale`, `direction` (from the `locales` table `direction` column for the locale code, default `ltr`). Leave placeholders `cart.restore_url`, `coupon.code` and `unsubscribe_url` to be filled by later tasks (T091, T101, T110). Sample data uses fixed fake values ("Sara", a 2-item cart, order #1001).

### Provider abstraction

- [X] T028 [P] Create interfaces in `packages/Webkul/MarketingAutomation/src/Contracts/Providers/`: `MessagingProvider.php`, `SupportsInboundOptOut.php`, `WhatsAppTemplateSource.php`. Method signatures must match `contracts/provider-driver.md` exactly.
- [X] T029 Create `packages/Webkul/MarketingAutomation/src/Services/ProviderManager.php`:
  - `driver(ProviderConnection $c): MessagingProvider` resolves the class `config("automation-providers.drivers.{$c->driver}.class")` via `app()->make()`. It throws `InvalidArgumentException` for an unknown driver.
  - `defaultFor(int $channelId, MessageChannel $ch): ?ProviderConnection` reads `automation_provider_channel_defaults`.
  - `driversFor(MessageChannel $ch): array` returns `[{driver, label, fields}]` from config + `Class::fields()`.

  Register with `$this->app->scoped(ProviderManager::class)` in `MarketingAutomationServiceProvider::register()`. Fill `packages/Webkul/MarketingAutomation/src/Config/automation-providers.php` with `['drivers' => [ 'twilio' => ['class' => \Webkul\MarketingAutomation\Services\Providers\TwilioSmsProvider::class, 'channel' => 'sms', 'label' => 'Twilio'], ... ]]` for all 8 drivers in `contracts/provider-driver.md` (class names: `TwilioSmsProvider, VonageSmsProvider, MessageBirdSmsProvider, UnifonicSmsProvider, MsegatSmsProvider, TaqnyatSmsProvider, MetaCloudWhatsAppProvider, TwilioWhatsAppProvider`). Driver classes are created in Phase 3; config entries may reference not-yet-existing classes.
- [X] T030 Create `packages/Webkul/MarketingAutomation/src/Services/Providers/AbstractHttpProvider.php`, an abstract class implementing `MessagingProvider` with:
  - `protected function classify(\Illuminate\Http\Client\Response|\Throwable $r, callable $extractError): SendResult`. Rules from `contracts/provider-driver.md` § Error classification: `ConnectionException` / 429 / >=500 → retryable; 401/403/other 4xx → permanent. `$extractError($response)` returns `[code, message]`.
  - `protected function http(): PendingRequest` returns `Http::timeout(15)->connectTimeout(5)->acceptJson()`.
  - `testConnection()` that calls `send()` with body `trans('marketing_automation::app.providers.test-message')`.
  - Default `verifyWebhook()` that compares `$request->query('s')` to `$connection->webhook_secret` with `hash_equals`.
  - Default `parseStatusWebhook()` that returns `[]`.

### Admin config, ACL, menu, translations

- [X] T031 [P] Fill `packages/Webkul/MarketingAutomation/src/Config/acl.php` with the 5 ACL entries from `contracts/admin-http.md` § ACL keys. Format each entry like `packages/Webkul/Admin/src/Config/acl.php`: `key`, `name` (trans key `marketing_automation::app.acl.<x>`), `route` (a representative route name), `sort`. Fill `packages/Webkul/MarketingAutomation/src/Config/menu.php` with children under `marketing`:
  - `marketing.automation` (name `marketing_automation::app.menu.automation`, route `admin.marketing.automation.flows.index`, sort 5)
  - `marketing.automation.flows` (name `marketing_automation::app.menu.flows`, route `admin.marketing.automation.flows.index`)
  - `marketing.automation.settings` (name `marketing_automation::app.menu.providers`, route `admin.marketing.automation.providers.index`)

  Bagisto shows a menu item only if the admin has an ACL permission with the **same key** (`packages/Webkul/Core/src/Menu.php:58`), so every menu key must be one of the ACL keys above. There's no separate health menu entry: the health page is a tab on the providers page (T052 links to `admin.marketing.automation.health.index`).

  Menu entries without existing routes will 404 until later tasks; that is expected.
- [X] T032 [P] Fill `packages/Webkul/MarketingAutomation/src/Config/system.php` with configuration sections (format like `Admin/src/Config/system.php`). Group key `marketing_automation` (name `marketing_automation::app.configuration.title`, sort 7). Sections and fields (all `channel_based => true` unless noted):
  - `marketing_automation.general.settings`: `enabled` (boolean, default 1)
  - `marketing_automation.email.sender`: `sender_name` (text), `sender_address` (text, validation `email`)
  - `marketing_automation.sending.limits`: `quiet_hours_enabled` (boolean, 0), `quiet_hours_start` (text, `22:00`), `quiet_hours_end` (text, `09:00`), `cap_count` (number, 2), `cap_window_hours` (number, 24)
  - `marketing_automation.abandoned_cart.settings`: `restore_link_days` (number, 7)
  - `marketing_automation.attribution.settings`: `click_window_hours` (number, 120), `delivered_window_hours` (number, 24)
  - `marketing_automation.retention.settings`: `log_months` (number, 12, not channel_based)
  - `marketing_automation.routing.settings`: one select field per flow template key (`abandoned_cart, welcome, post_purchase, win_back, order_status, back_in_stock`) with options native/klaviyo/disabled, default `native`

  Add all trans keys to en/ar `app.php`.
- [X] T033 Create `packages/Webkul/MarketingAutomation/src/Services/Settings.php` with typed getters that read the config above for a channel code: `quietHours(string $channelCode): ?array{start: string, end: string}`, `cap(string $channelCode): array{count: int, window_hours: int}`, `restoreLinkDays(...)`, `clickWindowHours(...)`, `deliveredWindowHours(...)`, `retentionMonths(): int`, `routing(string $channelCode, string $flowTemplateKey): RoutingTarget`, `timezone(Channel $channel): string` (`$channel->timezone ?: config('app.timezone')`), `senderName/senderAddress(...)` falling back to `config('mail.from.*')`.

### Install command & health

- [X] T034 Create `packages/Webkul/MarketingAutomation/src/Console/Commands/InstallCommand.php` (`automation:install`). It runs the `FlowTemplateSeeder` (it can be a no-op class until T084), then migrates newsletter consent:
  - Iterate `subscribers_list` where `is_subscribed = 1` in chunks of 500.
  - For each row, `ContactResolver::resolve(channel_id, customer_id, email)`, then `ConsentService::grant(contact, Email, MigratedNewsletter)`, but only if the status is `None`, so the command is idempotent.
  - Print the count.

  Register it in the service provider's commands. Feature test `packages/Webkul/MarketingAutomation/tests/Feature/InstallCommandTest.php`: 2 subscribed + 1 unsubscribed rows give exactly 2 granted email consents with source `migrated_newsletter` and zero sms/whatsapp consents. A second run creates no new consent events. Run and pass.
- [X] T035 Create `packages/Webkul/MarketingAutomation/src/Services/HealthCheck.php` with `report(): array{queue_driver: string, queue_ok: bool, scheduler_last_seen_at: ?string, scheduler_ok: bool, mail_configured: bool}`. `queue_ok` = `config('queue.default') !== 'sync'`. `scheduler_ok` = cache key `marketing_automation.scheduler_heartbeat` is under 5 minutes old. `mail_configured` = `config('mail.default')` not in `['log','array']`. Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Admin/HealthController.php` with `index()` returning view `marketing_automation::admin.health.index`, which shows the 3 checks with green/red badges and fix hints. Add route `GET marketing/automation/health` named `admin.marketing.automation.health.index` in `admin-routes.php`, guarded in the controller with `bouncer()->hasPermission('marketing.automation.settings')` (abort 401 like siblings do). Feature test `packages/Webkul/MarketingAutomation/tests/Feature/HealthCheckTest.php`: `config(['queue.default' => 'sync'])` gives `queue_ok` false. An admin GET returns 200 and contains the queue warning text. Run and pass.

**Checkpoint**: all Phase 2 tests pass, `php artisan automation:install` runs, and the Admin → Marketing → Automation menu appears.

---

## Phase 3: User Story 3 — Configure messaging channels and providers (Priority: P1)

**Goal**: Admins add SMS and WhatsApp provider connections, test them, sync WhatsApp templates and choose defaults per sales channel.
**Independent Test**: Add a Twilio SMS and a Meta WhatsApp connection with faked HTTP. "Test connection" marks it connected. Bad credentials show a plain-language error. Template sync lists approved templates. GET shows masked secrets.

### Tests for US3 (write first, expect them to fail)

- [X] T036 [P] [US3] Create HTTP fixtures under `packages/Webkul/MarketingAutomation/tests/Fixtures/Providers/{driver}/` for all 8 drivers: `send_success.json`, `send_auth_error.json`, `send_invalid_number.json`, `send_rate_limited.json` (body plus a status code noted in the filename suffix or a sibling `.status` file, your choice, but be consistent), and for `meta_cloud` and `twilio_whatsapp` also `templates.json`. Also add `meta_cloud/send_outside_window.json` (`{"error":{"message":"(#131047) Re-engagement message","code":131047}}`, HTTP 400). Use realistic shapes from each vendor's public docs (e.g. Twilio success `{"sid":"SM123","status":"queued"}`, auth error `{"code":20003,"message":"Authenticate"}` with HTTP 401; Meta success `{"messages":[{"id":"wamid.X"}]}`, error `{"error":{"message":"(#131030) Recipient phone number not in allowed list","code":131030}}` with HTTP 400).
- [X] T037 [P] [US3] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/ProviderDriversTest.php`. For each driver (Pest dataset of the 8 keys), with `Http::fake()` using the fixtures:
  - success → `SendStatus::Accepted` with a non-empty provider id
  - auth error → `PermanentError` with a non-empty message
  - 429 → `RetryableError`
  - `ConnectionException` (`Http::fake(fn () => throw new ConnectionException())`) → `RetryableError`
  - Assert the request URL, auth header and body fields per `contracts/provider-driver.md` § Launch drivers using `Http::assertSent`.
  - meta_cloud: the outside-window fixture → `PermanentError`; `testConnection()` posts `type: template` with `template.name = hello_world` (check with `Http::assertSent`). twilio_whatsapp: `testConnection()` with no synced templates → `PermanentError` code `no_template`.
- [X] T038 [P] [US3] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Admin/ProviderManagementTest.php` (admin with all permissions via `loginAsAdmin()`):
  - POST `admin.marketing.automation.providers.store` creates a connection with status `untested`.
  - GET show returns credentials masked as `••••` + last 4 chars and never the full secret (`assertDontSee`).
  - PUT with an empty secret keeps the old secret.
  - POST test with the success fixture gives 200 and status `connected`; with the auth fixture, 422 with the provider message and status `error`.
  - POST sync-templates (meta_cloud) stores only templates from the fixture, with correct `status` values.
  - PUT defaults sets `automation_provider_channel_defaults`.
  - DELETE is blocked (422) while the connection is a default used by an active flow's channel.
  - An admin without `marketing.automation.settings` permission gets 401/403. Create a role with limited permissions; see sibling Admin tests for how roles are created.

### Implementation for US3

- [X] T039 [P] [US3] `packages/Webkul/MarketingAutomation/src/Services/Providers/TwilioSmsProvider.php` (extends AbstractHttpProvider, implements SupportsInboundOptOut).
  - `fields()`: account_sid (text), auth_token (password, secret), messaging_service_sid (text, optional).
  - `send`: POST form `https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json` with basic auth (sid, token), `To`, `Body`, `MessagingServiceSid` or `From` = `$connection->sender`, and `StatusCallback` = `$message->statusCallbackUrl`. The id comes from `sid`; the error from `[code, message]`.
  - `verifyWebhook`: validate `X-Twilio-Signature` = base64(HMAC-SHA1(auth_token, full URL + sorted POST params concatenated key.value)).
  - `parseStatusWebhook`: map `MessageStatus` (queued/sent → sent, delivered → delivered, read → read, failed/undelivered → failed) with `MessageSid`, `ErrorCode`.
  - `parseInbound`: when `Body` is present, return `InboundMessage(From, Body)`.
- [X] T040 [P] [US3] `packages/Webkul/MarketingAutomation/src/Services/Providers/VonageSmsProvider.php`: fields api_key, api_secret (secret). Send: POST JSON `https://rest.nexmo.com/sms/json` `{api_key, api_secret, from: sender, to: E.164 without '+', text, callback, type: 'unicode'}`. Success when `messages[0].status == "0"` (id `messages[0]['message-id']`). Status `"1"` (throttled) → retryable; others → permanent with `error-text`. Status webhook: `messageId`, `status` (delivered/failed/rejected/expired). Inbound: `msisdn`, `text`.
- [X] T041 [P] [US3] `packages/Webkul/MarketingAutomation/src/Services/Providers/MessageBirdSmsProvider.php`: fields access_key (secret). Send: POST `https://rest.messagebird.com/messages` header `Authorization: AccessKey {key}`, JSON `{originator: sender, recipients: [E.164 without '+'], body, reportUrl, datacoding: 'auto'}`. The id is `id`; errors are `errors[0].code/description`. Status webhook: query params `id`, `status`. Inbound: `originator`, `body`.
- [X] T042 [P] [US3] `packages/Webkul/MarketingAutomation/src/Services/Providers/UnifonicSmsProvider.php`: fields app_sid (secret). Send: POST form `https://el.cloud.unifonic.com/rest/SMS/messages` `{AppSid, SenderID: sender, Recipient: E.164 without '+', Body, CorrelationID: messageUuid}`. Success when `success == true`; the id is `data.MessageID`; errors are `errorCode/message`. Inbound is not supported (don't implement SupportsInboundOptOut).
- [X] T043 [P] [US3] `packages/Webkul/MarketingAutomation/src/Services/Providers/MsegatSmsProvider.php`: fields username, api_key (secret). Send: POST JSON `https://www.msegat.com/gw/sendsms.php` `{userName, apiKey, numbers: E.164 without '+', userSender: sender, msg, msgEncoding: 'UTF8'}`. Success when `code` is `"1"` or `"M0000"`; the id is the `messageId` field, or the messageUuid if absent. Any other code → permanent with `message`.
- [X] T044 [P] [US3] `packages/Webkul/MarketingAutomation/src/Services/Providers/TaqnyatSmsProvider.php`: fields bearer_token (secret). Send: POST JSON `https://api.taqnyat.sa/v1/messages` header `Authorization: Bearer {token}`, body `{recipients: [E.164 without '+'], body, sender}`. Success when `statusCode == 201`; the id is `messageId`; others → classify by HTTP status, with the message from `message`.
- [X] T045 [P] [US3] `packages/Webkul/MarketingAutomation/src/Services/Providers/MetaCloudWhatsAppProvider.php` (implements WhatsAppTemplateSource, SupportsInboundOptOut).
  - fields: access_token (secret), phone_number_id, waba_id, app_secret (secret).
  - `send`: POST `https://graph.facebook.com/v21.0/{phone_number_id}/messages` with bearer token. Body: `{messaging_product: 'whatsapp', to: E.164 without '+', type: 'template', template: {name, language: {code}, components: [{type: 'body', parameters: [{type: 'text', text: p}...]}, buttons...]}}`. If `$message->template` is null and body is set, send `type: 'text'`.
  - The id is `messages[0].id`. Errors: `error.code` 131030/131026/132000-series/100 → permanent; 4/80007/130429 (rate limit) → retryable.
  - `testConnection()` override: send template `hello_world` with language `en_US` and no parameters. If the admin passes a template name (`?template=<name>` → `$to` stays the phone; read the name from `request('template')`), send that synced approved template instead. Never send free text in a test, because Meta rejects free text outside a 24-hour conversation (error 131047).
  - `fetchTemplates`: GET `https://graph.facebook.com/v21.0/{waba_id}/message_templates?limit=200`, following `paging.next`. Map `status` (APPROVED → approved, etc.), `category` lower-case, `variable_count` = the highest `{{n}}` in the BODY component text.
  - `verifyWebhook`: `X-Hub-Signature-256` = `sha256=` + HMAC-SHA256(app_secret, raw body).
  - `parseStatusWebhook`: `entry[].changes[].value.statuses[]` → StatusUpdate(id, status, errors[0]).
  - `parseInbound`: `value.messages[]` with `text.body` or `button.text`.
- [X] T046 [P] [US3] `packages/Webkul/MarketingAutomation/src/Services/Providers/TwilioWhatsAppProvider.php` (implements WhatsAppTemplateSource, SupportsInboundOptOut).
  - fields: account_sid, auth_token (secret), and `from` held in `$connection->sender` (`whatsapp:+…`).
  - `send`: the Twilio Messages endpoint with `To: 'whatsapp:+…'`, `From: sender`, `ContentSid: template->name` (we store the Content SID as the template `external_id` and `name`), `ContentVariables: json_encode(['1' => p1, ...])`.
  - `testConnection()` override: send the first `approved` synced template of this connection. If none has been synced, return `SendResult::permanent('no_template', trans('marketing_automation::app.providers.sync-templates-first'))`.
  - `fetchTemplates`: GET `https://content.twilio.com/v1/ContentAndApprovals?PageSize=100`, mapping `approval_requests.whatsapp.status` (approved → approved) and `language`.
  - Signature, status and inbound handling are the same as T039. Extract the shared Twilio logic into a trait `packages/Webkul/MarketingAutomation/src/Services/Providers/Concerns/TwilioCommon.php`.
- [X] T047 [US3] Create `packages/Webkul/MarketingAutomation/src/Repositories/ProviderConnectionRepository.php` methods:
  - `maskedCredentials(ProviderConnection $c): array` (secret fields from `fields()` → `'••••'.substr($v, -4)`, non-secret fields as-is)
  - `updateWithSecrets(ProviderConnection $c, array $data): ProviderConnection` (empty secret inputs keep existing values)
  - `setDefault(int $channelId, MessageChannel $ch, int $connectionId): void` (upsert)
- [X] T048 [US3] Create Form Requests `packages/Webkul/MarketingAutomation/src/Http/Requests/ProviderConnectionRequest.php`. Rules: `name` required string max:100; `message_channel` in sms,whatsapp; `driver` in keys of config `automation-providers.drivers` whose channel matches; `sender` required string max:50; `credentials` array, where each `required` non-secret field is required, and secret fields are required on create only. Also `ProviderTestRequest.php` (`to` required string max:30) and `ProviderDefaultRequest.php` (`channel_id` exists:channels,id; `message_channel`; `provider_connection_id` exists:automation_provider_connections,id).
- [X] T049 [US3] Create `packages/Webkul/MarketingAutomation/src/Jobs/SyncWhatsAppTemplates.php` (ShouldQueue, queue `automation`). For a connection whose driver implements WhatsAppTemplateSource it calls `fetchTemplates`, upserts `automation_whatsapp_templates` by `(provider_connection_id, name, language)`, and marks rows absent from the response as `disabled`. The controller runs it with `dispatchSync` so the response can return the list.
- [X] T050 [US3] Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Admin/ProviderController.php` with actions `index` (DataGrid via `ProviderDataGrid` for AJAX, else view), `store`, `show` (JSON with masked credentials + `webhook_url` = `route('automation.webhooks.receive', [$c->driver, $c->uuid])` + `?s=`secret for regional drivers), `update`, `destroy` (422 with message when used as a default by an active flow's channel), `test` (calls `ProviderManager::driver($c)->testConnection($c, PhoneNormalizer::normalize($to))`, updates `status/last_error/last_tested_at`, returns 200 or 422 `{message}`), `syncTemplates`, `updateDefaults`. Every action checks `bouncer()->hasPermission('marketing.automation.settings')`. JSON response shape: `{message, data}` (contracts/admin-http.md).
- [X] T051 [US3] Add routes to `packages/Webkul/MarketingAutomation/src/Routes/admin-routes.php` under `Route::prefix('marketing/automation')`, named exactly as in `contracts/admin-http.md` § Providers: `admin.marketing.automation.providers.index|store|show|update|delete|test|sync_templates|defaults`.
- [X] T052 [US3] Create `packages/Webkul/MarketingAutomation/src/DataGrids/ProviderDataGrid.php` with columns name, message_channel, driver, sender, status (badge), last_tested_at, and actions edit/delete. Create the view `packages/Webkul/MarketingAutomation/src/Resources/views/admin/providers/index.blade.php`, mirroring the events index page:
  - `<x-admin::datagrid>`
  - a "Create provider" modal (Vue inline component `v-provider-form`) whose fields render dynamically from `drivers` passed by the controller (`ProviderManager::driversFor()` for sms and whatsapp), with `password` inputs for secret fields
  - a "Test connection" button that prompts for a phone and POSTs to the test route, showing a success or error flash
  - a "Sync templates" button for whatsapp
  - a "Defaults" section: a table of sales channels × (sms, whatsapp) selects that POSTs to the defaults route
  - a "Health" tab linking to `admin.marketing.automation.health.index` (there's no separate menu entry, see T031)

  All strings are translated.
- [X] T053 [US3] Run `php artisan test --compact packages/Webkul/MarketingAutomation/tests/Feature/ProviderDriversTest.php packages/Webkul/MarketingAutomation/tests/Feature/Admin/ProviderManagementTest.php` and fix until green. Run pint.

**Checkpoint**: US3 is independently demoable (quickstart step 1).

---

## Phase 4: User Story 2 — Build and manage custom flows (Priority: P1)

**Goal**: Versioned flow engine with all triggers and step types, fallback channels, consent enforcement, admin builder, activate/pause/resume/duplicate/delete, test send and prebuilt templates (except the abandoned-cart specifics, which are in US1).
**Independent Test**: Create a Welcome flow (registration → email → wait 2 days → condition "has not ordered" → WhatsApp). Register a customer, then use time travel and `automation:dispatch-due` to see each step run in order (spec US2 Independent Test).

### Tests for US2 (write first)

- [X] T054 [P] [US2] Unit test `packages/Webkul/MarketingAutomation/tests/Feature/Engine/FlowValidatorTest.php`. The validator returns an `errors` array keyed `steps.<key>`, or a top-level `trigger`/`steps` key. Cases:
  - no send step → error
  - a `next` pointing to a missing key → error
  - a cycle without a wait step → error
  - send_whatsapp whose `template_id` is not an `approved` template → error `steps.sX`
  - an SMS step with no default SMS provider for one of the flow's sales channels → error
  - a transactional flow containing a `coupon` or `product_grid` block → error
  - a valid graph → `[]`
- [X] T055 [P] [US2] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Engine/EnrolmentTest.php`:
  - an active flow enrols a matching TriggerEvent
  - a duplicate `trigger_key` → still one enrolment
  - a second event within the `reentry_window_hours` with a different trigger_key → not enrolled; after the window (travelTo) → enrolled
  - a draft or paused flow → not enrolled
  - a flow not scoped to the event's channel → not enrolled
  - routing set to `disabled` or `klaviyo` for the flow's template_key → not enrolled
  - events that happened before activation are not enrolled (no retroactive enrolment)
- [X] T056 [P] [US2] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Engine/StepRunnerTest.php`, using the sync queue and `Mail::fake()` and `Http::fake()` with the twilio/meta fixtures:
  - email → wait 60 min → sms: after enrolment the email is sent and `next_run_at` = +60 min; `automation:dispatch-due` before that sends nothing; after travelTo +61 min the SMS is sent and the enrolment ends `completed`
  - a condition step takes the yes/no branch based on current data (order count)
  - no SMS consent on a marketing flow → `skipped` journey event with reason `no_consent` and the flow continues
  - `fallback_channel=email` on an SMS step when there's no SMS consent but email consent → email sent, a `fallback_used` event, and `MessageLog.is_fallback = true`
  - an SMS permanent error with fallback → the fallback is sent
  - a retryable error → the job is released and retried (assert attempts increments; simulate with `Http::fakeSequence()` 429 then success)
  - an invalid phone → `skipped` with `invalid_contact`
  - a transactional flow sends without marketing consent
  - an exit step → `completed`
  - with `marketing_automation.email.sender.sender_address` set for the channel, the sent mail's From address equals it; when unset, it equals `config('mail.from.address')` (FR-013)
- [X] T057 [P] [US2] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Engine/FlowLifecycleTest.php`:
  - pause → `automation:dispatch-due` sends nothing for due enrolments
  - resume at < 24 h overdue → sent; resume at > 24 h overdue → that step gets `skipped` reason `expired` and the flow moves to the next step
  - editing an active flow's draft and re-activating → version 2; existing enrolments keep `flow_version_id` = v1, and new ones get v2 (FR-007)
  - duplicate creates a draft copy named "<name> (copy)"
  - delete cancels active enrolments with `exit_reason=flow_deleted`
- [X] T058 [P] [US2] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Engine/TriggersTest.php`. For each trigger, fire the real Bagisto event or run the scan command and assert one enrolment with the expected `trigger_key`:
  - `customer.registration.after` → `customer:{id}`
  - `customer.subscription.after` → `subscribe:{subscriber_id}`
  - `checkout.order.save.after` → `order:{id}`
  - `sales.order.update` with status change → `order-status:{id}:{status}`
  - `sales.shipment.save.after` → `shipment:{id}`
  - order status `completed` → `order-completed:{id}`
  - back in stock: product qty 0 → 5 with a `waiting` subscription → `bis:{subscription_id}`
  - inactive: last order 91 days ago with inactive_days=90 → `inactive:{contact_id}:{date}`
  - birthday: `date_of_birth` = today in channel tz → `birthday:{customer_id}:{year}`

  Use factories from Bagisto (`Webkul\Sales\Models\Order::factory()` etc.); read `packages/Webkul/Shop/tests/Feature/Checkout` for how orders are created in tests.
- [X] T059 [P] [US2] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Admin/FlowManagementTest.php`:
  - CRUD routes from `contracts/admin-http.md` § Flows return the documented shapes
  - activate with an invalid definition returns 422 with `errors.steps.*`
  - send test: `POST .flows.test` with an email recipient sends 1 mail (`Mail::fake`) and creates a MessageLog with `is_test = true` and `enrolment_id` null; a phone recipient on an SMS step sends via the faked provider
  - an admin with only `marketing.automation.flows` (no `.manage`) gets 401 on store, but 200 on index

### Implementation for US2 — engine

- [X] T060 [US2] Create `packages/Webkul/MarketingAutomation/src/Engine/FlowDefinition.php`, a value object wrapping the definition array (`{entry: string, steps: array<string, array>}`) with `entry(): string`, `step(string $key): array` (throws if missing), `keys(): array`, `nextOf(string $key, ?bool $branch = null): ?string` (for a condition: `yes` when branch is true, else `no`; otherwise `next`) and `hash(): string` (sha256 of the canonical json_encode with keys sorted recursively). Step keys must match `/^s\d+$/`.
- [X] T061 [US2] Create `packages/Webkul/MarketingAutomation/src/Engine/FlowValidator.php` with `validate(Flow $flow, array $definition): array<string, array<int, string>>` implementing every rule in data-model.md § Activation validation, plus the cases in T054. Cycle detection: DFS over next/yes/no edges, where a cycle is allowed only if it contains at least one `wait` step. Error messages use trans keys `marketing_automation::app.flows.validation.*`.
- [X] T062 [US2] Create `packages/Webkul/MarketingAutomation/src/Repositories/FlowRepository.php` methods:
  - `activate(Flow $f): FlowVersion` validates, throws `ValidationException::withMessages($errors)` if invalid, creates a FlowVersion with `version = max+1` when `definition_hash` differs from the current version (otherwise reuses current), and sets `status=active, activated_at=now(), current_version_id`
  - `pause(Flow $f)` sets `status=paused`, `paused_at=now()`
  - `resume(Flow $f)` sets `status=active`, `resumed_at=now()`
  - `duplicate(Flow $f): Flow` copies the draft and channels, status draft
  - `deleteFlow(Flow $f)` sets active enrolments to `cancelled`/`flow_deleted`, then deletes the flow; rollups are kept because there's no FK from step_stats

  Dispatch the domain events from `contracts/domain-events.md` (`marketing_automation.flow.activate.after`, etc.).
- [X] T063 [US2] Create `packages/Webkul/MarketingAutomation/src/Engine/EnrolmentService.php` with `enrol(TriggerEvent $e): array<int, Enrolment>`:
  1. Find active flows whose `trigger_type` = `$e->type`, which are attached to `$e->channelId` in `automation_flow_channels`, and whose `activated_at <= now()`.
  2. Filter by `trigger_config` matching (for order_status_changed: `trigger_config.status` equals `payload.status` if set; for customer_inactive: `inactive_days` equals `payload.inactive_days`).
  3. Skip if `Settings::routing(channelCode, $flow->template_key)` is not `Native` (when template_key is null, always native).
  4. Resolve the contact via ContactResolver.
  5. Re-entry check: an enrolment exists for (flow, contact) with `created_at >= now()->subHours(reentry_window_hours)` → skip.
  6. `insertOrIgnore`-style create on the unique `(flow_id, contact_id, trigger_key)` (catch `UniqueConstraintViolationException`), with `status=active`, `current_step_key = definition.entry`, `next_run_at = now()`, `flow_version_id = current_version_id`.
  7. Write an `entered` EnrolmentEvent, increment StepStat `enrolled` (T118 adds StatsRecorder; until then call a no-op `StatsRecorder::increment()` stub created here in `packages/Webkul/MarketingAutomation/src/Services/StatsRecorder.php`), and dispatch `ProcessEnrolmentStep` for the enrolment immediately.
  8. Dispatch `marketing_automation.enrolment.create.after`.
- [X] T064 [US2] Create `packages/Webkul/MarketingAutomation/src/Engine/ConditionEvaluator.php` with `evaluate(array $rule, Enrolment $e): bool`. The rule shape is `{all|any: [{field, op, value}]}` (nesting allowed). Implement every field and operator in data-model.md § Condition fields:
  - `has_ordered_since_enrolment`: orders with customer_id (or customer_email) and `created_at >= enrolment.created_at`
  - `order_count`, `lifetime_spend` (sum `base_grand_total` of non-canceled orders), `last_order_total`
  - `customer_group_id`, `locale` (contact.locale), `channel_id`, `has_consent.{ch}` (ConsentService)
  - `cart_value` (`base_grand_total` of trigger cart, or the customer's active cart)
  - `cart_contains_product` / `cart_contains_category` (`value` = id or ids; categories via `product_categories`)
  - `order_contains_*` (trigger order, or last order)

  Unit-style Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Engine/ConditionEvaluatorTest.php` with ≥1 case per field and op, plus all/any nesting. Run and pass.
- [X] T065 [US2] Create `packages/Webkul/MarketingAutomation/src/Engine/Steps/StepHandler.php` (interface) with `handle(Enrolment $e, array $step, StepContext $ctx): StepOutcome`. Create `packages/Webkul/MarketingAutomation/src/Engine/StepOutcome.php` (readonly) with static constructors `next(?string $key)`, `waitUntil(CarbonInterface $at, ?string $nextKey)` (also used for deferral: same key), `branch(bool $yes)`, `exit()`, `retry(int $delaySeconds)`. Create `StepContext.php` (readonly: Flow, FlowVersion, Contact, Channel, timezone string, context array lazily built via ContextBuilder).
- [X] T066 [P] [US2] `packages/Webkul/MarketingAutomation/src/Engine/Steps/WaitStep.php`:
  - `mode=duration` → `waitUntil(now()->addMinutes(minutes), next)`
  - `until_time` → the next occurrence of `time` (HH:MM) in the channel timezone (today if still ahead, else tomorrow), converted to UTC
  - `until_weekday` → the next date whose ISO weekday is in `weekdays[]` (1–7) at `time` (default 09:00)

  The first time the runner hits a wait it schedules; when it is re-invoked after the wait it moves on. Implement this by storing `meta.waiting_step = key` on the enrolment's last `waited` event, or simpler: WaitStep returns `waitUntil(at, next)`, and the runner sets `current_step_key = next` with `next_run_at = at`.
- [X] T067 [P] [US2] `packages/Webkul/MarketingAutomation/src/Engine/Steps/ConditionStep.php` → `branch(ConditionEvaluator::evaluate(step.rule, e))`. `packages/Webkul/MarketingAutomation/src/Engine/Steps/ExitStep.php` → `exit()`.
- [X] T068 [US2] Create `packages/Webkul/MarketingAutomation/src/Services/SendPolicy.php` with `check(Contact $c, MessageChannel $ch, FlowType $type, Channel $salesChannel): PolicyResult`. PolicyResult is readonly: `allowed: bool`, `reason: ?SkipReason`, `deferUntil: ?Carbon`, `recipient: ?string`. Order:
  1. Recipient: email → contact.email (must pass `filter_var FILTER_VALIDATE_EMAIL`); sms/whatsapp → contact.phone_e164 (null → `invalid_contact`).
  2. Marketing only: consent must be Granted, else `no_consent`.
  3. Provider (sms/whatsapp): `ProviderManager::defaultFor` must exist with status `connected`, else `no_provider`.
  4. Marketing only: quiet hours and frequency cap. Leave a clearly marked `// US4: quiet hours & cap — see T103` hook method `applyTimingRules()` that returns null for now.

  Transactional flows skip steps 2 and 4.
- [X] T069 [US2] Create the email mailable `packages/Webkul/MarketingAutomation/src/Mail/AutomationMail.php` extending `Illuminate\Mail\Mailable` (NOT queued; the job already runs on the queue). The constructor takes `string $subject, string $html, string $fromAddress, string $fromName, ?string $unsubscribeUrl`. If `unsubscribeUrl` is set, add headers `List-Unsubscribe: <url>` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` via `headers()`. Use `html` content (no view compile of merchant data).
- [X] T070 [US2] Create `packages/Webkul/MarketingAutomation/src/Rendering/MessageRenderer.php`:
  - `renderEmail(array $step, array $context, string $locale): array{subject: string, html: string}`. Pick `step.subject[$locale] ?? step.subject[default locale] ?? first`, and likewise for blocks. Support these blocks now: `text` (paragraph through TemplateRenderer + `e()` + `nl2br`), `button`, `cart_items` (rows from `context.cart.items`: image, name, qty, price), `order_summary` (`context.order` items and total) and `footer` (text + unsubscribe link for marketing). Each one renders through a view `marketing_automation::emails.blocks.{type}` in `packages/Webkul/MarketingAutomation/src/Resources/views/emails/blocks/`, following the escaping rules in T109 (only `{{ }}` echo on pre-rendered values). US5 (T109) adds header, image, product_grid and coupon, and moves this rendering into EmailBlockRenderer without changing its output. Wrap in a minimal HTML layout view `marketing_automation::emails.layout` (a Blade file we own; merchant content is passed as an already-escaped `$bodyHtml` variable echoed with `{!! !!}`) with `dir` = `context.direction`.
  - `renderText(array $step, array $context, string $locale): string` for the SMS body.
  - `renderWhatsApp(array $step, array $context, string $locale): WhatsAppTemplateCall`. Load WhatsAppTemplate `template_id` and pick the language per locale map. `bodyParams` are `step.variables` (`{1: '{{ customer.first_name }}'}`) rendered in numeric order.

  Default locale = `core()->getDefaultLocaleCodeFromDefaultChannel()` (check the method exists in `packages/Webkul/Core/src/Core.php`; else use `config('app.locale')`).
- [X] T071 [US2] Create `packages/Webkul/MarketingAutomation/src/Engine/Steps/SendStep.php`, a single handler for send_email/send_sms/send_whatsapp:
  1. Determine the channel from the step type.
  2. `SendPolicy::check`.
  3. If not allowed and `step.fallback_channel` is set: re-run the policy for the fallback channel. If allowed, send the fallback using `step.fallback_step` content (shape of the corresponding send step) and mark `is_fallback`.
  4. If still not allowed: write a `skipped` event with the reason and return `next`. If `deferUntil` is set: return `waitUntil(deferUntil, sameKey)` with a `deferred` event, unless `deferUntil > enrolment step due time + validity_hours` (default 24), in which case skip with reason `expired` (US4 frequency-cap rule).
  5. Create a MessageLog (`status=queued`, `is_marketing` from flow type, recipient, locale, `flow_id`, `step_key`, `provider_connection_id`).
  6. Dispatch `SendMessage` synchronously inside this job (`SendMessage::dispatchSync`) or call `MessageSender::send($log, $payload)` directly. Choose the direct call; create `packages/Webkul/MarketingAutomation/src/Services/MessageSender.php`.
  7. Write a `sent` event and return `next`.
- [X] T072 [US2] Create `packages/Webkul/MarketingAutomation/src/Services/MessageSender.php`:
  - `sendEmail(MessageLog $log, array $rendered): void` → `Mail::to($log->recipient)->send(new AutomationMail(...))`. Set `fromAddress = Settings::senderAddress($channel->code)` and `fromName = Settings::senderName($channel->code)`, which fall back to `config('mail.from.address')` / `config('mail.from.name')` (FR-013). On success: `status=sent, sent_at=now()`. On `Throwable`: `status=failed, error_message`.
  - `sendProvider(MessageLog $log, OutboundMessage $msg): SendResult` → `ProviderManager::driver($conn)->send($conn, $msg)`. Accepted → `status=sent`, `provider_message_id`, `sent_at`. Permanent → `status=failed`, `error_code/error_message`. Retryable → increment `attempts`, store the error and throw `RetryableSendException` (create it in `packages/Webkul/MarketingAutomation/src/Exceptions/RetryableSendException.php`).
  - `statusCallbackUrl` = `route('automation.webhooks.receive', [$conn->driver, $conn->uuid]).'?s='.$conn->webhook_secret`.
  - Both methods dispatch `marketing_automation.message.send.before` (if any listener returns `false`, mark the log `failed` with `error_code='vetoed'`) and `.send.after`.
- [X] T073 [US2] Create `packages/Webkul/MarketingAutomation/src/Engine/StepRunner.php` with `run(Enrolment $e): void`:
  - Loop: load the step at `current_step_key` from `flowVersion.definition`, resolve the handler by type, call it, and apply the outcome:
    - `next(k)`: `k` null → complete (status completed, completed_at); else set current_step_key = k and continue the loop
    - `waitUntil`: set next_run_at, current_step_key = nextKey ?? same, write a `waited`/`deferred` event, stop
    - `branch`: write a `condition_yes`/`condition_no` event, then go to `nextOf(key, bool)`
    - `exit`: status completed, write an `exited` event
  - Guard against infinite loops: at most 25 steps per run, then reschedule `next_run_at = now()`.
  - Before each step: if the flow is paused → stop without changes. If enrolment.status != active → stop.
  - Overdue rule: if the step is a send step and `flow.resumed_at` is not null and `next_run_at < flow.resumed_at` and `flow.resumed_at->diffInHours(next_run_at) > 24`, skip with reason `expired` and continue to next (US2-AS2). Never use `updated_at` for this.
  - Each step transition is saved in its own `DB::transaction`.
  - On `RetryableSendException`, bubble up so the job retries the same step (the current_step_key was not advanced).
- [X] T074 [US2] Create `packages/Webkul/MarketingAutomation/src/Jobs/ProcessEnrolmentStep.php` (ShouldQueue, `onQueue('automation')`).
  - Constructor `(int $enrolmentId, ?string $lockToken = null)`.
  - `handle(StepRunner $runner)`: load the enrolment, return if its `lock_token` doesn't match a non-null `$lockToken`. Measure dispatch lag = `now()->diffInSeconds($enrolment->next_run_at)`. If it is over 300, `Cache::increment('marketing_automation.lag.over_5m')`; always `Cache::increment('marketing_automation.lag.total')` (SC-003 signal). Then run, then clear `locked_until/lock_token`. HealthCheck (T035) adds `lag_over_5m_ratio` = over_5m / total to its report and shows it on the health page (red above 0.01).
  - `backoff()`: `[60, 300, 900, 1800, 3600, 7200, 14400]`. `retryUntil()`: `now()->addHours(24)`.
  - `failed(Throwable)`: mark the current MessageLog `failed` (error_message 'retries exhausted'), write a `failed` journey event, then apply fallback if configured. Otherwise advance the enrolment to the next step and set next_run_at=now() (FR-019).
- [X] T075 [US2] Create `packages/Webkul/MarketingAutomation/src/Console/Commands/DispatchDueCommand.php` (`automation:dispatch-due {--now}`):
  - Write the cache heartbeat `marketing_automation.scheduler_heartbeat` = now (HealthCheck).
  - Loop: generate a ULID token. Run `DB::table('automation_enrolments')->where('status','active')->where('next_run_at','<=',now())->where(fn($q)=>$q->whereNull('locked_until')->orWhere('locked_until','<',now()))->whereIn('flow_id', activeFlowIdsSubquery)->orderBy('next_run_at')->limit(500)->update(['locked_until'=>now()->addMinutes(5),'lock_token'=>$token])`. (MySQL supports UPDATE … ORDER BY … LIMIT. If the query builder can't do it, select ids `lockForUpdate` inside a transaction, then update by ids.)
  - Then select ids with that token and dispatch `ProcessEnrolmentStep($id, $token)` for each.
  - Repeat until fewer than 500 are claimed, or at most 20 loops.
  - Register it in the provider and schedule it `->everyMinute()->withoutOverlapping()->runInBackground()` in `MarketingAutomationServiceProvider::boot` (replace the T005 placeholder).

### Implementation for US2 — triggers

- [X] T076 [US2] Create listeners in `packages/Webkul/MarketingAutomation/src/Listeners/` and register them in `EventServiceProvider::$listen`. Each builds a TriggerEvent and calls `EnrolmentService::enrol`. Wrap each call in `dispatch(fn () => ...)->onQueue('automation')` via a small queued job `packages/Webkul/MarketingAutomation/src/Jobs/IngestTrigger.php` (constructor takes a serialized TriggerEvent array), so storefront requests only queue work (SC-006).
  - `CustomerListener::registered($customer)` on `customer.registration.after` (TriggerType::CustomerRegistered, key `customer:{id}`)
  - `NewsletterListener::subscribed($subscriber)` on `customer.subscription.after` (inspect the payload type in `packages/Webkul/Shop/src/Http/Controllers/SubscriptionController.php`; key `subscribe:{id}`). The same listener also grants email consent with source `Newsletter` when `is_subscribed`.
  - `OrderListener::placed($order)` on `checkout.order.save.after` (OrderPlaced, `order:{id}`, payload `{order_id}`)
  - `OrderListener::updated($order)` on `sales.order.update` and `sales.order.cancel.after` (OrderStatusChanged with payload.status, `order-status:{id}:{status}`; plus OrderCompleted `order-completed:{id}` when status == `completed`)
  - `ShipmentListener::saved($shipment)` on `sales.shipment.save.after` (OrderShipped, `shipment:{id}`, payload `{order_id, shipment_id}`)

  Check each event's payload by reading where it's dispatched (`grep -rn "sales.order.update" packages/Webkul`).
- [X] T077 [US2] Back-in-stock.
  - Create `packages/Webkul/MarketingAutomation/src/Listeners/ProductListener.php` handling `catalog.product.update.after` (payload: product). Compute total inventory qty `product->inventories()->sum('qty')`. Use a cache key `marketing_automation.stock.{product_id}` set to the previous qty by a `catalog.product.update.before` handler. When previous ≤ 0 and new > 0, enrol each `waiting` BackInStockSubscription (TriggerType::BackInStock, key `bis:{subscription_id}`, payload `{product_id}`) and set it to `notified`.
  - Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Shop/BackInStockController.php` `store` (contract storefront-public-http.md). Validation: `product_id` exists:products,id; email or phone required; `consent_channel` in email,sms,whatsapp. Grant consent for that channel with source `checkout`. Return 201.
  - Route `POST automation/back-in-stock` named `shop.automation.back-in-stock` in `shop-routes.php`, with `->middleware('throttle:10,1')`.
  - Storefront form view `packages/Webkul/MarketingAutomation/src/Resources/views/shop/back-in-stock.blade.php`, injected through `Event::listen('bagisto.shop.products.view.description.after', ...)`. Render it only when the product is not saleable (`! $product->isSaleable()`); check the available variable name in `packages/Webkul/Shop/src/Resources/views/products/view.blade.php`.
- [X] T078 [US2] Create `packages/Webkul/MarketingAutomation/src/Console/Commands/DailyTriggersCommand.php` (`automation:daily-triggers`), scheduled `->hourly()`. For each channel, compute the local hour in `Settings::timezone`; run only when the local hour is 9 (once per local day, guarded by cache key `marketing_automation.daily.{channel}.{Y-m-d}`).
  - (a) Birthday: customers with `DAYOFMONTH/MONTH(date_of_birth)` = local today and `channel_id` = channel, key `birthday:{id}:{Y}`.
  - (b) Inactive: for each active flow with trigger customer_inactive, `inactive_days = N`: customers whose most recent order (`orders.created_at` max) is exactly N days ago (local date), key `inactive:{contact_id}:{Y-m-d}`, payload `{inactive_days: N}`.
  - Use chunked queries.

### Implementation for US2 — admin

- [X] T079 [US2] Create the Form Request `packages/Webkul/MarketingAutomation/src/Http/Requests/FlowRequest.php`. Rules: `name` required max:150; `trigger_type` Enum rule (TriggerType); `trigger_config` array; `type` Enum (FlowType); `channel_ids` required array min:1 with `channel_ids.*` exists:channels,id; `reentry_window_hours` integer 0..8760; `exit_goals` array, `exit_goals.*.type` in `order_placed`; `draft_definition` nullable array.
- [X] T080 [US2] Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Admin/FlowController.php` with `index` (FlowDataGrid / view), `templates`, `store`, `edit` (view), `update`, `activate`, `pause`, `resume`, `duplicate`, `destroy`, `preview`. Permission checks: `marketing.automation.flows` for read, `.flows.manage` for write. activate returns 422 `{message, errors}` on ValidationException. Add routes in `admin-routes.php` exactly as named in `contracts/admin-http.md` § Flows.
- [X] T081 [US2] Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Admin/FlowTestController.php` `send(Flow $flow, string $stepKey, Request $r)`: validate `{recipient, locale, sample: in:sample,customer, customer_id: required_if:sample,customer}`. Build the context (`ContextBuilder::forSample` or for the chosen customer). Render the step from `draft_definition`, create a MessageLog with `is_test=true`, and send via MessageSender to the recipient (normalize phone for sms/whatsapp) using the default provider of the flow's first channel. Return `{data: {message_log_id, status}}`. Skip consent checks (the admin is sending to themselves).
- [X] T082 [US2] Create `packages/Webkul/MarketingAutomation/src/DataGrids/FlowDataGrid.php` with columns name, trigger_type (translated), type, status (badge), channels (GROUP_CONCAT of channel codes), enrolled_30d and revenue_30d (subqueries on automation_step_stats; zero until US6), and actions edit / duplicate / delete. Create the view `packages/Webkul/MarketingAutomation/src/Resources/views/admin/flows/index.blade.php` with a datagrid and a "Create flow" button opening a modal: name, trigger select, type, channels multi-select, plus a template picker that loads `.flows.templates`.
- [X] T083 [US2] Create the flow builder page `packages/Webkul/MarketingAutomation/src/Resources/views/admin/flows/edit.blade.php` with an inline Vue 3 component `v-flow-builder`, pushed via `@pushOnce('scripts')` like other admin pages. **Keep it a vertical list builder, not a canvas**:
  - Top panel: flow settings (name, trigger + trigger_config inputs per trigger type, type, channels, re-entry hours, exit goal checkbox "Exit when an order is placed").
  - Main: an ordered list of step cards rendered recursively from `draft_definition`, starting at `entry`. A condition card shows two nested columns, "Yes" and "No", each a sub-list.
  - An "Add step" dropdown between cards inserts wait / condition / send email / send sms / send whatsapp / exit and re-links `next` pointers. Keys are generated as `s{n}` where n = max existing + 1.
  - Step edit drawer per type:
    - wait: mode + inputs
    - condition: rows of field/op/value with all/any toggle
    - email: subject + body textarea per locale tab (US5 replaces the textarea with the block editor)
    - sms: body textarea per locale, with a character counter (GSM 160 / UCS-2 70) and fallback channel select + fallback content
    - whatsapp: template select (from `automation_whatsapp_templates` approved, passed by the controller) + one input per variable, plus fallback
    - common: validity_hours
  - Buttons: Save (PUT update), Activate (POST activate; show the 422 errors next to the matching step card), Pause/Resume, and "Send test" on each send card (modal: recipient, locale, sample/customer).
  - All strings are translated.
- [X] T084 [US2] Create the prebuilt templates `packages/Webkul/MarketingAutomation/src/Database/Seeders/FlowTemplates.php`, a class with `all(): array` returning, for keys `welcome, post_purchase, win_back, order_status, back_in_stock` (abandoned_cart is added in T090), arrays `{key, name, description, trigger_type, trigger_config, type, reentry_window_hours, exit_goals, draft_definition}` with sensible English and Arabic content:
  - welcome: registration → email → wait 2d → condition has_ordered_since_enrolment false → whatsapp (fallback sms) → exit
  - post_purchase: order_completed → wait 7d → email review request
  - win_back: customer_inactive 90 → email → wait 3d → condition not ordered → sms
  - order_status: transactional, order_status_changed → sms (fallback email)
  - back_in_stock: back_in_stock → email (fallback sms)

  WhatsApp steps reference `template_id: null`, so activation forces the admin to pick one. `FlowController::templates` returns them. `store` with `template_key` copies the template into the new draft flow. Update `FlowTemplateSeeder` (`packages/Webkul/MarketingAutomation/src/Database/Seeders/FlowTemplateSeeder.php`) to be a no-op (templates are code, not rows) and keep `automation:install` calling it for future use.
- [X] T085 [US2] Run all US2 tests (T054–T059, T064) and fix until green: `php artisan test --compact packages/Webkul/MarketingAutomation/tests/Feature/Engine packages/Webkul/MarketingAutomation/tests/Feature/Admin/FlowManagementTest.php`. Run pint.

**Checkpoint**: US2 is independently demoable (the Welcome flow from the spec's Independent Test).

---

## Phase 5: User Story 1 — Recover abandoned carts automatically across channels (Priority: P1) 🎯 MVP

**Goal**: Detect abandoned carts (customers and guests with email/phone), send the email → WhatsApp/SMS sequence with a working restore link, exit on order, and apply the re-entry window.
**Independent Test**: spec US1 Independent Test / quickstart step 2.

### Tests for US1 (write first)

- [X] T086 [P] [US1] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/AbandonedCart/DetectionTest.php`:
  - an active cart with items whose `updated_at` is 61 min ago, with customer_id → an AbandonedCart row + an enrolment in the active Abandoned Cart flow (key `cart:{cart_id}:{updated_at timestamp}`)
  - a guest cart with only `customer_email` → enrolled
  - a guest cart with only a billing address phone → enrolled, and the email step is skipped with `invalid_contact` (US1-AS3)
  - a cart with no contact → ignored
  - a cart updated 30 min ago with a 60 min delay → ignored
  - the same cart re-scanned → no duplicate
  - a contact with a cart-abandoned enrolment 3 days ago and a 7-day re-entry window → not enrolled (US1-AS4)
  - a cart modified after detection → AbandonedCart status `reopened`; the original enrolment stays `active`, and its next email shows the updated items; a later scan creates a new trigger key, but no new enrolment while the re-entry window is still open
- [X] T087 [P] [US1] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/AbandonedCart/RecoveryTest.php`:
  - restore link GET with a valid token as a guest → 302 to the cart page and the session `cart_id` = cart id (inspect how `Cart::getCart()` resolves the guest cart in `packages/Webkul/Checkout/src/Cart.php` and assert accordingly)
  - an expired token → redirect with the flash `expired`
  - an unknown token → same
  - logged-in customer → cart merged/activated
  - a customer-owned cart opened as a guest → 302 to the cart page (no login redirect), the session cart has the same products and quantities, the original customer cart has `is_active = 0`, and the AbandonedCart has `restored_cart_id` = the new cart id (FR-012). Placing an order from the new cart (fire `checkout.order.save.after` with an order whose `cart_id` = the new cart) marks the **original** AbandonedCart `recovered` with that order id, and the next `automation:detect-abandoned-carts` run doesn't pick up the original cart.
  - token storage: take the token from the rendered restore URL and assert it doesn't appear in the raw values of `automation_enrolments.trigger_payload` or any `automation_abandoned_carts` column (only its SHA-256 is stored)
  - placing an order (fire `checkout.order.save.after` with an order whose `cart_id` = cart) → AbandonedCart `recovered` with `recovered_order_id`, the enrolment `converted`, and no further MessageLogs after running `automation:dispatch-due` with time travel (US1-AS2, SC-005)
  - the email HTML contains each cart item name, the formatted total and the restore URL (US1-AS1)
  - a product disabled after enrolment → the email renders without it; all items disabled → step skipped `cart_empty`

### Implementation for US1

- [X] T088 [US1] Create `packages/Webkul/MarketingAutomation/src/Services/AbandonedCartService.php`:
  - `detect(): int` runs this query: `cart` where `is_active=1`, `items_count>0`, `updated_at <= now()-delay`, `updated_at >= now()-7 days`. The delay is the minimum `trigger_config.abandon_delay_minutes` among active cart_abandoned flows for that channel (default 60). Join `cart_addresses` (address_type `cart_billing`; check the constant in `packages/Webkul/Checkout/src/Models/CartAddress.php`) for the phone and country. The contact is customer_id, else `customer_email`, else the address phone. Process in chunks of 200.
  - Create `packages/Webkul/MarketingAutomation/src/Services/RestoreToken.php`:
    - `for(AbandonedCart $c): string` returns `$c->id.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $c->id.'|'.$c->detected_at->timestamp, config('app.key'), true)), '+/', '-_'), '=')`
    - `hash(string $token): string` returns `hash('sha256', $token)`
  - For each cart: resolve the contact. Upsert AbandonedCart by cart_id. If the existing status is `abandoned` and the trigger key is the same → skip. Otherwise set `status = abandoned`, `detected_at = now()`, `restore_token_hash = RestoreToken::hash(RestoreToken::for($record))` and `restore_token_expires_at = now() + restoreLinkDays`. **Never store the plain token anywhere** (research R6); the link is rebuilt from the record when rendering. A re-detected cart gets a new `detected_at`, so a new token is issued and older links stop working. Then `EnrolmentService::enrol(TriggerEvent(CartAbandoned, key "cart:{id}:{updated_at->timestamp}", payload {cart_id, abandoned_cart_id}))`.
  - Dispatch `marketing_automation.cart.abandoned`.
  - Per-flow delay: EnrolmentService must skip flows whose `abandon_delay_minutes` is greater than the cart idle time. Add this filter to T063's trigger_config matching.
- [X] T089 [US1] Create `packages/Webkul/MarketingAutomation/src/Console/Commands/DetectAbandonedCartsCommand.php` (`automation:detect-abandoned-carts`), scheduled `everyFiveMinutes()->withoutOverlapping()`. Create `packages/Webkul/MarketingAutomation/src/Listeners/CartListener.php`:
  - `changed($cart)` on `checkout.cart.add.after`, `checkout.cart.update.after`, `checkout.cart.delete.after` → if an AbandonedCart exists with status `abandoned` → set `reopened`. Inspect the payloads; some pass a cart item, so resolve `Cart::getCart()`.
  - `orderPlaced($order)` on `checkout.order.save.after` → find the AbandonedCart with `cart_id = $order->cart_id` OR `restored_cart_id = $order->cart_id` → set `recovered` + `recovered_order_id`.
  - A `reopened` cart does NOT cancel its active enrolment. Later steps render the cart's current contents at send time (spec edge case "Cart contents change after enrolment"). A new enrolment for the re-detected cart is created only after the flow's re-entry window has passed.
- [X] T090 [US1] Exit goals.
  - Create `packages/Webkul/MarketingAutomation/src/Engine/ExitGoalService.php` with `orderPlaced(Order $order): void`. Find active enrolments for the contact (resolve by customer_id or customer_email within the order channel) whose flow `exit_goals` contains `order_placed` and whose `created_at <= order.created_at`. Set `status=converted`, `completed_at`, `next_run_at=null`, write an `exited` event (reason `goal_met`), increment StepStat `converted` and dispatch `marketing_automation.enrolment.finish.after`.
  - Call it from `OrderListener::placed` **synchronously** (before queuing the trigger ingestion), so SC-005 holds even when the queue is slow.
  - Add the `abandoned_cart` template to `packages/Webkul/MarketingAutomation/src/Database/Seeders/FlowTemplates.php`: trigger cart_abandoned `{abandon_delay_minutes: 60}`, re-entry 168 h, exit goal order_placed. Steps:
    - s1 send_email (subject "You left something behind" / "نسيت شيئاً في سلتك", blocks: text + cart_items + button restore)
    - s2 wait 1380 min (≈23 h)
    - s3 send_whatsapp (template null, fallback_channel sms, fallback_step body "{{ customer.first_name | default: \"Hi\" }}, your cart is waiting: {{ cart.restore_url }}")
    - s4 wait 2880 min
    - s5 send_email (subject "Still thinking it over?" / "هل ما زلت تفكر؟", blocks: text + cart_items + button restore). No coupon block, so the template activates without picking a cart rule (SC-001).
    - s6 exit

    That's 3 message steps (FR-008).
- [X] T091 [US1] Restore link.
  - Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Shop/CartRestoreController.php` `restore(string $token)`: find AbandonedCart by `hash('sha256', $token)` with `restore_token_expires_at > now()`. If not found → redirect `route('shop.checkout.cart.index')` with flash `warning` = trans `marketing_automation::app.shop.restore.expired`. Load the cart.
    - Guest (not `auth()->guard('customer')->check()`) with a customer-owned cart (`cart.customer_id` set): never redirect to login (FR-012).
      - For each active item, load the product with `app(\Webkul\Product\Repositories\ProductRepository::class)->find($item->product_id)` and call `Cart::addProduct($product, ['product_id' => $item->product_id, 'quantity' => $item->quantity] + ($item->additional ?? []))`. `addProduct` takes a **Product model**, not an id (`packages/Webkul/Checkout/src/Cart.php:256`). Skip items whose product is missing or can't be sold, and catch an exception per item so one bad item doesn't abort the restore. The first `addProduct` call creates the guest cart and puts it in the session through `setCart()`, which only does that for carts without a customer; read `Cart::addProduct()` / `createCart()` to confirm.
      - Save the new cart's id as `automation_abandoned_carts.restored_cart_id` on the AbandonedCart. The core `cart` table has no `additional` column, so don't store this on the cart.
      - Set the original customer cart to `is_active = 0`, so it isn't detected as abandoned again. Bagisto still merges it if the customer logs in later, because `mergeCart` uses the guest session cart.
    - Guest with a guest cart (`cart.customer_id` null): set `is_active=1` and call `Cart::setCart($cart)`; inspect `packages/Webkul/Checkout/src/Cart.php::setCart` to see how the guest cart id is put in session, and use that API rather than touching the session key directly.
    - Logged-in customer: if `cart.customer_id` is the same → `Cart::activateCart($cart->id)`; if it's a guest cart → set the session guest cart, then `Cart::mergeCart(auth customer)`.
    - If `?m={messageUuid}` is present, queue a `clicked` MessageEvent (T118; until then, a no-op call to `MessageEvents::record()`, a stub class created here in `packages/Webkul/MarketingAutomation/src/Services/MessageEvents.php`).
    - Redirect to the cart page.
  - Route `GET automation/cart/restore/{token}` named `shop.automation.cart.restore` in `shop-routes.php`.
  - In `ContextBuilder::forEnrolment`, when `trigger_payload.abandoned_cart_id` is set, set `cart.restore_url = route('shop.automation.cart.restore', RestoreToken::for(AbandonedCart::find(trigger_payload.abandoned_cart_id)))`. The controller lookup by `RestoreToken::hash($token)` stays the same.
  - Cart item rendering: skip items whose product `status` is 0 or not saleable. If none are left and the step's blocks include `cart_items`, the SendStep must skip with reason `cart_empty`. Expose `context.cart.items` already filtered, and add a check in SendStep for email steps that contain a cart_items block and have empty items.
- [X] T092 [US1] Guest contact capture.
  - Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Shop/CheckoutContactController.php` `store` (contract storefront-public-http.md). It validates email (nullable email) and phone (nullable max:30), plus `consents` booleans. It attaches to the current cart: sets `cart.customer_email` if empty, resolves the contact, and grants each consent=true with source `checkout`. Then `Cookie::queue('ma_contact', $contact->uuid, 60 * 24 * 365, null, null, null, true, false, 'Lax')` (an id-only cookie used to identify returning visitors; Klaviyo T135 reads it). Returns 204. Its test (in `DetectionTest`, T086) asserts the response has the `ma_contact` cookie. Route `POST automation/checkout-contact` named `shop.automation.checkout-contact`, `throttle:10,1`.
  - Create the view `packages/Webkul/MarketingAutomation/src/Resources/views/shop/checkout/contact-capture.blade.php`. It holds unchecked consent checkboxes ("Email me offers", "Send me SMS", "Send me WhatsApp messages") and a script that, on `blur`/`change` of the checkout email and phone inputs (query `input[name$="[email]"]`, `input[name$="[phone]"]`), POSTs via `fetch` with the CSRF token from `meta[name=csrf-token]`.
  - Inject it with `Event::listen('bagisto.shop.checkout.onepage.address.after', ...)` in `EventServiceProvider::boot`.
  - The Amacommerce theme lacks this hook: add `{!! view_render_event('bagisto.shop.checkout.onepage.address.after') !!}` at the end of its checkout address view. Find the file with `grep -rln "onepage" packages/Webkul/AmacommerceTheme/src/Resources/views/checkout`.
- [X] T093 [US1] Customer login merge: in `packages/Webkul/MarketingAutomation/src/Listeners/CustomerListener.php` add `loggedIn($customer)` on `customer.after.login`. If a guest contact with the customer's email exists in the channel (customer_id null), call `ContactResolver::merge(guest, forCustomer(customer))`. Then queue the `ma_contact` cookie (same parameters as T092) with the customer contact's uuid; the test asserts the login response has it. Add a test case to `packages/Webkul/MarketingAutomation/tests/Feature/AbandonedCart/DetectionTest.php`: a guest enrolment + login → exactly one active enrolment, keyed to the customer contact.
- [X] T094 [US1] Run the US1 tests and fix until green: `php artisan test --compact packages/Webkul/MarketingAutomation/tests/Feature/AbandonedCart`. Run pint.

**Checkpoint (MVP)**: Phases 1–5 deliver a working abandoned-cart recovery across email/SMS/WhatsApp.

---

## Phase 6: User Story 4 — Respect customer consent and preferences (Priority: P1)

**Goal**: Opt-in UI, one-click unsubscribe, SMS/WhatsApp STOP, quiet hours and frequency caps (marketing only), transactional rules, GDPR export/erasure.
**Independent Test**: spec US4 Independent Test / quickstart steps 3–4.

### Tests for US4 (write first)

- [X] T095 [P] [US4] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Consent/OptInTest.php`:
  - registration POST (`shop.customers.register.store`; check the route name in `packages/Webkul/Shop/src/Routes/customer-routes.php`) with `marketing_consent[sms]=1` → SMS consent granted, source `registration`; without it → none
  - account preferences PUT revokes WhatsApp (source `account`)
  - checkout-contact POST with consents grants them (source `checkout`)
  - the signup page HTML contains the 3 unchecked checkboxes (shop theme)
- [X] T096 [P] [US4] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Consent/UnsubscribeTest.php`:
  - a marketing email contains the unsubscribe URL and the `List-Unsubscribe` + `List-Unsubscribe-Post` headers (`Mail::fake()`, then inspect the sent mailable's headers)
  - GET shows a confirm page; POST revokes email consent with source `unsubscribe_link` and a timestamp (US4-AS2)
  - one-click POST with body `List-Unsubscribe=One-Click` and no CSRF → 200 and revoked
  - a tampered token → 404
  - transactional emails contain no unsubscribe header
- [X] T097 [P] [US4] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Consent/InboundOptOutTest.php`:
  - POST a Twilio inbound webhook with a valid signature and Body `STOP` → SMS consent revoked, source `sms_stop`
  - Arabic `إلغاء` → revoked
  - Meta inbound with a `STOP` button → WhatsApp revoked, source `whatsapp_optout`
  - a bad signature → 403
  - an unknown connection uuid → 404
- [X] T098 [P] [US4] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Consent/SendTimingTest.php`, with quiet hours enabled 22:00–09:00 and channel timezone `Asia/Riyadh`:
  - a marketing SMS due at 23:00 local → deferred, `next_run_at` = 09:00 local next day (in UTC) (US4-AS3)
  - a marketing email at 23:00 → sent (quiet hours only for SMS/WhatsApp)
  - a transactional SMS at 23:00 → sent immediately (clarification Q4)
  - cap 2 per 24 h: with 2 marketing SMS already logged in the last 24 h, the 3rd is deferred until the oldest + 24 h; if that is beyond `validity_hours` (set 1) → skipped `frequency_cap` (US4-AS4)
  - transactional messages don't count toward the cap
- [X] T099 [P] [US4] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Consent/GdprTest.php`:
  - admin customer deletion (fires `customer.delete.before`) → contact, consents, consent events, message logs and enrolments deleted; step_stats rows remain
  - a GDPR request with `type=delete` updated to `status=completed` (fire `customer.account.gdpr-request.update.after` with the request model) → same; `status=processing` → nothing erased
  - GET `shop.customers.account.marketing-preferences.export` as the customer → a JSON download with keys `consents`, `consent_events`, `enrolments`, `messages` containing that customer's rows only

### Implementation for US4

- [X] T100 [US4] Opt-in UI.
  - Create the view `packages/Webkul/MarketingAutomation/src/Resources/views/shop/consent-checkboxes.blade.php`: three unchecked checkboxes named `marketing_consent[email]`, `marketing_consent[sms]`, `marketing_consent[whatsapp]`, with translated labels. Inject it via `Event::listen('bagisto.shop.customers.signup_form_controls.after', ...)` (this hook exists in both the Shop and Amacommerce themes).
  - In `CustomerListener::registered` (T076), read `request()->input('marketing_consent', [])` and grant each consent with source `registration` (synchronously, before queuing the trigger).
  - Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Shop/MarketingPreferencesController.php` (`index`, `update`) with routes `GET|PUT customer/account/marketing-preferences` named `shop.customers.account.marketing-preferences.index|update`, using middleware `customer`. The view `packages/Webkul/MarketingAutomation/src/Resources/views/shop/account/marketing-preferences.blade.php` extends the account layout; copy the layout component used by `packages/Webkul/Shop/src/Resources/views/customers/account/profile/edit.blade.php`.
  - Add a link to the account profile page via `Event::listen('bagisto.shop.customers.account.profile.edit.before', ...)`. Amacommerce: if its profile view lacks the hook, add `{!! view_render_event('bagisto.shop.customers.account.profile.edit.before', ['customer' => $customer]) !!}` to it.
- [X] T101 [US4] Unsubscribe.
  - Create `packages/Webkul/MarketingAutomation/src/Services/SignedToken.php` with `make(string ...$parts): string` and `parse(string $token): ?array`, per the contracts/storefront-public-http.md token format (base64url payload + '.' + base64url HMAC-SHA256 with `config('app.key')`, compared with `hash_equals`).
  - Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Shop/UnsubscribeController.php`:
    - `show($token)`: confirm page `marketing_automation::shop.unsubscribe`
    - `confirm($token)`: revoke the parsed channel for the parsed contact uuid, source `UnsubscribeLink`; record a MessageEvent `unsubscribed` on the message uuid via the MessageEvents stub; return a thank-you page, or an empty 200 when the body is `List-Unsubscribe=One-Click`
  - Routes go in `public-routes.php` (no CSRF, session OK): `GET|POST automation/unsubscribe/{token}` named `shop.automation.unsubscribe` and `shop.automation.unsubscribe.confirm`.
  - In SendStep / MessageRenderer, for marketing email set `context.unsubscribe_url = route('shop.automation.unsubscribe', SignedToken::make(contact.uuid, 'email', log.uuid))`, pass it to AutomationMail, and append a footer link to the rendered HTML if the template has no footer block. For marketing SMS, append nothing (opt-out is via STOP).
- [X] T102 [US4] Webhook endpoint.
  - Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/WebhookController.php`:
    - `verify(string $driver, string $uuid)`: GET; for Meta, echo `hub_challenge` when `hub_verify_token` = webhook_secret
    - `receive(string $driver, string $uuid)`: find the connection by uuid AND driver (404 if missing); `verifyWebhook` (403 if false); dispatch `ProcessWebhook` with the request's method, headers, raw body and query captured into an array; return 200 `''`
  - Routes in `public-routes.php`: `GET|POST automation/webhooks/{driver}/{uuid}` named `automation.webhooks.verify` / `automation.webhooks.receive`.
  - Create the job `packages/Webkul/MarketingAutomation/src/Jobs/ProcessWebhook.php`. It rebuilds a `Request` from the captured array.
    - If the driver implements SupportsInboundOptOut and `parseInbound` returns a message whose trimmed, case-insensitive body matches `/^(stop|unsubscribe|إلغاء|الغاء|توقف)$/iu`, or whose type is `opt_out`: resolve the contact by the E.164 phone in the connection's channels (use the default mapping tables) and revoke the connection's channel (sms → `SmsStop`, whatsapp → `WhatsappOptout`).
    - Status updates are handled in US6 (T116). Leave a clearly marked call `$this->applyStatusUpdates(...)` that is empty for now.
  - Exclude these routes from response cache: check `config/responsecache.php`. If routes are cached by middleware `cache.response` only on specific routes, nothing needs to be done.
- [X] T103 [US4] Quiet hours and cap. Implement `SendPolicy::applyTimingRules()` (T068 hook):
  - Only when the flow type is Marketing.
  - Quiet hours (only sms/whatsapp, and only if `quiet_hours_enabled`): convert now to the channel tz. If the local time is within [start, end) (handle wrap past midnight: start > end means overnight), set `deferUntil` = the next local `end` time converted to UTC.
  - Cap: `MessageLog::where(contact_id)->where(message_channel)->where('is_marketing', true)->where('is_test', false)->whereIn('status', ['queued','sent','delivered','read'])->where('created_at', '>=', now()->subHours(window))`, ordered oldest first. If count >= cap_count → `deferUntil = oldest.created_at + window hours + 1 minute`.
  - If both apply, take the later time.
  - SendStep (T071) already turns deferral into skip/expired when it exceeds validity_hours.
- [X] T104 [US4] Transactional guard: in `MessageRenderer::renderEmail`, when the flow type is Transactional, drop any `coupon` or `product_grid` blocks defensively (the validator already blocks them) and never set unsubscribe_url/headers. Add assertions for this to `UnsubscribeTest` (T096).
- [X] T105 [US4] GDPR.
  - Create `packages/Webkul/MarketingAutomation/src/Services/GdprService.php`:
    - `export(Customer $c): array{consents: array, consent_events: array, enrolments: array, messages: array}`
    - `erase(Customer $c): void`: for every contact of the customer (by customer_id, or matching email), set active enrolments to cancelled (`exit_reason gdpr`), then delete message events, message logs, enrolment events, enrolments, consents, consent events, abandoned carts, back-in-stock subscriptions and the contact, in a transaction. step_stats and attributions are kept; set `attributions.message_log_id` null (FK nullOnDelete).
  - Create `packages/Webkul/MarketingAutomation/src/Listeners/GdprListener.php` and register two events (verified in the codebase):
    - `customer.delete.before` (payload: Customer; dispatched in `packages/Webkul/Admin/src/Http/Controllers/Customers/CustomerController.php`) → `GdprService::erase($customer)`. Use *before*, because FK cascades remove the `customer_id` link once the row is deleted.
    - `customer.account.gdpr-request.update.after` (payload: `Webkul\GDPR\Models\GDPRDataRequest`; dispatched by the admin `GDPRController::update`) → when `$request->type === 'delete'` and `$request->status === 'completed'`, load the customer by `$request->customer_id` and call `erase()`.
  - Export: Bagisto builds its GDPR PDF/HTML inline in `packages/Webkul/Shop/src/Http/Controllers/Customer/GDPRController::pdfView/htmlView` and offers no hook. Add route `GET customer/account/marketing-preferences/export` named `shop.customers.account.marketing-preferences.export` (middleware `customer`) in `MarketingPreferencesController::export()`. It returns `response()->json(GdprService::export($customer))` with `Content-Disposition: attachment; filename=marketing-data.json`. Link it from the marketing preferences page (T100) and from the GDPR page via `Event::listen('bagisto.shop.customers.account.gdpr.after', ...)`. If that view hook doesn't exist in the Shop GDPR view, add it only to the Amacommerce GDPR view (never edit Shop core).
- [X] T106 [US4] Run the US4 tests and fix until green: `php artisan test --compact packages/Webkul/MarketingAutomation/tests/Feature/Consent`. Re-run the US1/US2 tests to check for regressions. Run pint.

**Checkpoint**: P1 scope (US1–US4) is complete and compliant.

---

## Phase 7: User Story 5 — Write personalized, multilingual messages (Priority: P2)

**Goal**: Block-based email editor, per-locale versions with RTL, variable fallbacks, unique coupons, product recommendations.
**Independent Test**: spec US5 Independent Test.

### Tests for US5 (write first)

- [X] T107 [P] [US5] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Content/EmailBlocksTest.php`:
  - each block type renders (header, text, image, button, product_grid, cart_items, order_summary, coupon, footer) with the given props
  - merchant text containing `<script>` is escaped
  - an Arabic locale version → `dir="rtl"` on the wrapper and Arabic subject used (US5-AS2)
  - a missing Arabic version → default locale content
  - a missing first name with fallback → fallback text (US5-AS3)
  - a button href only accepts http(s) URLs or whitelisted variables (`{{ cart.restore_url }}`); `javascript:` becomes `#`
- [X] T108 [P] [US5] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Content/CouponTest.php`:
  - a coupon block tied to a cart rule with `coupon_type=1` (specific) and `use_auto_generation=1`; check the column names in the CartRule migrations
  - sending to 2 customers → 2 different codes in `cart_rule_coupons` with `usage_limit=1`, `usage_per_customer=1` (US5-AS1)
  - retrying the same MessageLog reuses its `coupon_code` (no second coupon)
  - applying the code at checkout works (use the coupon apply route from `packages/Webkul/Shop/tests/Feature/Checkout`)

### Implementation for US5

- [X] T109 [US5] Create `packages/Webkul/MarketingAutomation/src/Rendering/EmailBlockRenderer.php` with `render(array $blocks, array $context, string $direction): string`. Each block maps to a Blade view `marketing_automation::emails.blocks.{type}` in `packages/Webkul/MarketingAutomation/src/Resources/views/emails/blocks/{type}.blade.php`. The views receive **pre-rendered, pre-escaped** values and use only `{{ }}` echo (auto-escaped), except an explicitly whitelisted `$html` from the text block, which is built as `nl2br(e(TemplateRenderer::render(...)))`.
  - Block props:
    - header: logo_url?, title
    - text: content
    - image: src (http/https only), alt, href?
    - button: label, href, color (hex regex)
    - product_grid: source (`recommended|bestsellers|related`), count (1–6)
    - cart_items: none (uses `context.cart.items`)
    - order_summary: none
    - coupon: cart_rule_id, label, expires_days?
    - footer: text (an unsubscribe link is auto-appended for marketing)
  - Use table-based markup with inline styles; alignment is `text-align: {{ $direction === 'rtl' ? 'right' : 'left' }}`.
  - Move the T070 block rendering (text, button, cart_items, order_summary, footer) into this class unchanged, add the remaining block types, and have `MessageRenderer::renderEmail` call this class.
- [X] T110 [US5] Create `packages/Webkul/MarketingAutomation/src/Rendering/CouponIssuer.php` with `issue(MessageLog $log, int $cartRuleId, ?int $expiresDays): string`. If `$log->coupon_code` is set, return it. Otherwise load the cart rule; if it doesn't allow auto-generation, throw (the validator also checks this). Generate a code with `CartRuleCouponRepository::getRandomString('alphanumeric', 10)` (check the valid format keys in its `$charset`) with prefix `MA-`. Create a `cart_rule_coupons` row `{cart_rule_id, code, usage_limit: 1, usage_per_customer: 1, is_primary: 0, type: 1 (check the column meaning), expired_at: now()+expiresDays or the rule's ends_till}`, save `$log->coupon_code`, and return it. Set `context.coupon.code` before rendering the blocks. Add the validator rule to `FlowValidator` (T061): every coupon block's cart rule exists and has auto generation on. In the builder (T083/T112), show a hint on the abandoned-cart template's s5 card: "Add a coupon block to offer a discount" (trans key `marketing_automation::app.flows.hints.add-coupon`).
- [X] T111 [US5] Create `packages/Webkul/MarketingAutomation/src/Rendering/Recommendations.php` with `forContext(array $context, string $source, int $count, int $channelId): array` returning `[{name, price_formatted, image_url, url}]`.
  - `related`: `product_relations` of cart/order products
  - `upsell`: `product_up_sells`
  - `bestsellers`: top `order_items.product_id` by qty in the last 90 days, within the same categories as the cart/order items, falling back to overall
  - Exclude products already in the cart/order and non-saleable ones. Use the product image helper (`product_image()->getProductBaseImage($product)`; check `packages/Webkul/Product/src/ProductImage.php`) and `route('shop.product_or_category.index', $product->url_key)`; check the route name with `php artisan route:list --name=product`.
- [X] T112 [US5] Create the email block editor as an inline Vue component `v-automation-email-editor` in `packages/Webkul/MarketingAutomation/src/Resources/views/admin/flows/partials/email-editor.blade.php`, and include it in the email step drawer of `admin/flows/edit.blade.php` (T083), replacing the textarea:
  - a left palette of block types
  - a middle sortable list of blocks (up/down buttons are enough, no drag library)
  - a right props form per selected block
  - a locale tabs bar (from `core()->getAllLocales()`) with "Copy from default locale"
  - a variable picker dropdown inserting `{{ path | default: "" }}` tokens from `TemplateRenderer::variables()`
  - a live preview iframe that POSTs the current blocks to `admin.marketing.automation.flows.preview` (extend the preview to accept a draft step payload `POST` with `{step, locale}`; add the route `POST flows/{id}/preview` named `admin.marketing.automation.flows.preview_draft`)
- [X] T113 [US5] SMS and WhatsApp per-locale: make sure `MessageRenderer::renderText` and `renderWhatsApp` select the locale version with the default-locale fallback, and that the `whatsapp` step stores `language[locale] = template language code`. In the builder (T083) add locale tabs for SMS body and WhatsApp language selection. Add a case to `EmailBlocksTest` (T107) or a new `packages/Webkul/MarketingAutomation/tests/Feature/Content/LocaleSelectionTest.php` for SMS: an Arabic contact gets the Arabic body.
- [X] T114 [US5] Run the US5 tests and fix until green: `php artisan test --compact packages/Webkul/MarketingAutomation/tests/Feature/Content`. Run pint.

---

## Phase 8: User Story 6 — Measure flow performance (Priority: P2)

**Goal**: Delivery statuses, open/click tracking, last-click attribution, daily rollups, flow report UI, journey log, retention purge.
**Independent Test**: spec US6 Independent Test / quickstart step 5.

### Tests for US6 (write first)

- [X] T115 [P] [US6] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Reporting/TrackingTest.php`:
  - email HTML links are rewritten to `/automation/t/c/{uuid}.{sig}/{hash}` and the pixel is appended (marketing and transactional both tracked)
  - GET click → 302 to the original URL, a `clicked` event, and `clicked_at` set
  - an unknown hash → 404 (no open redirect)
  - a bad sig → 404
  - GET open pixel → `image/gif`, an `opened` event, and only the first open sets `opened_at`
  - a Twilio status webhook `delivered` → log `delivered`, `delivered_at`; `failed` with ErrorCode 30003 → `failed` with error stored (US6-AS2)
  - a Meta status `read` → `read`
- [X] T116 [P] [US6] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Reporting/AttributionTest.php`:
  - a click 2 days before the order → attributed to that flow/step with revenue = `base_grand_total` (US6-AS1)
  - a click 6 days before (window 5 d) → not attributed
  - clicks in 2 flows → the latest click wins, and exactly 1 attribution row
  - an SMS delivered 10 h before with no click → attributed (model `delivered`); 30 h before → not
  - the same order event twice → still 1 row
  - the report endpoint sums revenue equal to the attribution rows over a date range (SC-007)
- [X] T117 [P] [US6] Feature test `packages/Webkul/MarketingAutomation/tests/Feature/Reporting/ReportTest.php`:
  - run the abandoned cart flow for 4 contacts with mixed outcomes (1 converted, 1 skipped no_consent, 1 failed, 1 completed); `GET .flows.report` returns matching per-step counts and `skipped_by_reason`
  - the date range filter works, and channel filter works
  - journey endpoint lists events in order with masked recipient (`+96650****567`, `s***@example.com`)
  - `automation:purge-logs` with retention 12 months deletes 13-month-old logs/events/enrolment events but keeps step_stats and attributions; report totals for that old range are unchanged (FR-032a)

### Implementation for US6

- [X] T118 [US6] Replace the stubs:
  - Implement `packages/Webkul/MarketingAutomation/src/Services/StatsRecorder.php` with `increment(int $flowId, string $stepKey, int $channelId, string $metric, string $skipReason = '', int $by = 1, float $revenue = 0): void`. It uses `DB::table('automation_step_stats')->upsert([...], ['date','flow_id','step_key','channel_id','metric','skip_reason'], ['count' => DB::raw('count + '.$by), 'revenue' => DB::raw('revenue + '.$revenue)])`, with date = today in the channel tz. Always also increment flow-level `step_key='_flow'` for `enrolled` and `converted`.
  - Call it everywhere a journey event is written: sent/skipped/failed/deferred-not-counted. Also from status updates (delivered/opened/clicked/unsubscribed/failed) counting **first occurrence only** per log.
  - Implement `packages/Webkul/MarketingAutomation/src/Services/MessageEvents.php` with `record(string $messageUuid, MessageEventType $type, array $meta = []): void`. It dispatches the job `packages/Webkul/MarketingAutomation/src/Jobs/RecordMessageEvent.php`, which inserts a MessageEvent, sets the first-time timestamps (`opened_at`, `clicked_at`, `delivered_at`), updates status monotonically (never downgrade read → delivered → sent), increments stats on first occurrence, and dispatches `marketing_automation.message.status.after`.
- [X] T119 [US6] Create `packages/Webkul/MarketingAutomation/src/Rendering/LinkTracker.php` with `rewrite(MessageLog $log, string $html): string`. It parses `href="..."` with a regex or DOMDocument for http(s) links, skipping the unsubscribe URL. For each link it stores `links[hash] = url` with `hash = substr(sha1(url), 0, 12)`, replaces the href with `route('automation.track.click', [$log->uuid.'.'.$sig, $hash])`, appends `<img src="{route automation.track.open}" width="1" height="1" alt="">` before `</body>`, and saves `$log->links`. Sig = first 16 chars of the HMAC of the uuid. Call it in MessageSender before sending email. For SMS/WhatsApp, rewrite `cart.restore_url` to include `?m={uuid}` so restore clicks are tracked (T091).
- [X] T120 [US6] Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/TrackingController.php` `open($token)` and `click($token, $hash)` per contracts/storefront-public-http.md § Tracking. Add routes in `public-routes.php` named `automation.track.open` (`GET automation/t/o/{token}.gif`) and `automation.track.click` (`GET automation/t/c/{token}/{hash}`). The open response is a 43-byte transparent GIF (`base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')`) with `Cache-Control: no-store`.
- [X] T121 [US6] Fill in `ProcessWebhook::applyStatusUpdates()` (T102): for each StatusUpdate, find the MessageLog by `(provider_connection_id, provider_message_id)` and call `MessageEvents::record` with the mapped type. For `failed`, also store `error_code/error_message` on the log.
- [X] T122 [US6] Create `packages/Webkul/MarketingAutomation/src/Services/AttributionService.php` with `attribute(Order $order): ?Attribution`. Called from `OrderListener::placed` (queued job is fine). Resolve contact ids for the order (customer_id or customer_email in the channel). Find the latest MessageLog with `clicked_at >= order.created_at - clickWindowHours` among those contacts, not a test. If there is none, find the latest sms/whatsapp log with `delivered_at >= order.created_at - deliveredWindowHours` and `clicked_at` null. Insert an Attribution (catch the unique violation → return the existing one) with revenue = `base_grand_total` and currency = `base_currency_code`. Then `StatsRecorder::increment(flow, step, channel, 'converted', revenue: …)`. Make sure ExitGoalService's `converted` increment (T090) does NOT add revenue, so revenue is only counted here.
- [X] T123 [US6] Create `packages/Webkul/MarketingAutomation/src/Repositories/StepStatRepository.php` with `report(int $flowId, Carbon $from, Carbon $to, ?int $channelId): array`. It sums rows grouped by step_key and metric into the shape from contracts/admin-http.md § Reports. Step types come from the flow's current version definition; steps that exist only in older versions are listed with type `removed`. Create `packages/Webkul/MarketingAutomation/src/Http/Controllers/Admin/ReportController.php` with `report`, `enrolments` (DataGrid `packages/Webkul/MarketingAutomation/src/DataGrids/EnrolmentDataGrid.php`: contact email/phone masked, status, created_at, current step) and `journey`. Permission `marketing.automation.reports`. Add routes per contracts.
- [X] T124 [US6] Create the report UI `packages/Webkul/MarketingAutomation/src/Resources/views/admin/flows/report.blade.php`, linked as a "Report" tab/button on the edit page:
  - a date range picker (defaults to the last 30 days) and channel filter
  - KPI tiles: enrolled, sent, delivered, open rate, click rate, converted, revenue
  - a per-step table with all metrics and a skipped breakdown tooltip
  - the enrolments DataGrid; clicking a row opens a journey drawer that fetches the journey endpoint and renders a timeline with reason texts and provider error messages

  No new chart library; tiles and tables only.
- [X] T125 [US6] Create `packages/Webkul/MarketingAutomation/src/Console/Commands/PurgeLogsCommand.php` (`automation:purge-logs`), scheduled `daily()`. Cutoff = now - `Settings::retentionMonths()`. Delete in batches of 1000 (loop `->limit(1000)->delete()`): `automation_message_events` older than cutoff, `automation_message_logs` with `created_at < cutoff`, `automation_enrolment_events` < cutoff, and enrolments with status != active and `completed_at < cutoff`. Never touch step_stats or attributions.
- [X] T126 [US6] Run the US6 tests and fix until green: `php artisan test --compact packages/Webkul/MarketingAutomation/tests/Feature/Reporting`. Re-run the full MA suite: `php artisan test --compact --testsuite="MarketingAutomation Feature Test"`. Run pint.

---

## Phase 9: User Story 7 — Sync store data to Klaviyo (Priority: P3)

**Goal**: A separate `Webkul\Klaviyo` module that connects by API key, runs a historical sync with progress, an outbox for idempotent event delivery, consent pull-back and routing.
**Independent Test**: spec US7 Independent Test / quickstart step 6 (with faked HTTP in tests).

### Setup for US7

- [X] T127 [US7] Scaffold `packages/Webkul/Klaviyo` exactly like Phase 1 did for MA:
  - dirs `src/{Config,Console/Commands,Contracts,Database/Migrations,Database/Factories,Http/Controllers/Admin,Http/Requests,Jobs,Listeners,Models,Providers,Repositories,Routes,Services,Resources/views/admin,Resources/lang/en,Resources/lang/ar}`, `tests/Feature`
  - PSR-4 entries `Webkul\\Klaviyo\\` and `Webkul\\Klaviyo\\Tests\\` in `/var/www/html/bagisto/composer.json`
  - `KlaviyoServiceProvider` (mergeConfig menu → `menu.admin` with entry key `marketing.automation.klaviyo` (it must equal the ACL key so Bagisto's menu filter shows it), route `admin.marketing.klaviyo.index`; acl entry `marketing.automation.klaviyo` is already defined in MA T031, so don't duplicate it; translations namespace `klaviyo`, views `klaviyo`, admin routes with the same middleware/prefix as MA, and shop routes `src/Routes/shop-routes.php` loaded with `Route::middleware(['web', 'shop'])` for the T135 beacon)
  - `ModuleServiceProvider`
  - registration in `bootstrap/providers.php` and `config/concord.php`
  - `KlaviyoTestCase` + `tests/Pest.php` `uses()` + phpunit testsuite `Klaviyo Feature Test`
  - `composer dump-autoload`
- [X] T128 [US7] Migrations `packages/Webkul/Klaviyo/src/Database/Migrations/2026_10_08_100001_create_klaviyo_tables.php` for klaviyo_connections, klaviyo_outbox and klaviyo_sync_jobs, exactly per data-model.md § Klaviyo module (enum-like columns as strings). Models + Contracts + Proxies + Repositories + Factories: `Connection` (`api_key` cast `encrypted`), `OutboxItem`, `SyncJob`. Register them in the Klaviyo ModuleServiceProvider.

### Tests for US7 (write first)

- [X] T129 [P] [US7] Create fixtures `packages/Webkul/Klaviyo/tests/Fixtures/` (`accounts_ok.json`, `accounts_401.json`, `events_202.json` (empty body, 202), `events_429.json`, `events_409_duplicate.json`, `unsubscribed_events_page.json` with 2 unsubscribe events containing profile emails, `metrics.json` listing an "Unsubscribed from Email Marketing" metric id) and Feature test `packages/Webkul/Klaviyo/tests/Feature/ConnectionTest.php`:
  - connect with a valid key → `GET https://a.klaviyo.com/api/accounts` sent with headers `Authorization: Klaviyo-API-Key {key}` and `revision: 2024-10-15` → status connected
  - a 401 → 422 with message
  - the API key never appears in the GET index response
- [X] T130 [P] [US7] Feature test `packages/Webkul/Klaviyo/tests/Feature/OutboxTest.php`:
  - placing an order creates outbox rows: 1 `Placed Order` + N `Ordered Product` (one per line item) + a profile upsert (US7-AS2)
  - `klaviyo:flush-outbox` posts each to `/api/events` with `unique_id` per the data-model pattern and marks it `sent`
  - a 429 → stays pending with `attempts+1` and `available_at` pushed back
  - a duplicate trigger of the same order event → no second outbox row (unique constraint)
  - Klaviyo down (ConnectionException) then up → delivered once (US7-AS3)
  - each mapped event in data-model.md § Event mapping creates the right `metric.name`
- [X] T131 [P] [US7] Feature test `packages/Webkul/Klaviyo/tests/Feature/HistoricalSyncTest.php`: with 3 customers, 2 orders within 12 months, 1 order 13 months old and 2 active products → POST sync → 3 SyncJob rows. Running the jobs → profiles 3, orders 2 (the old one excluded), catalog 2. `processed/total` are correct and the status endpoint reports them (US7-AS1).
- [X] T132 [P] [US7] Feature test `packages/Webkul/Klaviyo/tests/Feature/ConsentAndRoutingTest.php`:
  - `klaviyo:pull-consent` with the unsubscribed fixture → those contacts' email consent revoked with source `klaviyo`, and the cursor advanced
  - a store consent change (`marketing_automation.consent.changed`) → a subscription outbox row
  - routing `abandoned_cart = klaviyo` → an abandoned cart detected → no native enrolment (US7-AS4)
  - the routing select option `klaviyo` is rejected (422 on config save, or ignored → native) when there's no active connection for the channel

### Implementation for US7

- [X] T133 [US7] Create `packages/Webkul/Klaviyo/src/Services/KlaviyoClient.php` (bind, not singleton), constructed with a `Connection`.
  - `request(string $method, string $path, array $json = []): Response` uses base `https://a.klaviyo.com`, headers `Authorization: Klaviyo-API-Key {key}`, `revision: 2024-10-15`, `accept: application/vnd.api+json`, `content-type: application/vnd.api+json`, timeout 15.
  - Helpers: `account()`, `createEvent(array $attributes)`, `importProfile(array $attributes)`, `subscribeProfiles(string $listId, array $profiles, string $channel)`, `unsubscribeProfiles(...)`, `upsertCatalogItem(array $item)`, `deleteCatalogItem(string $externalId)`, `metrics()`, `events(array $filter, ?string $pageCursor)`.
  - Map the response to retryable (429/5xx/connection) or permanent (other 4xx). A 409 duplicate counts as success.
- [X] T134 [US7] Create `packages/Webkul/Klaviyo/src/Services/PayloadMapper.php` with methods `profile(Customer|Contact): array` (email, phone_number E.164, first_name, last_name, `properties.locale`, external_id = `{channel_code}:customer:{id}`), `placedOrder(Order): array`, `orderedProduct(Order, OrderItem): array`, `fulfilledOrder`, `cancelledOrder`, `refundedOrder(Refund)`, `viewedProduct(Product, Contact)`, `addedToCart(CartItem, Contact)`, `startedCheckout(Cart, Contact)` and `catalogItem(Product, Channel): array`. Each event payload is a JSON:API `{data: {type: 'event', attributes: {metric: {data: {type: 'metric', attributes: {name}}}, profile: {data: {type: 'profile', attributes: {...}}}, properties: {...}, value: total, time: ISO8601, unique_id}}}`, with the unique_id pattern `{channel_code}:{metric_slug}:{entity_id}[:{line_id}]` (data-model.md). Order properties: OrderId (increment_id), Categories, ItemNames, Items[] (ProductID, SKU, ProductName, Quantity, ItemPrice, RowTotal, ProductURL, ImageURL), DiscountCode, DiscountValue.
- [X] T135 [US7] Create `packages/Webkul/Klaviyo/src/Services/OutboxWriter.php` with `write(Connection $c, string $kind, string $uniqueId, array $payload): void` using `insertOrIgnore` on the unique `(connection_id, kind, unique_id)`. Create `packages/Webkul/Klaviyo/src/Listeners/StoreEventsListener.php`, registered in `packages/Webkul/Klaviyo/src/Providers/EventServiceProvider.php`. It maps core events to outbox writes for every active Connection of the event's channel (data-model.md § Event mapping):
  - `checkout.order.save.after` → profile + Placed Order + Ordered Product × items
  - `sales.order.update` status completed → Fulfilled Order
  - `sales.order.cancel.after` → Cancelled Order
  - `sales.refund.save.after` → Refunded Order
  - `checkout.cart.add.after` → Added to Cart (only if a contact with email is known)
  - `checkout.load.index` → Started Checkout
  - `catalog.product.update.after` → catalog_item upsert
  - `marketing_automation.consent.changed` (email/sms) → subscription row; skip when source is `klaviyo` to avoid echo loops
  - Viewed Product: product pages are response-cached (`cache.response` in `packages/Webkul/Shop/src/Routes/store-front-routes.php`), so server-side view hooks don't run on cached hits. Instead:
    - Add a public route `POST klaviyo/track/viewed-product` named `klaviyo.track.viewed-product` in `packages/Webkul/Klaviyo/src/Routes/shop-routes.php`, loaded with middleware `['web', 'shop', 'throttle:30,1']` and never cached. Body `{product_id}`.
    - The controller `packages/Webkul/Klaviyo/src/Http/Controllers/TrackController.php` resolves the contact from the logged-in customer, or else from the `ma_contact` cookie (contact uuid, set by MA in T092/T093; only read it here and look up the contact by `uuid`). Unknown visitor → 204 and nothing written. Otherwise it writes an outbox row with `unique_id` `{channel_code}:viewed-product:{product_id}:{contact_id}:{Y-m-d-H}` and returns 204.
    - Inject a static script via `Event::listen('bagisto.shop.products.view.after', ...)` with view `klaviyo::shop.viewed-product-beacon`. It calls `navigator.sendBeacon(url, new Blob([JSON.stringify({product_id, _token})], {type: 'application/json'}))` with the product id rendered in the page; the script is static per product, so it's safe in cached pages. Read the CSRF token from `meta[name=csrf-token]` at runtime. Keep CSRF protection on: `config/responsecache.php` already uses `Spatie\ResponseCache\Replacers\CsrfTokenReplacer`, so cached pages get a fresh token. Don't add this route to any CSRF exemption.
    - Add a test case to `packages/Webkul/Klaviyo/tests/Feature/OutboxTest.php` (T130): POST with a logged-in customer → 1 outbox row; a repeat within the same hour → still 1; an anonymous visitor → 0.

  After writing, dispatch `FlushOutboxBatch` with `->afterCommit()` on queue `klaviyo`.
- [X] T136 [US7] Create the job `packages/Webkul/Klaviyo/src/Jobs/FlushOutboxBatch.php` and command `packages/Webkul/Klaviyo/src/Console/Commands/FlushOutboxCommand.php` (`klaviyo:flush-outbox`, scheduled every minute, withoutOverlapping). Claim up to 200 `pending` rows with `available_at <= now()` (set `status=sending`). Send each through KlaviyoClient by kind:
  - success → `sent`, `sent_at`
  - retryable → `pending`, `attempts+1`, `available_at = now() + min(2^attempts, 60) minutes`
  - permanent or attempts ≥ 10 → `failed` with `last_error`

  Rows stuck in `sending` for more than 10 minutes go back to `pending`. Purge `sent` rows older than 30 days in the same command.
- [X] T137 [US7] Create the job `packages/Webkul/Klaviyo/src/Jobs/HistoricalSync.php` `(int $syncJobId)`. By entity:
  - customers: the `customers` table, ordered by id > cursor_id, chunks of 100 → profile import + subscription state from MA consents
  - orders: `orders` with `created_at >= now()-12 months` → Placed Order + Ordered Product outbox rows (same unique ids as live, so there are no duplicates)
  - catalog: active products (`product_flat` with status=1 for the channel; check the table) → catalog items

  Update `cursor_id` and `processed` after each chunk, and re-dispatch itself until done (so a crash resumes from the cursor). `total` is computed at start. Status: running → completed or failed.
- [X] T138 [US7] Create `packages/Webkul/Klaviyo/src/Console/Commands/PullConsentCommand.php` (`klaviyo:pull-consent`, every 15 min). For each connected Connection: if `unsubscribed_metric_ids` is empty, fetch `metrics()` and store the ids whose name starts with "Unsubscribed". Fetch `events` with `filter=any(metric_id,[...]),greater-than(datetime,{consent_cursor})` following `links.next`. For each event's profile email/phone, revoke the matching MA contact consent (email metric → Email, SMS metric → Sms) via `ConsentService::revoke(..., ConsentSource::Klaviyo)`. Advance `consent_cursor` to the max event datetime.
- [X] T139 [US7] Create `packages/Webkul/Klaviyo/src/Http/Controllers/Admin/KlaviyoController.php` with `index`, `connect`, `disconnect`, `sync`, `syncStatus` and `retryFailed` per contracts/admin-http.md § Klaviyo. Permission `marketing.automation.klaviyo`. Add routes in `packages/Webkul/Klaviyo/src/Routes/admin-routes.php` named `admin.marketing.klaviyo.index|connect|disconnect|sync|sync_status|retry_failed`. Create the view `packages/Webkul/Klaviyo/src/Resources/views/admin/index.blade.php`:
  - a per-channel connection card (API key password input, public key, connect/disconnect, status)
  - a historical sync card with 3 progress bars polling `sync_status` every 3 s while running
  - outbox stats and a "Retry failed" button
  - a link to MA routing settings

  The API key is never echoed back.
- [X] T140 [US7] Routing guard: in `packages/Webkul/MarketingAutomation/src/Services/Settings.php::routing()`, if the stored value is `klaviyo` but there's no active Klaviyo connection for the channel, return `Native` (check `class_exists(\Webkul\Klaviyo\Models\Connection::class)` and query it). This lets MA work without the Klaviyo module. When routing changes from native to klaviyo/disabled, cancel the active enrolments of flows with that template_key in that channel (`exit_reason=routing_disabled`): listen to `core.configuration.save.after` (check the exact event name with `grep -rn "configuration.save" packages/Webkul/Core packages/Webkul/Admin`).
- [X] T141 [US7] Run the US7 tests and fix until green: `php artisan test --compact --testsuite="Klaviyo Feature Test"`. Re-run `tests/Feature/Engine/EnrolmentTest.php` from MA. Run pint.

---

## Phase 10: Polish & Cross-Cutting Concerns

- [X] T142 [P] Playwright smoke test `tests/e2e-pw/marketing-automation/abandoned-cart.spec.ts`, following the existing specs in `tests/e2e-pw` for login helpers and config:
  - admin creates the Abandoned Cart flow from the template with a 1-minute delay and a whatsapp step replaced by email, then activates it
  - a guest adds a product, enters an email at checkout and leaves
  - run `php artisan automation:detect-abandoned-carts && php artisan automation:dispatch-due` via the test's setup hook, or document it as a manual step if the suite can't run artisan
  - with `MAIL_MAILER=log`, assert the log contains the restore URL, then visit it and assert the cart shows the product
- [X] T143 [P] Performance check for SC-006, `packages/Webkul/MarketingAutomation/tests/Feature/PerformanceTest.php`: with `DB::enableQueryLog()`, firing `checkout.cart.add.after` and `checkout.order.save.after` listeners in a request context runs ≤ 3 MA queries synchronously (everything else is queued). Seed **10,000** due enrolments (bulk `DB::table()->insert` in chunks of 1000, not factories one by one); one `automation:dispatch-due` run claims all of them within its 20 × 500 loop budget, `Queue::assertPushed(ProcessEnrolmentStep::class, 10000)`, and the run takes < 15 s with `Queue::fake()` (SC-003, SC-006). Also assert that `HealthCheck::report()['lag_over_5m_ratio']` is 0 after jobs run on time and > 0 after a 10-minute-late run.
- [X] T144 [P] Security review pass, recording fixes in code (no docs):
  - every admin controller action checks a permission
  - no `{!! !!}` on merchant data except the escaped block HTML from EmailBlockRenderer
  - tracking click only redirects to stored links
  - webhook signature verification is on for every driver that supports it
  - credentials are never logged (search `Log::` calls and `error_message` assignments, and strip auth headers/keys from stored errors)
  - throttles on the public shop POST routes

  Add a test `packages/Webkul/MarketingAutomation/tests/Feature/SecurityTest.php` asserting (a) an admin whose role has only `marketing.automation.flows` sees the Flows menu item and not Providers (render `admin.marketing.automation.flows.index` and check the sidebar HTML), and (b) each admin route returns 401 for a logged-in admin with no permissions (iterate `Route::getRoutes()` filtered by name prefix `admin.marketing.automation.`).
- [X] T145 [P] Complete the Arabic translations in `packages/Webkul/MarketingAutomation/src/Resources/lang/ar/app.php` and `packages/Webkul/Klaviyo/src/Resources/lang/ar/app.php`. Add a test `packages/Webkul/MarketingAutomation/tests/Feature/TranslationsTest.php` asserting that the en and ar key sets are identical (flatten with `Arr::dot`).
- [X] T146 Check that the Amacommerce theme renders every injected storefront piece (signup consent checkboxes, checkout contact capture, back-in-stock form, account preferences link). Add a feature test `packages/Webkul/MarketingAutomation/tests/Feature/ThemeHooksTest.php` that sets the channel theme to `amacommerce` (check the theme code in `config/themes.php`) and asserts each page's HTML contains the injected element ids (`ma-consent-checkboxes`, `ma-contact-capture`, `ma-back-in-stock`, `ma-preferences-link`; add those ids in the views).
- [X] T147 [P] Consent audit for SC-004: create `packages/Webkul/MarketingAutomation/src/Console/Commands/AuditConsentCommand.php` (`automation:audit-consent {--days=30}`) and register it in the service provider. It finds marketing, non-test `automation_message_logs` created within `--days` where either no `automation_consent_events` row with `status=granted` exists for that contact and `message_channel` with `created_at <= log.created_at`, or the latest consent event before `log.created_at` is `revoked`. Fallback sends count against the fallback channel. Print a table (log id, contact id, channel, sent at, reason) and exit with code 1 if any rows are found, else 0 with "No violations". Feature test `packages/Webkul/MarketingAutomation/tests/Feature/AuditConsentTest.php`: 1 compliant log + 1 violating log (sent after a revoke) → exit code 1 and only the violating id is listed; only compliant logs → exit 0.
- [X] T148 Final: run `vendor/bin/pint --dirty --format agent`, `php artisan test --compact --testsuite="MarketingAutomation Feature Test"`, `--testsuite="MarketingAutomation Unit Test"` and `--testsuite="Klaviyo Feature Test"`. Walk through `specs/002-marketing-automation/quickstart.md` steps 1–6 against a local install with sandbox credentials, or note which steps could not be run and why.

---

## Dependencies & Execution Order

### Phase dependencies

```
Phase 1 Setup ─▶ Phase 2 Foundational ─▶ Phase 3 US3 ─▶ Phase 4 US2 ─▶ Phase 5 US1 (MVP) ─▶ Phase 6 US4
                                                                   │
                                                                   ├─▶ Phase 7 US5 (needs US2 renderer; best after US4 for unsubscribe footer)
                                                                   ├─▶ Phase 8 US6 (needs US2 send pipeline + US4 webhook controller T102)
                                                                   └─▶ Phase 9 US7 (needs Phase 2 consent + US2 EnrolmentService routing; independent of US5/US6)
All ─▶ Phase 10 Polish
```

- **US3 before US2**: send steps need ProviderManager drivers and defaults (SendPolicy `no_provider`).
- **US2 before US1**: abandoned cart is a trigger plus template on top of the engine.
- **US4 after US1**: basic consent skipping is already in the engine (T068). US4 adds UI, unsubscribe, STOP, timing rules and GDPR. US4 can start right after US2 if staffed separately.
- **US7** can run in parallel with US5/US6 once Phase 5 is done.

### Within each story

Tests first (they fail) → models/services → controllers/routes → views → run the story's tests → pint.

### Cross-task contracts (stubs that later tasks fill in)

| Stub created in | Filled in by |
|---|---|
| `SendPolicy::applyTimingRules()` (T068) | T103 |
| `StatsRecorder::increment()` no-op (T063) | T118 |
| `MessageEvents::record()` no-op (T091) | T118 |
| `ProcessWebhook::applyStatusUpdates()` empty (T102) | T121 |
| `MessageRenderer` blocks text/button/cart_items/order_summary/footer only (T070) | T109 |
| `ContextBuilder` placeholders (T027) | T091 (restore_url), T101 (unsubscribe_url), T110 (coupon.code) |

---

## Parallel Execution Examples

**Phase 2** (after T010–T011): T012–T019 (8 migrations) in parallel. Then T020 → T021. In parallel: T022, T026, T028, T031, T032.

**US3**: T036, T037, T038 (tests) in parallel. Then T039–T046 (8 drivers, separate files) all in parallel. Then T047 → T048 → T049 → T050 → T051 → T052 → T053.

**US2**: T054–T059 (tests) in parallel. T066 and T067 in parallel after T065. T076, T077, T078 (triggers) in parallel after T063.

**US1**: T086 and T087 in parallel. Then T088 → T089 → T090 → T091 → T092 → T093.

**US4**: T095–T099 in parallel. Then T100, T101, T102, T103 in parallel (different files), then T104 → T105.

**US6**: T115–T117 in parallel. T119 and T120 in parallel after T118.

**US7**: T129–T132 in parallel after T128. T133 and T134 in parallel.

---

## Implementation Strategy

### MVP first

1. Phases 1–2 (foundation).
2. Phase 3 (US3 providers).
3. Phase 4 (US2 engine).
4. Phase 5 (US1 abandoned cart). **STOP and validate** with quickstart step 2. This is the shippable MVP.

### Incremental delivery

5. Phase 6 (US4): required before any production marketing sends (legal compliance).
6. Phase 7 (US5) and Phase 8 (US6) can ship separately.
7. Phase 9 (US7 Klaviyo) ships as a separate module release.
8. Phase 10 polish.

### Guidance for the implementing agent

- Do tasks strictly in ID order unless a task is marked [P] within the same phase.
- Before writing any file, read the sibling file named in §0 or in the task.
- When a task says "check X with grep", do it and adapt the code to what you find. Don't guess Bagisto event payloads or column names.
- If a test can't be made to pass because of a Bagisto behaviour the task didn't anticipate, adjust the implementation, not the test's intent. Record the deviation in a PHPDoc on the affected class.
- Never edit core `packages/Webkul/{Admin,Shop,Core,Checkout,Sales,Customer,...}` code. Only the registration files in Phase 1/T127 and `packages/Webkul/AmacommerceTheme` views may change outside the two new modules.
