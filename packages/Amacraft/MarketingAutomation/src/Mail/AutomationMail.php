<?php

namespace Amacraft\MarketingAutomation\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;

class AutomationMail extends Mailable
{
    /**
     * Create a new mailable instance.
     */
    public function __construct(
        protected string $subjectText,
        protected string $bodyHtml,
        protected string $fromAddress,
        protected string $fromName,
        protected ?string $unsubscribeUrl = null,
    ) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this
            ->from($this->fromAddress, $this->fromName)
            ->subject($this->subjectText)
            ->html($this->bodyHtml);
    }

    /**
     * Add the RFC 8058 one-click unsubscribe headers when a link is set.
     */
    public function headers(): Headers
    {
        if (blank($this->unsubscribeUrl)) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe'      => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /**
     * The unsubscribe URL carried in the headers, if any.
     */
    public function unsubscribeUrl(): ?string
    {
        return $this->unsubscribeUrl;
    }
}
