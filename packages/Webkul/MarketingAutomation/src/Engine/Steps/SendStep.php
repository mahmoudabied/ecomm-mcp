<?php

namespace Webkul\MarketingAutomation\Engine\Steps;

use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\Engine\StepContext;
use Webkul\MarketingAutomation\Engine\StepOutcome;
use Webkul\MarketingAutomation\Enums\FlowType;
use Webkul\MarketingAutomation\Enums\JourneyEvent;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\MessageStatus;
use Webkul\MarketingAutomation\Enums\SendStatus;
use Webkul\MarketingAutomation\Enums\SkipReason;
use Webkul\MarketingAutomation\Enums\StepType;
use Webkul\MarketingAutomation\Exceptions\RetryableSendException;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Rendering\MessageRenderer;
use Webkul\MarketingAutomation\Services\MessageSender;
use Webkul\MarketingAutomation\Services\PolicyResult;
use Webkul\MarketingAutomation\Services\ProviderManager;
use Webkul\MarketingAutomation\Services\SendPolicy;
use Webkul\MarketingAutomation\Services\UnsubscribeLink;

class SendStep implements StepHandler
{
    /**
     * The message channel of each send step type.
     */
    protected const CHANNEL_BY_TYPE = [
        StepType::SendEmail->value    => MessageChannel::Email,
        StepType::SendSms->value      => MessageChannel::Sms,
        StepType::SendWhatsapp->value => MessageChannel::Whatsapp,
    ];

    /**
     * How long a rate-limited step stays locked before the next attempt.
     */
    protected const RETRY_BACKOFF_SECONDS = 300;

    /**
     * The retryable delivery attempts allowed before the step gives up.
     */
    protected const MAX_SEND_ATTEMPTS = 4;

    /**
     * Create a new send step instance.
     */
    public function __construct(
        protected SendPolicy $policy,
        protected MessageRenderer $renderer,
        protected MessageSender $sender,
        protected ProviderManager $providerManager,
        protected UnsubscribeLink $unsubscribeLink,
    ) {}

    /**
     * Check the policy, then render and deliver the message.
     */
    public function handle(Enrolment $enrolment, array $step, StepContext $context): StepOutcome
    {
        if ($handled = $this->resumeCurrentVisit($enrolment, $step, $context)) {
            return $handled;
        }

        $channel = self::CHANNEL_BY_TYPE[$step['type']];

        $result = $this->policy->check($enrolment->contact, $channel, $context->flow->type, $context->channel, $enrolment->id);

        if ($result->allowed) {
            return $this->deliver($enrolment, $step, $context, $channel, $result);
        }

        if ($fallback = $this->tryFallback($enrolment, $step, $context, $result)) {
            return $fallback;
        }

        if ($result->deferUntil !== null) {
            return $this->deferOrExpire($enrolment, $step, $context, $result);
        }

        $this->record($enrolment, $context->currentStepKey, JourneyEvent::Skipped, $result->reason->value);

        return StepOutcome::next($step['next'] ?? null);
    }

    /**
     * Render and send the message for the given channel.
     */
    protected function deliver(
        Enrolment $enrolment,
        array $step,
        StepContext $context,
        MessageChannel $channel,
        PolicyResult $result,
        bool $isFallback = false,
    ): StepOutcome {
        if (
            $channel === MessageChannel::Email
            && $this->cartWentEmpty($step, $context)
        ) {
            $this->record($enrolment, $context->currentStepKey, JourneyEvent::Skipped, SkipReason::CartEmpty->value);

            return StepOutcome::next($step['next'] ?? null);
        }

        $locale = $context->renderContext()['locale'] ?? config('app.locale');

        $log = $this->createLog($enrolment, $step, $context, $channel, $result, $isFallback);

        try {
            $outcome = match ($channel) {
                MessageChannel::Email    => $this->sendEmail($log, $step, $context, $locale),
                MessageChannel::Sms      => $this->sendSms($log, $step, $context, $locale),
                MessageChannel::Whatsapp => $this->sendWhatsApp($log, $step, $context, $locale),
            };
        } catch (RetryableSendException) {
            return $this->retryOrGiveUp($enrolment, $step, $context, $log, $isFallback);
        }

        if ($outcome instanceof StepOutcome) {
            return $outcome;
        }

        if (
            $outcome === SendStatus::PermanentError
            && ! $isFallback
        ) {
            if ($fallback = $this->tryFallback($enrolment, $step, $context, PolicyResult::skip(SkipReason::NoProvider), true)) {
                return $fallback;
            }

            $this->record($enrolment, $context->currentStepKey, JourneyEvent::Failed, null, $log->id);

            return StepOutcome::next($step['next'] ?? null);
        }

        if ($outcome === SendStatus::PermanentError) {
            $this->record($enrolment, $context->currentStepKey, JourneyEvent::Failed, null, $log->id);

            return StepOutcome::next($step['next'] ?? null);
        }

        $this->record($enrolment, $context->currentStepKey, JourneyEvent::Sent, null, $log->id);

        return StepOutcome::next($step['next'] ?? null);
    }

