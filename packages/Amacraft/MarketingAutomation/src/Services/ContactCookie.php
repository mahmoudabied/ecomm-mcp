<?php

namespace Amacraft\MarketingAutomation\Services;

use Illuminate\Support\Facades\Cookie;
use Amacraft\MarketingAutomation\Contracts\Contact;

class ContactCookie
{
    /**
     * The cookie name.
     */
    public const NAME = 'ma_contact';

    /**
     * How long the cookie lives, in minutes.
     */
    protected const LIFETIME_MINUTES = 60 * 24 * 365;

    /**
     * Queue the cookie for the contact on the current response.
     */
    public function queue(Contact $contact): void
    {
        Cookie::queue(self::NAME, $contact->uuid, self::LIFETIME_MINUTES, null, null, null, true, false, 'Lax');
    }
}
