<?php

namespace Webkul\McpServer\Mcp\Shop\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Webkul\McpServer\Concerns\ResolvesChannel;
use Webkul\McpServer\Traits\FormatsResponse;

#[Description('Verify MCP connectivity for the shop endpoint.')]
class HelloWorldTool extends Tool
{
    use FormatsResponse;
    use ResolvesChannel;

    public function handle(): Response
    {
        if (! config('mcp.diagnostic.hello_world_enabled')) {
            return Response::error('Diagnostic tool disabled.');
        }

        $channel = $this->getChannel();

        return $this->jsonResponse([
            'status'           => 'ok',
            'server'            => 'shop',
            'authenticated_as' => auth()->guard('customer-jwt')->user()?->email,
            'channel_code'     => $channel->code,
        ]);
    }
}