    /**
     * Schedule the next attempt after a retryable error, or give up once the
     * attempts run out (FR-019): record the failure, then fallback or advance.
     */
    protected function retryOrGiveUp(
        Enrolment $enrolment,
        array $step,
        StepContext $context,
        MessageLog $log,
        bool $isFallback,
    ): StepOutcome {
        $attempts = (int) $log->refresh()->attempts;

        if ($attempts < self::MAX_SEND_ATTEMPTS) {
            return StepOutcome::retry($this->backoffSeconds($attempts));
        }

        $log->update([
            'status'        => MessageStatus::Failed->value,
            'error_message' => $log->error_message ?? 'retries exhausted',
        ]);

        $this->record($enrolment, $context->currentStepKey, JourneyEvent::Failed, 'retries_exhausted', $log->id);

        if ($isFallback) {
            return StepOutcome::next($step['next'] ?? null);
        }

        if ($fallback = $this->tryFallback($enrolment, $step, $context, PolicyResult::skip(SkipReason::NoProvider), true)) {
            return $fallback;
        }

        return StepOutcome::next($step['next'] ?? null);
    }

    /**
     * Pick up a step this visit already worked on, so a retried or resumed run never
     * sends the primary twice nor duplicates the Skipped/FallbackUsed entries.
     */
    protected function resumeCurrentVisit(Enrolment $enrolment, array $step, StepContext $context): ?StepOutcome
    {
        $stepKey = $context->currentStepKey;

        if ($fallbackLog = $this->currentVisitLog($enrolment, $stepKey, true)) {
            if ($fallbackLog->status === MessageStatus::Queued) {
                return $this->resumeFallback($enrolment, $step, $context, $fallbackLog);
            }

            if ($fallbackLog->status !== MessageStatus::Failed) {
                $this->recordOnce($enrolment, $stepKey, JourneyEvent::Sent, $fallbackLog->id);

                $this->recordOnce($enrolment, $stepKey, JourneyEvent::FallbackUsed);
            }

            return StepOutcome::next($step['next'] ?? null);
        }

        $primaryLog = $this->currentVisitLog($enrolment, $stepKey, false);

        if (
            $primaryLog !== null
            && $this->wasDelivered($primaryLog)
        ) {
            $this->recordOnce($enrolment, $stepKey, JourneyEvent::Sent, $primaryLog->id);

            return StepOutcome::next($step['next'] ?? null);
        }

        return null;
    }

    /**
     * Retry the fallback delivery that a retryable error interrupted.
     */
    protected function resumeFallback(Enrolment $enrolment, array $step, StepContext $context, MessageLog $fallbackLog): StepOutcome
    {
        $fallbackChannel = $fallbackLog->message_channel;

        $fallbackStep = (array) ($step['fallback_step'] ?? []);

        $fallbackStep['type'] ??= $this->typeFor($fallbackChannel);

        $fallbackStep['next'] = $step['next'] ?? null;

        $fallbackResult = $this->policy->check(
            $enrolment->contact,
            $fallbackChannel,
            $context->flow->type,
            $context->channel,
            $enrolment->id,
        );

        if (
            ! $fallbackResult->allowed
            && $fallbackResult->deferUntil !== null
        ) {
            return StepOutcome::deferUntil($fallbackResult->deferUntil, $enrolment->current_step_key, $fallbackResult->reason?->value);
        }

        if (! $fallbackResult->allowed) {
            $fallbackLog->update([
                'status'        => MessageStatus::Failed->value,
                'error_code'    => $fallbackResult->reason?->value,
                'error_message' => $fallbackLog->error_message,
            ]);

            $this->record($enrolment, $context->currentStepKey, JourneyEvent::Failed, $fallbackResult->reason?->value, $fallbackLog->id);

            return StepOutcome::next($step['next'] ?? null);
        }

        return $this->finishFallback(
            $enrolment,
            $context,
            $this->deliver($enrolment, $fallbackStep, $context, $fallbackChannel, $fallbackResult, isFallback: true),
        );
    }

