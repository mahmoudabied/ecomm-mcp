<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Amacraft\MarketingAutomation\DTOs\OutboundMessage;
use Amacraft\MarketingAutomation\Enums\FlowType;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\MessageStatus;
use Amacraft\MarketingAutomation\Enums\SkipReason;
use Amacraft\MarketingAutomation\Enums\StepType;
use Amacraft\MarketingAutomation\Exceptions\RetryableSendException;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Rendering\ContextBuilder;
use Amacraft\MarketingAutomation\Rendering\MessageRenderer;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Amacraft\MarketingAutomation\Services\MessageSender;
use Amacraft\MarketingAutomation\Services\PhoneNormalizer;
use Amacraft\MarketingAutomation\Services\ProviderManager;
use Amacraft\MarketingAutomation\Services\UnsubscribeLink;

class FlowTestController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ContextBuilder $contextBuilder,
        protected MessageRenderer $renderer,
        protected MessageSender $sender,
        protected ProviderManager $providerManager,
        protected PhoneNormalizer $phoneNormalizer,
        protected UnsubscribeLink $unsubscribeLink,
    ) {}

    /**
     * Send a single test message for one step of the draft.
     */
    public function send(Flow $flow, string $stepKey): JsonResponse
    {
        if (! bouncer()->hasPermission('marketing.automation.flows.manage')) {
            abort(401, 'This action is unauthorized.');
        }

        $data = request()->validate([
            'recipient'   => ['required', 'string', 'max:191'],
            'locale'      => ['nullable', 'string', 'max:10'],
            'sample'      => ['required', 'in:sample,customer'],
            'customer_id' => ['required_if:sample,customer', 'nullable', 'integer', 'exists:customers,id'],
        ]);

        $step = ($flow->draft_definition ?? [])['steps'][$stepKey] ?? null;

        if ($step === null || ! in_array($step['type'] ?? '', [StepType::SendEmail->value, StepType::SendSms->value, StepType::SendWhatsapp->value], true)) {
            return response()->json([
                'message' => trans('marketing_automation::app.flows.test.not-sendable'),
            ], 422);
        }

        $channel = $flow->channels->first() ?? core()->getDefaultChannel();

        $locale = $data['locale'] ?? config('app.locale');

        $enrolment = $data['sample'] === 'customer'
            ? $this->syntheticEnrolment($flow, (int) $data['customer_id'], $channel->id)
            : null;

        $context = $enrolment !== null
            ? $this->contextBuilder->forEnrolment($enrolment)
            : $this->contextBuilder->forSample($channel->id, $locale);

        $messageChannel = $this->channelFor($step['type']);

        $log = MessageLog::query()->create([
            'flow_id'         => $flow->id,
            'step_key'        => $stepKey,
            'contact_id'      => null,
            'channel_id'      => $channel->id,
            'message_channel' => $messageChannel->value,
            'is_marketing'    => $flow->type === FlowType::Marketing,
            'is_test'         => true,
            'recipient'       => $data['recipient'],
            'locale'          => $locale,
            'status'          => MessageStatus::Queued->value,
        ]);

        match ($messageChannel) {
            MessageChannel::Email => $this->sendTestEmail($log, $step, $context, $locale, $enrolment?->contact?->uuid),
            default               => $this->sendTestProviderMessage($log, $step, $context, $locale, $channel, $messageChannel),
        };

        return response()->json([
            'message' => trans('marketing_automation::app.flows.test.sent'),
            'data'    => [
                'message_log_id' => $log->id,
                'status'         => $log->refresh()->status->value,
            ],
        ]);
    }

    /**
     * Render and send the test email.
     */
    protected function sendTestEmail(MessageLog $log, array $step, array $context, string $locale, ?string $contactUuid): void
    {
        $unsubscribeUrl = $this->unsubscribeLink->forEmail($log, $contactUuid);

        $rendered = $this->renderer->renderEmail($step, [
            ...$context,
            'unsubscribe_url' => $unsubscribeUrl,
        ], $locale);

        $log->update(['rendered_body' => $rendered['html']]);

        $this->sender->sendEmail($log, $rendered, $unsubscribeUrl);
    }

    /**
     * Render and send a test SMS or WhatsApp message.
     */
    protected function sendTestProviderMessage(
        MessageLog $log,
        array $step,
        array $context,
        string $locale,
        $channel,
        MessageChannel $messageChannel,
    ): void {
        $connection = $this->providerManager->defaultFor($channel->id, $messageChannel);

        if ($connection === null) {
            $log->update(['status' => MessageStatus::Failed->value, 'error_code' => SkipReason::NoProvider->value]);

            return;
        }

        $log->update(['provider_connection_id' => $connection->id]);

        $to = $this->phoneNormalizer->normalize($log->recipient)
            ?? $log->recipient;

        $message = match ($messageChannel) {
            MessageChannel::Sms => new OutboundMessage(
                to: $to,
                channel: $messageChannel->value,
                body: $this->renderer->renderText($step, $context, $locale),
                template: null,
                messageUuid: $log->uuid,
            ),
            MessageChannel::Whatsapp => new OutboundMessage(
                to: $to,
                channel: $messageChannel->value,
                body: null,
                template: $this->renderer->renderWhatsApp($step, $context, $locale),
                messageUuid: $log->uuid,
            ),
        };

        try {
            $this->sender->sendProvider($log, $message);
        } catch (RetryableSendException) {
            // The test send surfaces the provider error straight away.
        }
    }

    /**
     * Map a step type to its message channel.
     */
    protected function channelFor(string $stepType): MessageChannel
    {
        return match ($stepType) {
            StepType::SendSms->value      => MessageChannel::Sms,
            StepType::SendWhatsapp->value => MessageChannel::Whatsapp,
            default                       => MessageChannel::Email,
        };
    }

    /**
     * Build a detached enrolment so the context builder can use customer data.
     */
    protected function syntheticEnrolment(Flow $flow, int $customerId, int $channelId): Enrolment
    {
        $contact = app(ContactResolver::class)
            ->resolve($channelId, $customerId);

        $enrolment = new Enrolment([
            'flow_id'         => $flow->id,
            'flow_version_id' => $flow->current_version_id,
            'contact_id'      => $contact->id,
            'channel_id'      => $channelId,
            'trigger_key'     => 'test',
            'trigger_payload' => [],
        ]);

        $enrolment->setRelation('contact', $contact);
        $enrolment->setRelation('flow', $flow);

        return $enrolment;
    }
}
