<?php

namespace Webkul\MarketingAutomation\Services\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Webkul\MarketingAutomation\Contracts\Providers\SupportsInboundOptOut;
use Webkul\MarketingAutomation\Contracts\Providers\WhatsAppTemplateSource;
use Webkul\MarketingAutomation\DTOs\InboundMessage;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\DTOs\SendResult;
use Webkul\MarketingAutomation\DTOs\WhatsAppTemplateCall;
use Webkul\MarketingAutomation\DTOs\WhatsAppTemplateData;
use Webkul\MarketingAutomation\Enums\WhatsAppTemplateStatus;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Models\WhatsAppTemplate;
use Webkul\MarketingAutomation\Services\Providers\Concerns\TwilioCommon;

class TwilioWhatsAppProvider extends AbstractHttpProvider implements SupportsInboundOptOut, WhatsAppTemplateSource
{
    use TwilioCommon;

    /**
     * Describe the credential fields shown in the admin form.
     */
    public static function fields(): array
    {
        return self::twilioFields();
    }

    /**
     * Send a WhatsApp message through the Twilio Content API.
     */
    public function send(ProviderConnection $connection, OutboundMessage $message): SendResult
    {
        $params = [
            'To'   => $this->whatsappAddress($message->to),
            'From' => $connection->sender,
        ];

        if ($message->template !== null) {
            $params['ContentSid'] = $message->template->name;

            $variables = $this->indexedVariables($message->template);

            if ($variables !== []) {
                $params['ContentVariables'] = json_encode($variables);
            }
        } else {
            $params['Body'] = (string) $message->body;
        }

        if ($message->statusCallbackUrl) {
            $params['StatusCallback'] = $message->statusCallbackUrl;
        }

        return $this->postMessage($connection, $params);
    }

    /**
     * Probe the connection using the first approved synced template.
     */
    public function testConnection(ProviderConnection $connection, string $to): SendResult
    {
        /** @var ?WhatsAppTemplate $template */
        $template = $connection->templates()->where('status', WhatsAppTemplateStatus::Approved->value)->orderBy('id')->first();

        if ($template === null) {
            return SendResult::permanent(
                'no_template',
                trans('marketing_automation::app.providers.sync-templates-first'),
            );
        }

        return $this->send($connection, new OutboundMessage(
            to: $to,
            channel: 'whatsapp',
            body: null,
            template: new WhatsAppTemplateCall(name: $template->name, language: $template->language),
            messageUuid: (string) Str::uuid(),
        ));
    }

    /**
     * Verify the Twilio signature on an inbound webhook.
     */
    public function verifyWebhook(Request $request, ProviderConnection $connection): bool
    {
        return $this->verifyTwilioSignature($request, $connection);
    }

    /**
     * Parse a delivery status webhook.
     */
    public function parseStatusWebhook(Request $request, ProviderConnection $connection): array
    {
        return $this->parseTwilioStatus($request);
    }

    /**
     * Parse an inbound WhatsApp message.
     */
    public function parseInbound(Request $request, ProviderConnection $connection): ?InboundMessage
    {
        return $this->parseTwilioInbound($request);
    }

    /**
     * Fetch content items together with their WhatsApp approval status,
     * following the pagination links of the ContentAndApprovals API.
     *
     * @return array<int, WhatsAppTemplateData>
     */
    public function fetchTemplates(ProviderConnection $connection): array
    {
        $sid = (string) ($connection->credentials['account_sid'] ?? '');
        $token = (string) ($connection->credentials['auth_token'] ?? '');

        $templates = [];

        $url = 'https://content.twilio.com/v1/ContentAndApprovals?PageSize=100';

        while ($url) {
            $response = $this->http()->withBasicAuth($sid, $token)->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    'Twilio ContentAndApprovals request failed ['.$response->status().'].',
                );
            }

            foreach ($response->json('contents', []) as $content) {
                $approval = $content['approval_requests']['whatsapp'] ?? [];

                // The Content SID doubles as the name, because the send call
                // passes `template->name` straight through as the ContentSid.
                $templates[] = new WhatsAppTemplateData(
                    externalId: (string) ($content['sid'] ?? ''),
                    name: (string) ($content['sid'] ?? ''),
                    language: (string) ($content['language'] ?? ''),
                    category: strtolower((string) ($approval['category'] ?? '')),
                    status: WhatsAppTemplateStatus::fromProvider((string) ($approval['status'] ?? ''))->value,
                    components: $content['types'] ?? [],
                    variableCount: count($content['variables'] ?? []),
                );
            }

            $url = $response->json('meta.next_page_url');
        }

        return $templates;
    }

    /**
     * Re-key named template variables as a "1"-based JSON object for the
     * ContentVariables payload.
     *
     * @return array<string, string>
     */
    protected function indexedVariables(WhatsAppTemplateCall $template): array
    {
        $variables = [];

        foreach (array_values($template->bodyParams) as $index => $value) {
            $variables[(string) ($index + 1)] = (string) $value;
        }

        return $variables;
    }

    /**
     * Prefix a phone number for the WhatsApp channel.
     */
    protected function whatsappAddress(string $phone): string
    {
        return 'whatsapp:+'.ltrim($phone, '+');
    }
}
