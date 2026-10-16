<?php

namespace Webkul\MarketingAutomation\Services;

class SignedToken
{
    /**
     * Sign the parts into a URL-safe token: base64url(parts joined by ".")
     * plus "." plus base64url(HMAC-SHA256 with the app key).
     */
    public function make(string ...$parts): string
    {
        $payload = $this->encode(implode('.', $parts));

        return $payload.'.'.$this->encode($this->signature($payload));
    }

    /**
     * Verify a token and return its parts, or null when it was tampered with.
     *
     * @return array<int, string>|null
     */
    public function parse(string $token): ?array
    {
        $separator = strrpos($token, '.');

        if ($separator === false) {
            return null;
        }

        $payload = substr($token, 0, $separator);

        $signature = $this->decode(substr($token, $separator + 1));

        if ($payload === '' || $signature === null || ! hash_equals($this->signature($payload), $signature)) {
            return null;
        }

        $decoded = $this->decode($payload);

        return $decoded === null ? null : explode('.', $decoded);
    }

    /**
     * The raw HMAC of a payload.
     */
    protected function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('app.key'), true);
    }

    /**
     * Base64url-encode without padding.
     */
    protected function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Base64url-decode, returning null for malformed input.
     */
    protected function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
