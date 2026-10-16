# Contract: Admin HTTP (MarketingAutomation + Klaviyo)

Prefix: `config('app.admin_url')` (default `/admin`) + `/marketing/automation`. Middleware: `web`, `admin`, ACL `bouncer()->hasPermission(<key>)`. Responses for JSON endpoints follow Bagisto admin convention: `{ "message": string, "data": ... }`; validation errors 422 `{ message, errors: { field: [..] } }`. DataGrid endpoints return Bagisto DataGrid JSON when called via AJAX.

## ACL keys (FR-039) — added to `acl.php`

| Key | Grants |
|---|---|
| `marketing.automation.flows` | view flows list & builder (read-only) |
| `marketing.automation.flows.manage` | create/edit/duplicate/activate/pause/delete, send test |
| `marketing.automation.reports` | flow reports & journey logs |
| `marketing.automation.settings` | providers, WhatsApp templates, sending settings, routing |
| `marketing.automation.klaviyo` | Klaviyo connection & sync |

## Flows

| Method & path | Name | ACL | Body / Notes |
|---|---|---|---|
| GET `/flows` | `admin.marketing.automation.flows.index` | flows | DataGrid: name, trigger, type, status, channels, enrolled (30d), revenue (30d) |
| GET `/flows/templates` | `.flows.templates` | flows.manage | list of prebuilt templates `[ {key, name, description, trigger_type, type} ]` |
| POST `/flows` | `.flows.store` | flows.manage | `{name, template_key?, trigger_type, trigger_config, type, channel_ids[], reentry_window_hours, exit_goals[]}` → 201 `{data: Flow}` (status `draft`) |
| GET `/flows/{id}/edit` | `.flows.edit` | flows | builder page (Blade + Vue) |
| PUT `/flows/{id}` | `.flows.update` | flows.manage | same fields as store + `draft_definition` (see data-model step schema) |
| POST `/flows/{id}/activate` | `.flows.activate` | flows.manage | validates & publishes version → 200 `{data:{status:"active", version}}`; 422 `{message, errors:{"steps.s3":["WhatsApp template not approved"]}}` (US2-AS5) |
| POST `/flows/{id}/pause` | `.flows.pause` | flows.manage | |
| POST `/flows/{id}/resume` | `.flows.resume` | flows.manage | |
| POST `/flows/{id}/duplicate` | `.flows.duplicate` | flows.manage | new draft copy |
| DELETE `/flows/{id}` | `.flows.delete` | flows.manage | cancels active enrolments |
| POST `/flows/{id}/steps/{stepKey}/test` | `.flows.test` | flows.manage | `{recipient (email or phone), locale, sample: "sample"\|"customer", customer_id?}` → 200 `{data:{message_log_id, status}}` (FR-009) |
| GET `/flows/{id}/preview/{stepKey}` | `.flows.preview` | flows | `?locale=&customer_id=` → rendered HTML/text |

## Reports (US6)

| Method & path | ACL | Notes |
|---|---|---|
| GET `/flows/{id}/report` | reports | `?from=YYYY-MM-DD&to=&channel_id=` → `{data:{flow:{enrolled,sent,delivered,opened,clicked,skipped,failed,unsubscribed,converted,revenue}, steps:[{step_key, type, ...same metrics, skipped_by_reason:{}}]}}` |
| GET `/flows/{id}/enrolments` | reports | DataGrid of enrolments (contact, status, entered, current step) |
| GET `/enrolments/{id}/journey` | reports | `{data:{enrolment, events:[{step_key, event, reason, message:{channel, status, error_message, recipient_masked}, created_at}]}}` |

## Providers & settings (US3)

| Method & path | Notes |
|---|---|
| GET `/providers` | DataGrid + available drivers `[ {driver, channel, label, fields:[{name, type, secret:bool, required}]} ]` |
| POST `/providers` | `{name, message_channel, driver, credentials:{...}, sender}` |
| PUT `/providers/{id}` | secret fields omitted/empty ⇒ keep existing |
| GET `/providers/{id}` | credentials returned masked `{"auth_token":"••••a91f"}` (FR-017) + `webhook_url` |
| POST `/providers/{id}/test` | `{to}` → 200 `{data:{status:"connected"}}` or 422 `{message:"<provider error in plain language>"}` (US3-AS1/2) |
| POST `/providers/{id}/sync-templates` | WhatsApp only → `{data:{synced:n, templates:[...]}}` (US3-AS3) |
| DELETE `/providers/{id}` | blocked (422) while referenced by an active flow's channel default |
| PUT `/providers/defaults` | `{channel_id, message_channel, provider_connection_id}` (FR-016) |
| GET `/health` | `{data:{queue_driver, queue_ok, scheduler_last_seen_at, scheduler_ok, mail_configured}}` (setup check) |

Sending settings, quiet hours, caps, retention, attribution windows and routing are core configuration under `admin.configuration.index` (`marketing_automation/*`), not custom endpoints.

## Klaviyo (US7) — prefix `/marketing/klaviyo`, ACL `marketing.automation.klaviyo`

| Method & path | Notes |
|---|---|
| GET `/` | connection status per channel, sync jobs progress, outbox stats `{pending, failed, sent_24h}` |
| POST `/connect` | `{channel_id, api_key, public_key?}` → validates via `GET /api/accounts` → 200/422 |
| POST `/disconnect` | `{channel_id}` |
| POST `/sync` | `{channel_id, entities:["customers","orders","catalog"]}` → starts historical sync jobs |
| GET `/sync/status` | `{data:[{entity, status, processed, total}]}` (polled by UI) |
| POST `/outbox/retry-failed` | re-queues failed outbox rows |
