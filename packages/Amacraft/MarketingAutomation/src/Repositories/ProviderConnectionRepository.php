<?php

namespace Amacraft\MarketingAutomation\Repositories;

use Illuminate\Support\Facades\DB;
use Webkul\Core\Eloquent\Repository;
use Amacraft\MarketingAutomation\Enums\FlowStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\ProviderConnection;

class ProviderConnectionRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Amacraft\MarketingAutomation\Contracts\ProviderConnection';
    }

    /**
     * Present credentials with secret values masked.
     */
    public function maskedCredentials(ProviderConnection $connection): array
    {
        $masked = [];

        foreach ($this->driverFields($connection) as $field) {
            $value = $connection->credentials[$field['name']] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $masked[$field['name']] = $field['secret']
                ? '••••'.substr((string) $value, -4)
                : $value;
        }

        return $masked;
    }

    /**
     * Update a connection, keeping existing values for omitted or masked secret fields.
     */
    public function updateWithSecrets(ProviderConnection $connection, array $data): ProviderConnection
    {
        $incoming = array_filter(
            (array) ($data['credentials'] ?? []),
            fn ($value) => $value !== null && $value !== '' && ! str_starts_with((string) $value, '••••'),
        );

        $merged = [];

        foreach ($this->driverFields($connection) as $field) {
            $provided = $incoming[$field['name']] ?? null;

            if ($provided !== null) {
                $merged[$field['name']] = $provided;

                continue;
            }

            $existing = $connection->credentials[$field['name']] ?? null;

            if ($existing !== null) {
                $merged[$field['name']] = $existing;
            }
        }

        $connection->update([
            'name'            => $data['name'],
            'message_channel' => $data['message_channel'],
            'driver'          => $data['driver'],
            'sender'          => $data['sender'],
            'credentials'     => $merged,
        ]);

        return $connection;
    }

    /**
     * Set (or replace) the default connection of a sales channel for a message channel.
     */
    public function setDefault(int $channelId, MessageChannel $messageChannel, ?int $connectionId): void
    {
        if ($connectionId === null) {
            DB::table('automation_provider_channel_defaults')
                ->where('channel_id', $channelId)
                ->where('message_channel', $messageChannel->value)
                ->delete();

            return;
        }

        DB::table('automation_provider_channel_defaults')->updateOrInsert(
            [
                'channel_id'      => $channelId,
                'message_channel' => $messageChannel->value,
            ],
            [
                'provider_connection_id' => $connectionId,
            ],
        );
    }

    /**
     * Get the sales channel ids where the connection is the default for its message channel.
     *
     * @return array<int, int>
     */
    public function defaultChannelIds(ProviderConnection $connection): array
    {
        return DB::table('automation_provider_channel_defaults')
            ->where('provider_connection_id', $connection->id)
            ->where('message_channel', $connection->message_channel->value)
            ->pluck('channel_id')
            ->map(fn ($channelId) => (int) $channelId)
            ->all();
    }

    /**
     * Map the stored defaults keyed by "<channel_id>:<message_channel>".
     *
     * @return array<string, int>
     */
    public function defaultsMap(): array
    {
        return DB::table('automation_provider_channel_defaults')
            ->get()
            ->mapWithKeys(
                fn ($row) => ["{$row->channel_id}:{$row->message_channel}" => (int) $row->provider_connection_id],
            )
            ->all();
    }

    /**
     * Whether the connection is the default of a sales channel that runs an active flow.
     */
    public function isActiveFlowDefault(ProviderConnection $connection): bool
    {
        return DB::table('automation_provider_channel_defaults as defaults')
            ->join('automation_flow_channels', 'automation_flow_channels.channel_id', '=', 'defaults.channel_id')
            ->join('automation_flows', 'automation_flows.id', '=', 'automation_flow_channels.flow_id')
            ->where('defaults.provider_connection_id', $connection->id)
            ->where('defaults.message_channel', $connection->message_channel->value)
            ->where('automation_flows.status', FlowStatus::Active->value)
            ->exists();
    }

    /**
     * List every connection as a lightweight option for the defaults selectors.
     *
     * @return array<int, array{id: int, name: string, message_channel: string, driver: string}>
     */
    public function selectOptions(): array
    {
        return $this->orderBy('name')
            ->all()
            ->map(fn (ProviderConnection $connection) => [
                'id'              => $connection->id,
                'name'            => $connection->name,
                'message_channel' => $connection->message_channel->value,
                'driver'          => $connection->driver,
            ])
            ->values()
            ->all();
    }

    /**
     * Get the credential field schema of the connection's driver.
     *
     * @return array<int, array{name: string, secret: bool, required: bool}>
     */
    protected function driverFields(ProviderConnection $connection): array
    {
        $class = config("automation-providers.drivers.{$connection->driver}.class");

        if (! $class || ! class_exists($class)) {
            return [];
        }

        return $class::fields();
    }
}