    /**
     * Record FallbackUsed once the fallback delivery is settled; a retry,
     * wait or deferral comes back later and records it then.
     */
    protected function finishFallback(Enrolment $enrolment, StepContext $context, StepOutcome $outcome): StepOutcome
    {
        if (in_array($outcome->kind, ['retry', 'wait', 'defer', 'branch'], true)) {
            return $outcome;
        }

        $this->recordOnce($enrolment, $context->currentStepKey, JourneyEvent::FallbackUsed);

        return $outcome;
    }

    /**
     * The step's latest log in the current visit; loops always pass a wait,
     * so a log whose journey entry predates the latest Waited entry is older.
     */
    protected function currentVisitLog(Enrolment $enrolment, string $stepKey, bool $isFallback): ?MessageLog
    {
        $lastWaitId = $enrolment->events()
            ->where('event', JourneyEvent::Waited->value)
            ->max('id');

        return MessageLog::query()
            ->where('enrolment_id', $enrolment->id)
            ->where('step_key', $stepKey)
            ->where('is_fallback', $isFallback)
            ->when($lastWaitId, fn ($query) => $query->whereNotExists(fn ($events) => $events
                ->from('automation_enrolment_events')
                ->whereColumn('automation_enrolment_events.message_log_id', 'automation_message_logs.id')
                ->where('automation_enrolment_events.id', '<', $lastWaitId)))
            ->latest('id')
            ->first();
    }

    /**
     * Whether the message already left the store.
     */
    protected function wasDelivered(MessageLog $log): bool
    {
        return ! in_array($log->status, [MessageStatus::Queued, MessageStatus::Failed], true);
    }

    /**
     * The growing backoff applied before the next delivery attempt.
     */
    protected function backoffSeconds(int $attempts): int
    {
        $schedule = [
            1 => self::RETRY_BACKOFF_SECONDS,
            2 => 900,
            3 => 1800,
        ];

        return $schedule[$attempts] ?? 3600;
    }

    /**
     * Deliver the fallback channel after the primary retries are exhausted.
     */
    public function deliverFallbackAfterExhaustion(Enrolment $enrolment, array $step, StepContext $context): StepOutcome
    {
        $stepKey = $context->currentStepKey;

        $primaryLog = $this->currentVisitLog($enrolment, $stepKey, false);

        if (
            $primaryLog !== null
            && $this->wasDelivered($primaryLog)
        ) {
            $this->recordOnce($enrolment, $stepKey, JourneyEvent::Sent, $primaryLog->id);

            return StepOutcome::next(null);
        }

        $fallbackLog = $this->currentVisitLog($enrolment, $stepKey, true);

        if ($fallbackLog !== null) {
            if ($fallbackLog->status === MessageStatus::Queued) {
                $fallbackLog->update([
                    'status'        => MessageStatus::Failed->value,
                    'error_message' => $fallbackLog->error_message ?? 'retries exhausted',
                ]);
            }

            return StepOutcome::next(null);
        }

        $fallbackChannelValue = $step['fallback_channel'] ?? null;

        $fallbackStep = $step['fallback_step'] ?? null;

        if (
            blank($fallbackChannelValue)
            || ! is_array($fallbackStep)
        ) {
            return StepOutcome::next(null);
        }

        $fallbackChannel = MessageChannel::tryFrom((string) $fallbackChannelValue);

        if ($fallbackChannel === null) {
            return StepOutcome::next(null);
        }

        $fallbackStep['type'] ??= $this->typeFor($fallbackChannel);

        $fallbackResult = $this->policy->check(
            $enrolment->contact,
            $fallbackChannel,
            $context->flow->type,
            $context->channel,
            $enrolment->id,
        );

        if (! $fallbackResult->allowed) {
            return StepOutcome::next(null);
        }

        try {
            $outcome = $this->deliver($enrolment, $fallbackStep, $context, $fallbackChannel, $fallbackResult, isFallback: true);
        } catch (RetryableSendException) {
            return StepOutcome::next(null);
        }

        if ($outcome->kind !== 'next') {
            $this->currentVisitLog($enrolment, $stepKey, true)?->update(['status' => MessageStatus::Failed->value]);

            return StepOutcome::next(null);
        }

        $this->recordOnce($enrolment, $stepKey, JourneyEvent::FallbackUsed);

        return $outcome;
    }

