<?php

namespace Amacraft\MarketingAutomation\Rendering;

use Webkul\Core\Models\Channel;
use Webkul\Core\Repositories\ChannelRepository;

class ChannelUrl
{
    /**
     * Channels already looked up by this instance, keyed by id.
     *
     * @var array<int, Channel>
     */
    protected array $channels = [];

    /**
     * Create a new channel URL instance.
     */
    public function __construct(
        protected ChannelRepository $channelRepository,
    ) {}

    /**
     * Build an absolute URL for a named route on the channel's own hostname,
     * falling back to APP_URL when the channel has none.
     */
    public function to(string $name, Channel $channel, mixed $parameters = []): string
    {
        return $this->base($channel).route($name, $parameters, false);
    }

    /**
     * The sales channel a message belongs to, falling back to the current one.
     */
    public function channel(?int $channelId): Channel
    {
        if ($channelId === null) {
            return core()->getCurrentChannel();
        }

        return $this->channels[$channelId] ??= $this->channelRepository->find($channelId)
            ?? core()->getCurrentChannel();
    }

    /**
     * The scheme and host of the channel's links; a scheme stored on the hostname wins over the one of APP_URL.
     */
    protected function base(Channel $channel): string
    {
        $appUrl = (string) config('app.url');

        if (blank($channel->hostname)) {
            return rtrim($appUrl, '/');
        }

        $hostname = rtrim((string) $channel->hostname, '/');

        if (preg_match('#^https?://#i', $hostname)) {
            return $hostname;
        }

        $scheme = str_starts_with($appUrl, 'http://') ? 'http' : 'https';

        return $scheme.'://'.$hostname;
    }
}
