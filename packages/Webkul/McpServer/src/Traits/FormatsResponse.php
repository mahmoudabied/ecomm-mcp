<?php

namespace Webkul\McpServer\Traits;

use Laravel\Mcp\Response;

trait FormatsResponse
{
    protected function jsonResponse(array $data): Response
    {
        return Response::text(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
