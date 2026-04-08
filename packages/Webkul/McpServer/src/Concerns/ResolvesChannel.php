<?php

namespace Webkul\McpServer\Concerns;

use Webkul\Core\Models\Channel;

trait ResolvesChannel
{
    protected function getChannel(): Channel
    {
        return request()->attributes->get('mcp_channel') ?? core()->getCurrentChannel();
    }
}
