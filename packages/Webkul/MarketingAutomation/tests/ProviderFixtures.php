<?php

namespace Webkul\MarketingAutomation\Tests;

final class ProviderFixtures
{
    /**
     * Load a JSON fixture body for the given driver scenario.
     */
    public static function body(string $driver, string $name): array
    {
        $path = self::path($driver, $name);

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Load the HTTP status code recorded next to a fixture body.
     */
    public static function status(string $driver, string $name): int
    {
        return (int) file_get_contents(self::path($driver, $name.'.status'));
    }

    /**
     * Resolve the fixture path, tolerating the ".json" suffix.
     */
    protected static function path(string $driver, string $name): string
    {
        $name = str_ends_with($name, '.json') ? substr($name, 0, -5) : $name;

        if (str_ends_with($name, '.status')) {
            return __DIR__."/Fixtures/Providers/{$driver}/{$name}";
        }

        return __DIR__."/Fixtures/Providers/{$driver}/{$name}.json";
    }
}
