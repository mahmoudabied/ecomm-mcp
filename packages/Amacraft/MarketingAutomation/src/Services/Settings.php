<?php

namespace Amacraft\MarketingAutomation\Services;

use Amacraft\Klaviyo\Models\Connection;
use Amacraft\MarketingAutomation\Enums\RoutingTarget;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Models\Channel;

class Settings
{
    /**
     * Get the quiet hours window for a channel, when enabled.
     *
     * @return array{start: string, end: string}|null
     */
    public function quietHours(string $channelCode): ?array
    {
        if (! $this->config('sending.limits.quiet_hours_enabled', $channelCode)) {
            return null;
        }

        return [
            'start' => (string) $this->config('sending.limits.quiet_hours_start', $channelCode, '22:00'),
            'end'   => (string) $this->config('sending.limits.quiet_hours_end', $channelCode, '09:00'),
        ];
    }

    /**
     * Get the marketing frequency cap for a channel.
     *
     * @return array{count: int, window_hours: int}
     */
    public function cap(string $channelCode): array
    {
        return [
            'count'        => (int) $this->config('sending.limits.cap_count', $channelCode, 2),
            'window_hours' => (int) $this->config('sending.limits.cap_window_hours', $channelCode, 24),
        ];
    }

    /**
     * How many days a cart restore link stays valid.
     */
    public function restoreLinkDays(string $channelCode): int
    {
        return (int) $this->config('abandoned_cart.settings.restore_link_days', $channelCode, 7);
    }

    /**
     * How long a click stays attributable.
     */
    public function clickWindowHours(string $channelCode): int
    {
        return (int) $this->config('attribution.settings.click_window_hours', $channelCode, 120);
    }

    /**
     * How long a delivery stays attributable.
     */
    public function deliveredWindowHours(string $channelCode): int
    {
        return (int) $this->config('attribution.settings.delivered_window_hours', $channelCode, 24);
    }

    /**
     * How long raw message data is kept before purging.
     */
    public function retentionMonths(): int
    {
        return (int) core()->getConfigData('marketing_automation.retention.settings.log_months') ?: 12;
    }

    /**
     * Whether consents requested without an authenticated session must be
     * confirmed before they count.
     */
    public function doubleOptIn(): bool
    {
        return (bool) $this->config('consent.settings.double_opt_in', core()->getCurrentChannel()?->code ?? '', true);
    }

    /**
     * Which platform handles a flow template for a channel.
     */
    public function routing(string $channelCode, string $flowTemplateKey): RoutingTarget
    {
        $value = $this->config("routing.settings.{$flowTemplateKey}", $channelCode, 'native');

        $target = RoutingTarget::tryFrom((string) $value) ?? RoutingTarget::Native;

        if ($target === RoutingTarget::Klaviyo && ! $this->klaviyoConnected($channelCode)) {
            return RoutingTarget::Native;
        }

        return $target;
    }

    /**
     * Whether the channel has an active Klaviyo connection.
     */
    protected function klaviyoConnected(string $channelCode): bool
    {
        if (! class_exists(Connection::class)) {
            return false;
        }

        $channelId = DB::table('channels')->where('code', $channelCode)->value('id');

        if ($channelId === null) {
            return false;
        }

        return DB::table('klaviyo_connections')
            ->where('channel_id', $channelId)
            ->where('status', 'connected')
            ->exists();
    }

    /**
     * Get the timezone of a sales channel, falling back to the app.
     */
    public function timezone(Channel $channel): string
    {
        return $channel->timezone ?: config('app.timezone');
    }

    /**
     * The email sender name, falling back to the store mailer config.
     */
    public function senderName(string $channelCode): string
    {
        return (string) ($this->config('email.sender.sender_name', $channelCode) ?: config('mail.from.name'));
    }

    /**
     * The email sender address, falling back to the store mailer config.
     */
    public function senderAddress(string $channelCode): string
    {
        return (string) ($this->config('email.sender.sender_address', $channelCode) ?: config('mail.from.address'));
    }

    /**
     * Whether the automation engine is enabled for a channel.
     */
    public function isEnabled(string $channelCode): bool
    {
        return (bool) $this->config('general.settings.enabled', $channelCode, 1);
    }

    /**
     * Read a channel-scoped configuration value.
     */
    protected function config(string $field, string $channelCode, mixed $default = null): mixed
    {
        $value = core()->getConfigData("marketing_automation.{$field}", $channelCode);

        return $value ?? $default;
    }
}
