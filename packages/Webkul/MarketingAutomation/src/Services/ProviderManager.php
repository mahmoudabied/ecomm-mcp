<?php

namespace Webkul\MarketingAutomation\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Webkul\MarketingAutomation\Contracts\Providers\MessagingProvider;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\ProviderConnection;

class ProviderManager
{
    /**
     * Resolve the driver instance behind a provider connection.
     */
    public function driver(ProviderConnection $connection): MessagingProvider
    {
        $class = config("automation-providers.drivers.{$connection->driver}.class");

        if (! $class) {
            throw new InvalidArgumentException("Unknown messaging driver [{$connection->driver}].");
        }

        return app()->make($class);
    }

    /**
     * Get the default connection for a sales channel and message channel.
     */
    public function defaultFor(int $channelId, MessageChannel $messageChannel): ?ProviderConnection
    {
        $connectionId = DB::table('automation_provider_channel_defaults')
            ->where('channel_id', $channelId)
            ->where('message_channel', $messageChannel->value)
            ->value('provider_connection_id');

        return $connectionId !== null
            ? ProviderConnection::query()->find($connectionId)
            : null;
    }

    /**
     * List the configured drivers available for a message channel.
     *
     * @return array<int, array{driver: string, label: string, fields: array}>
     */
    public function driversFor(MessageChannel $messageChannel): array
    {
        $drivers = [];

        foreach (config('automation-providers.drivers', []) as $key => $driver) {
            if ($driver['channel'] !== $messageChannel->value) {
                continue;
            }

            $drivers[] = [
                'driver' => $key,
                'label'  => $driver['label'],
                'fields' => $driver['class']::fields(),
            ];
        }

        return $drivers;
    }

    /**
     * Whether a connection's webhook authenticates by signature or shared secret.
     */
    public function webhookAuth(ProviderConnection $connection): string
    {
        return (string) config("automation-providers.drivers.{$connection->driver}.webhook_auth", 'secret');
    }
}
