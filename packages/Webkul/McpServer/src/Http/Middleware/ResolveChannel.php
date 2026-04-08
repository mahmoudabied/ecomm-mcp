<?php

namespace Webkul\McpServer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webkul\Core\Repositories\ChannelRepository;

class ResolveChannel
{
    public function __construct(
        protected ChannelRepository $channelRepository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->header('X-Channel-Code') ?? $request->query('channel');

        $core = app(\Webkul\Core\Core::class);

        if ($code !== null) {
            $channel = $this->channelRepository->findOneByField('code', $code);

            if (! $channel) {
                return response()->json([
                    'error'   => true,
                    'code'    => 'CHANNEL_NOT_FOUND',
                    'message' => "Channel not found: {$code}",
                ], 422);
            }

            $request->query->set('channel', $channel->code);
        } else {
            $channel = $core->getCurrentChannel();
        }

        $request->attributes->set('mcp_channel', $channel);

        return $next($request);
    }
}
