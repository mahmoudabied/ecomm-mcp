<?php

namespace Webkul\MarketingAutomation\Services;

class RecipientMasker
{
    /**
     * Hide most of an email or phone number, keeping enough to tell
     *
     * recipients apart (`s***@example.com`, `+96650****567`).
     */
    public function mask(?string $recipient): ?string
    {
        if ($recipient === null || $recipient === '') {
            return null;
        }

        return str_contains($recipient, '@')
            ? $this->maskEmail($recipient)
            : $this->maskPhone($recipient);
    }

    /**
     * Keep the first character of the local part and the whole domain.
     */
    protected function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 1).'***@'.$domain;
    }

    /**
     * Keep the country code and prefix digits, mask the middle, keep the
     * last three (`+966501234567` becomes `+96650****567`); at least four
     * characters are always hidden.
     */
    protected function maskPhone(string $phone): string
    {
        $length = strlen($phone);

        if ($length <= 3) {
            return str_repeat('*', $length);
        }

        $keep = min(6, max(0, $length - 7));

        return substr($phone, 0, $keep).str_repeat('*', $length - $keep - 3).substr($phone, -3);
    }
}
