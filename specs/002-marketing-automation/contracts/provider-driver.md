# Contract: Messaging Provider Driver (PHP)

Namespace `Webkul\MarketingAutomation\Contracts\Providers`. Adding a provider = implement the interface(s) + register in `config/automation-providers.php`. Flows never reference drivers directly (FR-015).

```php
interface MessagingProvider
{
    /** Credential field schema shown in the admin form. @return array<int, array{name: string, label: string, type: 'text'|'password'|'select', secret: bool, required: bool, options?: array<string,string>}> */
    public static function fields(): array;

    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult;

    public function testConnection(ProviderConnection $connection, string $to): SendResult;

    public function verifyWebhook(Request $request, ProviderConnection $connection): bool;

    /** @return array<int, StatusUpdate> */
    public function parseStatusWebhook(Request $request, ProviderConnection $connection): array;
}

interface SupportsInboundOptOut
{
    public function parseInbound(Request $request, ProviderConnection $connection): ?InboundMessage;
}

interface WhatsAppTemplateSource
{
    /** @return array<int, WhatsAppTemplateData> */
    public function fetchTemplates(ProviderConnection $connection): array;
}
```

## DTOs (readonly classes)

- `OutboundMessage { string $to (E.164); string $channel ('sms'|'whatsapp'); ?string $body; ?WhatsAppTemplateCall $template; string $messageUuid; ?string $statusCallbackUrl; }`
- `WhatsAppTemplateCall { string $name; string $language; array<int,string> $bodyParams; array<int,string> $buttonParams; ?string $headerMediaUrl; }`
- `SendResult { SendStatus $status ('accepted'|'retryable_error'|'permanent_error'); ?string $providerMessageId; ?string $errorCode; ?string $errorMessage (human-readable); }`
- `StatusUpdate { string $providerMessageId; string $status ('sent'|'delivered'|'read'|'failed'|'undelivered'); ?string $errorCode; ?string $errorMessage; CarbonImmutable $occurredAt; }`
- `InboundMessage { string $from; string $body; ?string $type ('text'|'button'|'opt_out'); }`

## Error classification rules (all drivers)

| Condition | SendStatus |
|---|---|
| HTTP 2xx with provider id | accepted |
| Timeout, connection error, HTTP 429, HTTP 5xx | retryable_error |
| Auth failure (401/403), invalid number, unsubscribed/blocked recipient, template not approved, insufficient balance | permanent_error (→ fallback channel if configured, FR-003a) |

## Launch drivers

| Key | Channel | Credentials | Send endpoint |
|---|---|---|---|
| `twilio` | sms | account_sid, auth_token, messaging_service_sid? | `POST https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json` |
| `vonage` | sms | api_key, api_secret | `POST https://rest.nexmo.com/sms/json` |
| `messagebird` | sms | access_key | `POST https://rest.messagebird.com/messages` |
| `unifonic` | sms | app_sid | `POST https://el.cloud.unifonic.com/rest/SMS/messages` |
| `msegat` | sms | username, api_key | `POST https://www.msegat.com/gw/sendsms.php` |
| `taqnyat` | sms | bearer_token | `POST https://api.taqnyat.sa/v1/messages` |
| `meta_cloud` | whatsapp | access_token, phone_number_id, waba_id, app_secret | `POST https://graph.facebook.com/v21.0/{phone_number_id}/messages`; templates `GET /{waba_id}/message_templates` |
| `twilio_whatsapp` | whatsapp | account_sid, auth_token, from (`whatsapp:+…`) | Messages API with `ContentSid` + `ContentVariables`; templates `GET https://content.twilio.com/v1/Content` + approval status |

Each driver ships with JSON fixtures in `tests/Fixtures/Providers/{driver}/` (success, auth error, invalid number, rate limit, status webhook, inbound STOP) used with `Http::fake()`.
