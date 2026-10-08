# Contract: Storefront & Public HTTP

Middleware `web` + Bagisto shop middleware (theme, locale, currency) unless noted. Tracking/webhook routes are excluded from CSRF and response cache (`spatie/laravel-responsecache` doNotCacheResponse).

## Customer-facing

| Method & path | Name | Behavior |
|---|---|---|
| GET `/automation/cart/restore/{token}` | `shop.automation.cart.restore` | Valid & unexpired: guest + guest cart → set it as the session cart; guest + customer-owned cart → copy its items into a new guest cart without login, save `restored_cart_id`, and deactivate the original; logged-in customer → activate or merge into their cart; record click on message `?m={uuid}` if present, redirect 302 → `shop.checkout.cart.index`. Invalid/expired: 302 → cart page with flash `automation::app.shop.restore.expired` (FR-012). |
| POST `/automation/checkout-contact` | `shop.automation.checkout-contact` | `{email?, phone?, consents?:{email:bool,sms:bool,whatsapp:bool}}` — attaches contact to current cart; throttled 10/min/session. 204. |
| GET `/automation/unsubscribe/{token}` | `shop.automation.unsubscribe` | Confirmation page listing channel being revoked. |
| POST `/automation/unsubscribe/{token}` | `shop.automation.unsubscribe.confirm` | Revokes consent (source `unsubscribe_link`) immediately; also handles RFC 8058 one-click (`List-Unsubscribe=One-Click` body, no CSRF). 200 page / 200 empty for one-click (FR-023, US4-AS2). |
| POST `/automation/back-in-stock` | `shop.automation.back-in-stock` | `{product_id, email?, phone?, consent_channel}` → 201; idempotent per contact+product. |
| GET `/customer/account/marketing-preferences/export` | `shop.customers.account.marketing-preferences.export` | auth:customer. JSON download (`marketing-data.json`) of `{consents, consent_events, enrolments, messages}` for the logged-in customer (FR-025). |
| POST `/klaviyo/track/viewed-product` | `klaviyo.track.viewed-product` | Klaviyo module. `{product_id}` sent by `navigator.sendBeacon` (CSRF on; token comes from the page meta tag, which responsecache's `CsrfTokenReplacer` refreshes). Contact from the logged-in customer or the `ma_contact` cookie. Writes 1 outbox row per contact, product and hour. Unknown visitor → 204 with nothing written. `throttle:30,1`, never cached. |
| GET/PUT `/customer/account/marketing-preferences` | `shop.customers.account.marketing-preferences.*` | auth:customer. View/update per-channel consent (FR-021, source `account`). |

`{token}` = `base64url(contact_uuid . "." . channel . "." . message_uuid)` + `.` + HMAC-SHA256 (APP_KEY) — verified with `hash_equals`.

## Tracking (no session, no locale middleware)

| Method & path | Behavior |
|---|---|
| GET `/automation/t/o/{messageUuid}.{sig}.gif` | 1×1 GIF, `Cache-Control: no-store`; queues `opened` event (first open sets `opened_at`). |
| GET `/automation/t/c/{messageUuid}.{sig}/{linkHash}` | 302 to `links[linkHash]` of that message only (404 if unknown — no open redirect); queues `clicked`. |

## Provider webhooks (no session, CSRF-exempt)

`POST /automation/webhooks/{driver}/{connectionUuid}` (and `GET` for Meta verification handshake `hub.mode=subscribe&hub.verify_token=&hub.challenge=` → echo challenge when token = `webhook_secret`).

- Verification per driver (`verifyWebhook`): Meta `X-Hub-Signature-256` (app secret), Twilio `X-Twilio-Signature`, Vonage signed JWT/`sig`, MessageBird `MessageBird-Signature-JWT`, regional gateways: secret in URL path (`connectionUuid` + `?s=webhook_secret`).
- Body parsed into `StatusUpdate[]` (provider_message_id, status, error_code, error_message, occurred_at) and/or `InboundMessage` (from, body). Inbound body matching `STOP|UNSUBSCRIBE|إلغاء|الغاء|توقف` (case-insensitive, trimmed) → revoke SMS/WhatsApp consent (source `sms_stop` / `whatsapp_optout`). Meta "STOP" quick-reply and opt-out events treated likewise.
- Always 200 quickly; processing queued. Unknown connection → 404. Bad signature → 403.
