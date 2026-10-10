<?php

namespace Webkul\MarketingAutomation\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\DTOs\OutboundMessage;
use Webkul\MarketingAutomation\DTOs\SendResult;
use Webkul\MarketingAutomation\Enums\MessageStatus;
use Webkul\MarketingAutomation\Enums\SendStatus;
use Webkul\MarketingAutomation\Exceptions\RetryableSendException;
use Webkul\MarketingAutomation\Mail\AutomationMail;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;

class MessageSender
{
    /**
     * Create a new message sender instance.
     */
    public function __construct(
        protected ProviderManager $providerManager,
        protected Settings $settings,
    ) {}

    /**
     * Send a rendered email for a message log.
     *
     * @param  array{subject: string, html: string}  $rendered
     */
    public function sendEmail(MessageLog $log, array $rendered, ?string $unsubscribeUrl = null): void
    {
        if (Event::until('marketing_automation.message.send.before', [$log, 'email']) === false) {
            $this->fail($log, 'vetoed', 'A listener vetoed the send.');

            return;
        }

        try {
            Mail::to($log->recipient)->send(new AutomationMail(
                subjectText: $rendered['subject'],
                bodyHtml: $rendered['html'],
                fromAddress: $this->settings->senderAddress($this->channelCode($log)),
                fromName: $this->settings->senderName($this->channelCode($log)),
                unsubscribeUrl: $log->is_marketing ? $unsubscribeUrl : null,
            ));

            $log->update([
                'status'  => MessageStatus::Sent->value,
                'subject' => $rendered['subject'],
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->fail($log, 'mail_error', $exception->getMessage());
        }

        Event::dispatch('marketing_automation.message.send.after', [$log]);
    }

    /**
     * Send a message through the provider of the log's connection.
     */
    public function sendProvider(MessageLog $log, OutboundMessage $message): SendResult
    {
        /** @var ProviderConnection $connection */
        $connection = $log->providerConnection;

        $message = new OutboundMessage(
            to: $message->to,
            channel: $message->channel,
            body: $message->body,
            template: $message->template,
            messageUuid: $log->uuid,
            statusCallbackUrl: $this->statusCallbackUrl($connection),
        );

        if (Event::until('marketing_automation.message.send.before', [$log, $message]) === false) {
            $this->fail($log, 'vetoed', 'A listener vetoed the send.');

            return SendResult::permanent('vetoed', 'The send was vetoed.');
        }

        $result = $this->providerManager->driver($connection)->send($connection, $message);

        $this->applyResult($log, $result);

        Event::dispatch('marketing_automation.message.send.after', [$log]);

        if ($result->status === SendStatus::RetryableError) {
            throw new RetryableSendException($result);
        }

        return $result;
    }

    /**
     * Apply a provider result onto the message log.
     */
    protected function applyResult(MessageLog $log, SendResult $result): void
    {
        match ($result->status) {
            SendStatus::Accepted => $log->update([
                'status'              => MessageStatus::Sent->value,
                'provider_message_id' => $result->providerMessageId,
                'sent_at'             => now(),
            ]),
            SendStatus::PermanentError => $log->update([
                'status'        => MessageStatus::Failed->value,
                'error_code'    => $result->errorCode,
                'error_message' => $result->errorMessage !== null ? Str::limit($result->errorMessage, 250) : null,
            ]),
            SendStatus::RetryableError => $log->update([
                'attempts'      => $log->attempts + 1,
                'error_code'    => $result->errorCode,
                'error_message' => $result->errorMessage !== null ? Str::limit($result->errorMessage, 250) : null,
            ]),
        };
    }

    /**
     * Mark a log failed without throwing.
     */
    protected function fail(MessageLog $log, string $code, string $message): void
    {
        $log->update([
            'status'        => MessageStatus::Failed->value,
            'error_code'    => Str::limit($code, 250, ''),
            'error_message' => Str::limit($message, 250),
        ]);
    }

    /**
     * The sales channel code behind a message log.
     */
    protected function channelCode(MessageLog $log): string
    {
        return Channel::query()
            ->whereKey($log->channel_id)
            ->value('code') ?? (string) core()->getDefaultChannelCode();
    }

    /**
     * The webhook URL the provider should call back with status updates.
     * The secret rides along only for drivers that authenticate by it.
     */
    protected function statusCallbackUrl(ProviderConnection $connection): string
    {
        $url = route('automation.webhooks.receive', [$connection->driver, $connection->uuid]);

        if ($this->providerManager->webhookAuth($connection) === 'secret') {
            $url .= '?s='.$connection->webhook_secret;
        }

        return $url;
    }
}
