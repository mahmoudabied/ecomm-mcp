<?php

namespace Amacraft\MarketingAutomation\Rendering;

use Amacraft\MarketingAutomation\Models\MessageLog;

class LinkTracker
{
    /**
     * Rewrite the http(s) links of an email into signed tracking redirects,
     * append the open pixel and store the mapping on the message log.
     */
    public function rewrite(MessageLog $log, string $html, ?string $exceptUrl = null): string
    {
        $links = $log->links ?? [];

        $signature = $this->signature((string) $log->uuid);

        $html = preg_replace_callback(
            '/href="(https?:\/\/[^"]+)"/i',
            function (array $matches) use ($log, $signature, $exceptUrl, &$links) {
                $url = html_entity_decode($matches[1]);

                if ($exceptUrl !== null && $url === $exceptUrl) {
                    return $matches[0];
                }

                $hash = substr(sha1($url), 0, 12);

                $links[$hash] = $url;

                return 'href="'.route('automation.track.click', [$log->uuid.'.'.$signature, $hash]).'"';
            },
            $html,
        ) ?? $html;

        if ($links !== []) {
            $log->update(['links' => $links]);
        }

        return $this->appendOpenPixel($log, $signature, (string) $html);
    }

    /**
     * The shared signature segment of the tracking tokens of one message.
     */
    public function signature(string $messageUuid): string
    {
        return substr(hash_hmac('sha256', $messageUuid, (string) config('app.key')), 0, 16);
    }

    /**
     * Whether a token of a message is authentic.
     */
    public function signatureIsValid(string $messageUuid, string $signature): bool
    {
        return hash_equals($this->signature($messageUuid), $signature);
    }

    /**
     * Add the invisible open pixel just before the closing body tag.
     */
    protected function appendOpenPixel(MessageLog $log, string $signature, string $html): string
    {
        $pixel = '<img src="'.route('automation.track.open', $log->uuid.'.'.$signature).'" width="1" height="1" alt="">';

        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $pixel.'</body>', $html);
        }

        return $html.$pixel;
    }
}
