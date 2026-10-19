<?php

namespace Amacraft\Klaviyo\Repositories;

use Webkul\Core\Eloquent\Repository;
use Amacraft\Klaviyo\Contracts\Connection;

class ConnectionRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Amacraft\Klaviyo\Contracts\Connection';
    }

    /**
     * The connection of a sales channel, creating it when missing.
     */
    public function firstOrNewByChannel(int $channelId): Connection
    {
        return $this->model->firstOrNew(['channel_id' => $channelId]);
    }

    /**
     * The active connection of a sales channel, if any.
     */
    public function activeForChannel(int $channelId): ?Connection
    {
        return $this->model
            ->where('channel_id', $channelId)
            ->where('status', 'connected')
            ->first();
    }
}
