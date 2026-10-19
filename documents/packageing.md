# Marketing Automation — production & Bagisto Extension Store readiness

## Context
The MarketingAutomation (MA) and Klaviyo (KL) packages are feature-complete: all 148 spec tasks are done, and 371 of 373 tests pass. They still can't be sold yet:
- They can't be installed into a stock Bagisto store. There's no package `composer.json`, they're wired in by editing host files, and they depend on spatie/multitenancy.
- They have consent and data-leak bugs that create legal exposure for buyers.
- There are no buyer docs.

The goal is a single listing: "Marketing Automation (with optional Klaviyo connector)" for **Bagisto 2.3.x**, installable with `composer require` plus one install command, under the seller's **own vendor namespace**.

Decisions made with the user:
- Own vendor, not `Webkul\`.
- Klaviyo bundled as an optional sub-package.
- Bagisto 2.3.x only.
- Tenancy removed from the package.

> Assumed vendor: `Amacraft` → `Amacraft\MarketingAutomation`, `Amacraft\Klaviyo`, composer names `amacraft/bagisto-marketing-automation` and `amacraft/bagisto-klaviyo`. Confirm before Phase 1.

---

## Phase 1 — Make it a real, installable package (blockers)

**1.1 Move and rename the namespace**
- Move `packages/Webkul/MarketingAutomation` to `packages/Amacraft/MarketingAutomation`, and `packages/Webkul/Klaviyo` to `packages/Amacraft/Klaviyo`, using `git mv`.
- Rewrite the namespace in about 288 PHP files, plus `config/concord.php`, `bootstrap/providers.php`, `tests/Pest.php` and the root `composer.json` (lines 89, 92, 118, 119).
- Keep the view and lang namespaces `marketing_automation::` and `klaviyo::` unchanged (about 490 refs).
- Keep table names, config codes (`marketing_automation.*`) and ACL keys. This means no data migration for the existing dev install.

**1.2 Give each package its own `composer.json`**
- `name`, `type: library`, `license: proprietary`, `version` via tags.
- `require`: `php ^8.2`, `illuminate/support ^11.0`, `giggsey/libphonenumber-for-php-lite ^9.0`. KL also requires `amacraft/bagisto-marketing-automation`.
- PSR-4 for `src/` (factories live under `src/Database/Factories`) and `autoload-dev` for `tests/`.
- `extra.laravel.providers` lists `MarketingAutomationServiceProvider` / `KlaviyoServiceProvider`, so they are auto-discovered.
- `archive.exclude`: `tests`, `.github`, `*.md` drafts. Add a `.gitattributes` `export-ignore` as well.

**1.3 Concord self-registration**
- Each app service provider registers its `ModuleServiceProvider` into concord at runtime, so buyers don't edit `config/concord.php`. Check the Concord API (`$this->app->make(ConcordContract::class)->registerModule(...)`) or the existing `CoreModuleServiceProvider` pattern (`packages/Webkul/Core/src/Providers/CoreModuleServiceProvider.php:15-32`).
- Remove the manual entries from `config/concord.php:34,36` and `bootstrap/providers.php:73-74`.
- Switch the host root `composer.json` to a `path` repository plus `require`, so this repo installs the packages the same way a buyer does.

**1.4 Remove the spatie multitenancy coupling**
- Drop `implements NotTenantAware` and its `use` from the 9 jobs:
  - MA `Jobs/{ProcessWebhook,ProcessEnrolmentStep,ProcessBackInStockSubscriptions,SyncWhatsAppTemplates,AttributeOrder,IngestTrigger,RecordMessageEvent}.php`
  - KL `Jobs/{FlushOutboxBatch,HistoricalSync}.php`
- In the host app (not the package), add those classes to spatie's `config/multitenancy.php` `not_tenant_aware_jobs`.

**1.5 Configurable queues**
- Add `src/Config/marketing-automation.php` with `queues.automation` (env `MA_QUEUE`, default `automation`) and `queues.klaviyo` (env `KLAVIYO_QUEUE`, default `klaviyo`). Allow `null` to mean "use the default connection queue".
- Replace every `onQueue('automation'|'klaviyo')` with `config(...)`. The call sites are the 9 jobs above, `KlaviyoController.php:183` and `OutboxWriter.php:58`.

**1.6 Publishables and install/uninstall**
- `publishes()` tags for `ma-config`, `ma-views` and `ma-lang`.
- Extend the existing `automation:install` (`Console/Commands/InstallCommand.php`) so it:
  - runs migrations,
  - seeds templates,
  - prints a checklist covering the cron, `queue:work --queue=automation,klaviyo,default`, and the `APP_URL`/TrustProxies note for Twilio,
  - checks the scheduler heartbeat with the existing `HealthCheck`.
- Add `automation:uninstall --force`, which rolls back the package's migrations by path and clears config rows `marketing_automation.%` and `klaviyo.%`.

## Phase 2 — Security & correctness fixes (must-fix before sale)
Each fix gets a regression test in the existing `tests/Feature` suites.

**2.1 Consent forgery and contact hijack**
Affects `Shop/CheckoutContactController.php:33`, `BackInStockController.php:24`, `Services/ContactResolver.php:148-163` and `ConsentService.php`.
- Add `ConsentStatus::Pending` and **double opt-in** for requests that aren't authenticated:
  - email: a signed confirmation link by mail;
  - SMS/WhatsApp: a confirmation message with a link through the provider.
- A system config toggle `consent.double_opt_in` defaults to on. Logged-in customers still grant directly.
- Reuse the existing HMAC token pattern from the unsubscribe/restore links.
- In `ContactResolver`, add a `trusted: bool` flag. Unauthenticated callers must not fill `phone_e164` or `customer_id` on an existing contact that was matched by email, or the other way round. When the data conflicts, they get a separate unlinked contact.
- `ma_contact` cookie: only set it to an existing contact's uuid when the request is authenticated as that customer. Otherwise use the new or isolated contact.
- `SendPolicy::check` treats `Pending` as no consent.

**2.2 Klaviyo cross-channel leak**
- In `Klaviyo/src/Jobs/HistoricalSync.php:96`, add `->where('channel_id', $job->connection->channel_id)` to the customer query.
- Apply the same filter to the count shown in `KlaviyoController` `countEntity`.

**2.3 Klaviyo unsubscribe pull is a no-op**
- `KlaviyoClient::events` (`KlaviyoClient.php:162`) should pass `include=profile,metric`.
- `PullConsentCommand::revokeForEvent` (lines 119-145) should resolve profile and metric from the top-level `included` array by relationship id.
- Add a test with a realistic JSON:API fixture under `tests/Fixtures`.

**2.4 Back-in-stock never sends to guests**
- Change the template in `Database/Seeders/FlowTemplates.php:183` to `'type' => 'transactional'`, since the subscription is the consent.
- Add a migration or install step that updates existing seeded flows.
- On re-subscribe, rotate the trigger key (`bis:{id}:{ulid}`) so the unique enrolment index doesn't block it.
- Validate the phone with libphonenumber and the cart or channel country.
- Throttle per IP and per phone, for example 3 a day, to block SMS pumping.

**2.5 Stored XSS in the admin report**
- In `Resources/views/admin/flows/report.blade.php:176-184`, build nodes with `textContent` or `createElement` instead of string `innerHTML`.

**2.6 Wrong store on non-default channels**
- In `Rendering/ContextBuilder.php:107-120`, load the `Channel` by `$channelId` and use its `name` and `hostname`.
- Add a small `ChannelUrl` helper that builds absolute URLs on the channel hostname (falling back to `APP_URL`) for the store, restore, unsubscribe and tracking links.

**2.7 Scheduler lock outlives a crash**
- In `MarketingAutomationServiceProvider.php:92-96` and `KlaviyoServiceProvider.php:58-64`, use `withoutOverlapping(10)` plus `onOneServer()`.

**2.8 Lower-severity fixes**
- Restoring a guest cart copies only the items, not the addresses (`CartRestoreController.php:77`).
- Make Twilio signature checks use a configurable public base URL when TrustProxies isn't set (`TwilioCommon.php:65`).
- Validate the Meta `waba_id` and `phone_number_id` as digits.

**2.9 Fragile tests**
- In `Klaviyo/tests/Feature/ConsentAndRoutingTest.php:~120` and `MarketingAutomation/tests/Feature/Engine/EnrolmentTest.php:~140`, use `CoreConfig::updateOrCreate` so they pass on a DB that already has saved settings.

## Phase 3 — Works on a stock store
- **Storefront CSS:** the 7 MA shop views (`Resources/views/shop/**`) use Tailwind classes that may be purged from the stock Shop bundle. Ship a small precompiled `ma-shop.css`, published to `public/vendor/marketing-automation`, built with the package's own Tailwind content globs. Inject it with the `bagisto.shop.layout.head` view event. Do the same check for the admin builder views against the stock Admin bundle.
- **Theme tests:** in `tests/Feature/ThemeHooksTest.php:18`, use a dataset over `['default', 'amacommerce']` (amacommerce only when installed).
- **Clean install verification:** a fresh `bagisto/bagisto` 2.3.x in a scratch dir, plus a composer `path` repo pointing at the packages. Run `composer require` → `php artisan automation:install` → the quickstart flows.

## Phase 4 — Listing assets
In each package root:
- `README.md`: features, requirements, installation, cron/queue setup, provider setup per driver (Twilio, Vonage, Meta, Unifonic and the rest), Klaviyo setup, upgrade and uninstall, FAQ.
- `CHANGELOG.md` starting at `1.0.0`.
- `LICENSE`, the commercial EULA.

Also needed for the store listing:
- screenshots (flow builder, report, providers, Klaviyo);
- a short demo video;
- the supported version matrix (Bagisto 2.3.x, PHP 8.2–8.4, MySQL 8);
- the languages shipped (en, ar), with the fallback stated.

Release:
- Git tag `v1.0.0`, built with `composer archive`.
- The zip contains only `packages/Amacraft/*` with no tests, specs, `test-*.mjs` or build artifacts of the Amacommerce theme.

---

## Verification
1. `vendor/bin/pint --dirty --format agent`.
2. `php artisan test --compact --testsuite="MarketingAutomation Feature Test,MarketingAutomation Unit Test,Klaviyo Feature Test"` must be all green, including the new regression tests:
   - consent forgery rejected or pending;
   - customer sync filtered by channel;
   - Klaviyo pull revokes using the `included` fixture;
   - a guest back-in-stock subscriber gets a message;
   - journey drawer escaping;
   - channel-specific URLs and store name;
   - `automation:uninstall`.
3. Check the archive with `composer validate` in each package. `composer archive` should produce a zip without tests.
4. Clean Bagisto 2.3.x install:
   - Install from the zip.
   - Run `automation:install` and `queue:work`.
   - Run the abandoned-cart flow with `MAIL_MAILER=log`.
   - Confirm the restore link works on the default theme with styled snippets.
   - Run `automation:uninstall` and confirm the tables are gone.
5. Run the existing Playwright spec `tests/e2e-pw/marketing-automation/abandoned-cart.spec.ts` against this repo after the rename.

## Suggested order and commits
1. Phase 1.1 (rename only), as its own commit.
2. 1.2–1.6.
3. Phase 2, one commit per fix.
4. Phase 3.
5. Phase 4.
