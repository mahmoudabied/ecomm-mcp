<?php

namespace Amacraft\MarketingAutomation\Services\Providers;

use Amacraft\MarketingAutomation\Contracts\Providers\SupportsInboundOptOut;
use Amacraft\MarketingAutomation\Contracts\Providers\WhatsAppTemplateSource;
use Amacraft\MarketingAutomation\DTOs\InboundMessage;
use Amacraft\MarketingAutomation\DTOs\OutboundMessage;
use Amacraft\MarketingAutomation\DTOs\SendResult;
use Amacraft\MarketingAutomation\DTOs\StatusUpdate;
use Amacraft\MarketingAutomation\DTOs\WhatsAppTemplateCall;
use Amacraft\MarketingAutomation\DTOs\WhatsAppTemplateData;
use Amacraft\MarketingAutomation\Enums\WhatsAppTemplateStatus;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MetaCloudWhatsAppProvider extends AbstractHttpProvider implements SupportsInboundOptOut, WhatsAppTemplateSource
{
    /**
     * Describe the credential fields shown in the admin form.
     */
    public static function fields(): array
    {
        return [
            ['name' => 'access_token', 'label' => 'Access Token', 'type' => 'password', 'secret' => true, 'required' => true],
            ['name' => 'phone_number_id', 'label' => 'Phone Number ID', 'type' => 'text', 'secret' => false, 'required' => true, 'numeric' => true],
            ['name' => 'waba_id', 'label' => 'WABA ID', 'type' => 'text', 'secret' => false, 'required' => true, 'numeric' => true],
            ['name' => 'app_secret', 'label' => 'App Secret', 'type' => 'password', 'secret' => true, 'required' => true],
        ];
    }

    /**
     * Send a WhatsApp message through the Meta Cloud API.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult
    {
        try {
            $response = $this->http()
                ->withToken((string) ($connection->credentials['access_token'] ?? ''))
                ->post($this->messagesEndpoint($connection), $this->buildPayload($message));
        } catch (ConnectionException $exception) {
            return $this->classify($exception, fn () => ['', '']);
        }

        if ($response->successful() && filled($response->json('messages.0.id'))) {
            return SendResult::accepted((string) $response->json('messages.0.id'));
        }

        return $this->classifyMeta($response);
    }

    /**
     * Probe the connection by sending the approved hello_world template.
     *
     * Meta rejects free text outside a 24-hour conversation, so the probe
     * always uses a template — the synced one requested by the admin, when given.
     */
    public function testConnection(ProviderConnection $connection, string $to): SendResult
    {
        $templateName = request('template');

        if (filled($templateName)) {
            $template = $connection->templates()->where('name', $templateName)->where('status', WhatsAppTemplateStatus::Approved->value)->first();

            if ($template === null) {
                return SendResult::permanent(
                    'template_not_approved',
                    trans('marketing_automation::app.providers.template-not-approved', ['name' => $templateName]),
                );
            }

            $call = new WhatsAppTemplateCall(name: $template->name, language: $template->language);
        } else {
            $call = new WhatsAppTemplateCall(name: 'hello_world', language: 'en_US');
        }

        return $this->send($connection, new OutboundMessage(
            to: $to,
            channel: 'whatsapp',
            body: null,
            template: $call,
            messageUuid: (string) Str::uuid(),
        ));
    }

    /**
     * Verify the X-Hub-Signature-256 header on an inbound webhook.
     */
    public function verifyWebhook(Request $request, ProviderConnection $connection): bool
    {
        $expected = 'sha256='.hash_hmac(
            'sha256',
            (string) $request->getContent(),
            (string) ($connection->credentials['app_secret'] ?? ''),
        );

        return hash_equals($expected, (string) $request->header('X-Hub-Signature-256'));
    }

    /**
     * Parse delivery status updates from a Meta webhook payload.
     *
     * @return array<int, StatusUpdate>
     */
    public function parseStatusWebhook(Request $request, ProviderConnection $connection): array
    {
        $updates = [];

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $mapped = $this->mapStatus((string) ($status['status'] ?? ''));

                    if ($mapped === null) {
                        continue;
                    }

                    $error = $status['errors'][0] ?? null;

                    $updates[] = new StatusUpdate(
                        providerMessageId: (string) ($status['id'] ?? ''),
                        status: $mapped,
                        errorCode: isset($error['code']) ? (string) $error['code'] : null,
                        errorMessage: $error['message'] ?? null,
                        occurredAt: now()->toImmutable(),
                    );
                }
            }
        }

        return $updates;
    }

    /**
     * Parse an inbound WhatsApp message.
     */
    public function parseInbound(Request $request, ProviderConnection $connection): ?InboundMessage
    {
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $incoming) {
                    $body = $incoming['text']['body']
                        ?? $incoming['button']['text']
                        ?? null;

                    if (filled($body)) {
                        return new InboundMessage(
                            from: (string) ($incoming['from'] ?? ''),
                            body: (string) $body,
                            type: isset($incoming['button']) ? 'button' : 'text',
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * Fetch every message template of the WABA, following pagination. The
     * next-page cursor is copied out of the paging link, so the access_token
     * Meta embeds in it never lands in our own URLs or logs.
     *
     * @return array<int, WhatsAppTemplateData>
     */
    public function fetchTemplates(ProviderConnection $connection): array
    {
        $templates = [];

        $baseUrl = "https://graph.facebook.com/v21.0/{$connection->credentials['waba_id']}/message_templates";

        $after = null;

        $seenCursors = [];

        do {
            $query = ['limit' => '200'];

            if ($after !== null) {
                $query['after'] = $after;
            }

            $response = $this->http()
                ->withToken((string) ($connection->credentials['access_token'] ?? ''))
                ->get($baseUrl, $query);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    'Meta message_templates request failed ['.$response->status().'].',
                );
            }

            foreach ($response->json('data', []) as $template) {
                $templates[] = $this->mapTemplate($template);
            }

            $after = $this->cursorFromPagingLink((string) $response->json('paging.next', ''));

            if ($after === null || in_array($after, $seenCursors, true)) {
                break;
            }

            $seenCursors[] = $after;
        } while (true);

        return $templates;
    }

    /**
     * Extract the "after" cursor from a Meta paging link, if any.
     */
    protected function cursorFromPagingLink(string $link): ?string
    {
        if ($link === '') {
            return null;
        }

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        return isset($query['after']) && $query['after'] !== '' ? (string) $query['after'] : null;
    }

    /**
     * Build the Meta send payload for a template or free-text message.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(OutboundMessage $message): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => ltrim($message->to, '+'),
        ];

        if ($message->template === null) {
            $payload['type'] = 'text';
            $payload['text'] = ['preview_url' => false, 'body' => (string) $message->body];

            return $payload;
        }

        $payload['type'] = 'template';

        $templatePayload = [
            'name'     => $message->template->name,
            'language' => ['code' => $message->template->language],
        ];

        $parameters = [];

        foreach ($message->template->bodyParams as $parameter) {
            $parameters[] = ['type' => 'text', 'text' => (string) $parameter];
        }

        if ($parameters !== []) {
            $templatePayload['components'][] = ['type' => 'body', 'parameters' => $parameters];
        }

        foreach ($message->template->buttonParams as $index => $text) {
            $templatePayload['components'][] = [
                'type'       => 'button',
                'sub_type'   => 'quick_reply',
                'index'      => $index,
                'parameters' => [['type' => 'payload', 'payload' => (string) $text]],
            ];
        }

        if ($message->template->headerMediaUrl !== null) {
            $templatePayload['components'][] = [
                'type'       => 'header',
                'parameters' => [['type' => 'image', 'image' => ['link' => $message->template->headerMediaUrl]]],
            ];
        }

        $payload['template'] = $templatePayload;

        return $payload;
    }

    /**
     * Classify a Meta response using the Cloud API error codes.
     */
    protected function classifyMeta(Response $response): SendResult
    {
        $code = (string) $response->json('error.code', $response->status());
        $message = (string) $response->json('error.message', $response->body());

        if (in_array($code, ['4', '80007', '130429'], true)) {
            return SendResult::retryable($code, $message);
        }

        return $this->classify($response, fn () => [$code, $message]);
    }

    /**
     * Map a raw Meta template node onto the DTO.
     *
     * @param  array<string, mixed>  $template
     */
    protected function mapTemplate(array $template): WhatsAppTemplateData
    {
        $bodyComponent = collect($template['components'] ?? [])->firstWhere('type', 'BODY') ?? [];

        return new WhatsAppTemplateData(
            externalId: (string) ($template['id'] ?? ''),
            name: (string) ($template['name'] ?? ''),
            language: (string) ($template['language'] ?? ''),
            category: strtolower((string) ($template['category'] ?? '')),
            status: WhatsAppTemplateStatus::fromProvider((string) ($template['status'] ?? ''))->value,
            components: $template['components'] ?? [],
            variableCount: $this->countVariables((string) ($bodyComponent['text'] ?? '')),
        );
    }

    /**
     * Count the highest {{n}} placeholder used in a template body.
     */
    protected function countVariables(string $text): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $text, $matches);

        return $matches[1] !== [] ? max(array_map('intval', $matches[1])) : 0;
    }

    /**
     * Normalise a Meta webhook status string; unknown and intermediate
     * statuses (buffered, scheduled, warning…) are treated as no-ops.
     */
    protected function mapStatus(string $status): ?string
    {
        return match ($status) {
            'sent'      => 'sent',
            'delivered' => 'delivered',
            'read'      => 'read',
            'deleted'   => 'failed',
            'failed'    => 'failed',
            default     => null,
        };
    }

    /**
     * Build the messages endpoint URL for a phone number.
     */
    protected function messagesEndpoint(ProviderConnection $connection): string
    {
        return "https://graph.facebook.com/v21.0/{$connection->credentials['phone_number_id']}/messages";
    }
}
