<?php

namespace Amacraft\Klaviyo\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Amacraft\Klaviyo\Models\Connection;
use Amacraft\Klaviyo\Services\KlaviyoClient;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Services\ConsentService;

class PullConsentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'klaviyo:pull-consent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Klaviyo unsubscribe events and revoke the matching store consents';

    /**
     * The prefix shared by every unsubscribe metric Klaviyo ships.
     */
    protected const UNSUBSCRIBED_METRIC_PREFIX = 'Unsubscribed';

    /**
     * Execute the console command.
     */
    public function handle(ConsentService $consents): int
    {
        foreach (Connection::query()->where('status', 'connected')->get() as $connection) {
            $this->pullFor($connection, $consents);
        }

        return self::SUCCESS;
    }

    /**
     * Pull one connection's unsubscribe events since its cursor.
     */
    protected function pullFor(Connection $connection, ConsentService $consents): void
    {
        $client = new KlaviyoClient($connection);

        $metricIds = $this->unsubscribedMetricIds($client, $connection);

        if ($metricIds === []) {
            return;
        }

        $latest = $connection->consent_cursor ?? now()->subYears(1);

        $filter = [
            'any(metric_id,['.implode(',', array_map(fn ($id) => "\"{$id}\"", $metricIds)).'])',
            'greater-than(datetime,'.$latest->toIso8601String().')',
        ];

        $pageCursor = null;

        do {
            $page = $client->events($filter, $pageCursor);

            foreach ($page['data'] ?? [] as $event) {
                $this->revokeForEvent($connection, $event, $consents);

                $eventTime = Carbon::parse($event['attributes']['datetime'] ?? $latest)
                    ->timezone(config('app.timezone'));

                if ($eventTime->greaterThan($latest)) {
                    $latest = $eventTime;
                }
            }

            $pageCursor = $page['links']['next'] ?? null
                ? $this->cursorFromUrl($page['links']['next'])
                : null;
        } while ($pageCursor !== null);

        $connection->forceFill([
            'consent_cursor'          => $latest,
            'unsubscribed_metric_ids' => $metricIds,
        ])->save();

        $this->info("Pulled consent for connection {$connection->id} (cursor {$latest->toIso8601String()}).");
    }

    /**
     * The cached unsubscribe metric ids, fetched on the first run.
     *
     * @return array<int, string>
     */
    protected function unsubscribedMetricIds(KlaviyoClient $client, Connection $connection): array
    {
        $cached = $connection->unsubscribed_metric_ids;

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        return collect($client->metrics())
            ->filter(fn ($metric) => str_starts_with((string) ($metric['attributes']['name'] ?? ''), self::UNSUBSCRIBED_METRIC_PREFIX))
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * Revoke the contact consent an unsubscribe event refers to.
     */
    protected function revokeForEvent(Connection $connection, array $event, ConsentService $consents): void
    {
        $profile = $event['attributes']['profile']['data']['attributes'] ?? [];

        $email = $profile['email'] ?? null;
        $phone = $profile['phone_number'] ?? null;

        if (! $email && ! $phone) {
            return;
        }

        $metricName = (string) ($event['attributes']['metric']['data']['attributes']['name'] ?? '');

        $channel = str_contains($metricName, 'SMS') ? MessageChannel::Sms : MessageChannel::Email;

        $contact = Contact::query()
            ->where('channel_id', $connection->channel_id)
            ->when($email, fn ($query) => $query->where('email', strtolower((string) $email)))
            ->when(! $email, fn ($query) => $query->where('phone_e164', $phone))
            ->first();

        if ($contact === null) {
            return;
        }

        $consents->revoke($contact, $channel, ConsentSource::Klaviyo);
    }

    /**
     * The page cursor of a next link, when any.
     */
    protected function cursorFromUrl(string $url): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['page']['cursor'] ?? null;
    }
}
