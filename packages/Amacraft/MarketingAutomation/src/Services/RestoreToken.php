<?php

namespace Amacraft\MarketingAutomation\Services;

use Amacraft\MarketingAutomation\Contracts\AbandonedCart;

class RestoreToken
{
    /**
     * The token of an abandoned cart: id + HMAC of (id, detected_at).
     */
    public function for(AbandonedCart $cart): string
    {
        $signature = hash_hmac(
            'sha256',
            $cart->id.'|'.$cart->detected_at->getTimestamp(),
            (string) config('app.key'),
            true,
        );

        return $cart->id.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * The stored lookup value of a token.
     */
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
