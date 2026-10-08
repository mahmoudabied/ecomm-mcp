<?php

namespace Webkul\MarketingAutomation\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNormalizer
{
    /**
     * Normalize a raw phone number to E.164, or null when it is empty,
     * unparsable or invalid.
     */
    public function normalize(?string $raw, ?string $countryCode = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($raw, $countryCode ?? $this->defaultCountry());
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($number)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    /**
     * Get the fallback country for numbers without a dial code.
     */
    protected function defaultCountry(): string
    {
        return core()->getConfigData('sales.shipping.origin.country') ?? 'SA';
    }
}