    /**
     * Send the fallback channel when configured and allowed.
     */
    protected function tryFallback(
        Enrolment $enrolment,
        array $step,
        StepContext $context,
        PolicyResult $blocked,
        bool $permanentError = false,
    ): ?StepOutcome {
        $fallbackChannelValue = $step['fallback_channel'] ?? null;

        $fallbackStep = $step['fallback_step'] ?? null;

        if (
            blank($fallbackChannelValue)
            || ! is_array($fallbackStep)
        ) {
            return null;
        }

        $fallbackChannel = MessageChannel::tryFrom((string) $fallbackChannelValue);

        if ($fallbackChannel === null) {
            return null;
        }

        $fallbackStep['type'] ??= $this->typeFor($fallbackChannel);

        $fallbackResult = $this->policy->check(
            $enrolment->contact,
            $fallbackChannel,
            $context->flow->type,
            $context->channel,
            $enrolment->id,
        );

        if (! $fallbackResult->allowed) {
            return null;
        }

        if (! $permanentError) {
            $this->record($enrolment, $context->currentStepKey, JourneyEvent::Skipped, $blocked->reason->value);
        }

        $fallbackStep['next'] = $step['next'] ?? null;

        return $this->finishFallback(
            $enrolment,
            $context,
            $this->deliver($enrolment, $fallbackStep, $context, $fallbackChannel, $fallbackResult, isFallback: true),
        );
    }

    /**
     * Turn a deferral into a wait, unless it outlives the step validity.
     */
    protected function deferOrExpire(Enrolment $enrolment, array $step, StepContext $context, PolicyResult $result): StepOutcome
    {
        $dueAt = $enrolment->next_run_at ?? now();

        $validHours = (int) ($step['validity_hours'] ?? 24);

        if ($result->deferUntil->greaterThan($dueAt->copy()->addHours($validHours))) {
            $this->record($enrolment, $context->currentStepKey, JourneyEvent::Skipped, ($result->reason ?? SkipReason::Expired)->value);

            return StepOutcome::next($step['next'] ?? null);
        }

        return StepOutcome::deferUntil($result->deferUntil, $enrolment->current_step_key, $result->reason?->value);
    }

    /**
     * Render and mail an email step.
     */
    protected function sendEmail(MessageLog $log, array $step, StepContext $context, string $locale): SendStatus|StepOutcome
    {
        $unsubscribeUrl = $this->unsubscribeLink->forEmail($log, $context->contact->uuid);

        $rendered = $this->renderer->renderEmail($step, [
            ...$this->messageContext($log, $context),
            'transactional'   => $context->flow->type === FlowType::Transactional,
            'unsubscribe_url' => $unsubscribeUrl,
        ], $locale);

        $log->update(['rendered_body' => $rendered['html']]);

        $this->sender->sendEmail($log, $rendered, $unsubscribeUrl);

        return $this->statusOf($log);
    }

    /**
     * Render and send an SMS step.
     */
    protected function sendSms(MessageLog $log, array $step, StepContext $context, string $locale): SendStatus|StepOutcome
    {
        $body = $this->renderer->renderText($step, $this->messageContext($log, $context), $locale);

        $log->update(['rendered_body' => $body]);

        return $this->pushToProvider($log, new OutboundMessage(
            to: $log->recipient,
            channel: MessageChannel::Sms->value,
            body: $body,
            template: null,
            messageUuid: $log->uuid,
        ));
    }

    /**
     * Render and send a WhatsApp template step.
     */
    protected function sendWhatsApp(MessageLog $log, array $step, StepContext $context, string $locale): SendStatus|StepOutcome
    {
        $call = $this->renderer->renderWhatsApp($step, $this->messageContext($log, $context), $locale);

        return $this->pushToProvider($log, new OutboundMessage(
            to: $log->recipient,
            channel: MessageChannel::Whatsapp->value,
            body: null,
            template: $call,
            messageUuid: $log->uuid,
        ));
    }

    /**
     * The render context of one message: the cart restore link carries the
     * message uuid, so a restore click is recorded against this message.
     *
     * @return array<string, mixed>
     */
    protected function messageContext(MessageLog $log, StepContext $context): array
    {
        $renderContext = $context->renderContext();

        if (! empty($renderContext['cart']['restore_url'])) {
            $renderContext['cart']['restore_url'] .= '?'.http_build_query(['m' => $log->uuid]);
        }

        return $renderContext;
    }

