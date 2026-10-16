<?php

namespace Webkul\MarketingAutomation\Http\Controllers;

use Illuminate\Routing\Controller;
use Webkul\MarketingAutomation\Enums\MessageEventType;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Rendering\LinkTracker;
use Webkul\MarketingAutomation\Services\MessageEvents;

class TrackingController extends Controller
{
    /**
     * The 1x1 transparent GIF served as the open pixel.
     */
    protected const PIXEL_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Create a new tracking controller instance.
     */
    public function __construct(
        protected LinkTracker $linkTracker,
        protected MessageEvents $messageEvents,
    ) {}

    /**
     * Record an email open and serve the transparent pixel.
     */
    public function open(string $token)
    {
        $log = $this->logFor($token);

        if ($log !== null) {
            $this->messageEvents->record((string) $log->uuid, MessageEventType::Opened);
        }

        return response(base64_decode(self::PIXEL_GIF), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Record a click and redirect to the stored destination of the link.
     */
    public function click(string $token, string $hash)
    {
        $log = $this->logFor($token);

        $url = $log?->links[$hash] ?? null;

        if ($url === null) {
            abort(404);
        }

        $this->messageEvents->record((string) $log->uuid, MessageEventType::Clicked, ['url' => $url]);

        return redirect()->away($url);
    }

    /**
     * The message log behind a signed tracking token, or null when forged.
     */
    protected function logFor(string $token): ?MessageLog
    {
        [$messageUuid, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if (! $this->linkTracker->signatureIsValid((string) $messageUuid, (string) $signature)) {
            return null;
        }

        return MessageLog::query()
            ->where('uuid', $messageUuid)
            ->first();
    }
}
