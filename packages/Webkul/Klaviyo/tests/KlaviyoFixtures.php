<?php

namespace Webkul\Klaviyo\Tests;

final class KlaviyoFixtures
{
    /**
     * Load a JSON fixture body from the Klaviyo fixtures directory.
     */
    public static function body(string $name): array
    {
        $path = __DIR__."/Fixtures/{$name}.json";

        $raw = (string) file_get_contents($path);

        return $raw === ''
            ? []
            : json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }
}