    /**
     * Push an outbound message through the default provider connection.
     */
    protected function pushToProvider(MessageLog $log, OutboundMessage $message): SendStatus|StepOutcome
    {
        $connection = $this->providerManager->defaultFor($log->channel_id, $log->message_channel);

        if ($connection === null) {
            $log->update([
                'status'        => MessageStatus::Failed->value,
                'error_code'    => SkipReason::NoProvider->value,
                'error_message' => 'No default provider is configured for the channel.',
            ]);

            return SendStatus::PermanentError;
        }

        $log->update(['provider_connection_id' => $connection->id]);

        return $this->sender->sendProvider($log, $message)->status;
    }

    /**
     * Create the queued message log for this delivery, reusing the one a
     * previous retryable attempt left behind for the same step.
     */
    protected function createLog(
        Enrolment $enrolment,
        array $step,
        StepContext $context,
        MessageChannel $channel,
        PolicyResult $result,
        bool $isFallback,
    ): MessageLog {
        $existing = MessageLog::query()
            ->where('enrolment_id', $enrolment->id)
            ->where('step_key', $context->currentStepKey)
            ->where('message_channel', $channel->value)
            ->where('is_fallback', $isFallback)
            ->where('status', MessageStatus::Queued->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return MessageLog::query()->create([
            'enrolment_id'    => $enrolment->id,
            'flow_id'         => $context->flow->id,
            'step_key'        => $context->currentStepKey,
            'contact_id'      => $enrolment->contact_id,
            'channel_id'      => $enrolment->channel_id,
            'message_channel' => $channel->value,
            'is_marketing'    => $context->flow->type === FlowType::Marketing,
            'is_fallback'     => $isFallback,
            'recipient'       => (string) $result->recipient,
            'locale'          => $context->renderContext()['locale'] ?? config('app.locale'),
            'status'          => MessageStatus::Queued->value,
        ]);
    }

    /**
     * Map a message channel back to its step type for fallback content.
     */
    protected function typeFor(MessageChannel $channel): string
    {
        return match ($channel) {
            MessageChannel::Email    => StepType::SendEmail->value,
            MessageChannel::Sms      => StepType::SendSms->value,
            MessageChannel::Whatsapp => StepType::SendWhatsapp->value,
        };
    }

    /**
     * The stored status after a delivery attempt.
     */
    protected function statusOf(MessageLog $log): SendStatus
    {
        return match ($log->refresh()->status->value) {
            MessageStatus::Sent->value   => SendStatus::Accepted,
            MessageStatus::Failed->value => SendStatus::PermanentError,
            default                      => SendStatus::RetryableError,
        };
    }

    /**
     * Append an entry to the enrolment's journey log.
     */
    protected function record(
        Enrolment $enrolment,
        string $stepKey,
        JourneyEvent $event,
        ?string $reason = null,
        ?int $messageLogId = null,
    ): void {
        $enrolment->events()->create([
            'step_key'       => substr($stepKey, 0, 20),
            'event'          => $event->value,
            'reason'         => $reason,
            'message_log_id' => $messageLogId,
        ]);
    }

    /**
     * Append a journey entry unless this step already logged it.
     */
    protected function recordOnce(Enrolment $enrolment, string $stepKey, JourneyEvent $event, ?int $messageLogId = null): void
    {
        $exists = $enrolment->events()
            ->where('step_key', substr($stepKey, 0, 20))
            ->where('event', $event->value)
            ->when($messageLogId !== null, fn ($query) => $query->where('message_log_id', $messageLogId))
            ->when($messageLogId === null, fn ($query) => $query->where('id', '>', (int) $enrolment->events()
                ->where('event', JourneyEvent::Waited->value)
                ->max('id')))
            ->exists();

        if (! $exists) {
            $this->record($enrolment, $stepKey, $event, null, $messageLogId);
        }
    }

    /**
     * Whether an email step shows cart items that are all gone now.
     */
    protected function cartWentEmpty(array $step, StepContext $context): bool
    {
        $blocks = (array) ($step['blocks'] ?? []);

        $hasCartItemsBlock = collect($blocks)
            ->flatMap(fn ($localeBlocks) => (array) $localeBlocks)
            ->contains(fn ($block) => ($block['type'] ?? '') === 'cart_items');

        if (! $hasCartItemsBlock) {
            return false;
        }

        return ($context->renderContext()['cart']['items'] ?? null) === [];
    }
}
