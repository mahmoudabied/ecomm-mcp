<?php

namespace Amacraft\MarketingAutomation\Rendering;

use Webkul\Core\Models\Channel;

class ChannelUrl
{
    /**
     * Build an absolute URL for a named route on the channel's own hostname,
     * falling back to APP_URL when the channel has none.
     */
    public function to(string $name, Channel $channel, mixed $parameters = []): string
    {
        return $this->base($channel).route($name, $parameters, false);
    }

    /**
     * The scheme and host the channel's links are built on. Bagisto stores
     * the channel hostname with its scheme, so any prefix is stripped and a
     * scheme derived from APP_URL is applied instead.
     */
    protected function base(Channel $channel): string
    {
        $appUrl = (string) config('app.url');

        if (blank($channel->hostname)) {
            return rtrim($appUrl, '/');
        }

        $scheme = str_starts_with($appUrl, 'http://') ? 'http' : 'https';

        $host = preg_replace('#^https?://#i', '', (string) $channel->hostname);

        return $scheme.'://'.rtrim((string) $host, '/');
    }
}
