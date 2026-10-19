<?php

namespace Amacraft\Klaviyo\Services;

use Amacraft\Klaviyo\DTOs\ApiOutcome;
use Amacraft\Klaviyo\Models\Connection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class KlaviyoClient
{
    /**
     * The API revision header Klaviyo requires.
     */
    protected const REVISION = '2024-10-15';

    /**
     * The API base URL.
     */
    protected const BASE_URI = 'https://a.klaviyo.com';

    /**
     * Create a new Klaviyo client instance.
     */
    public function __construct(
        protected Connection $connection,
    ) {}

    /**
     * Send an API request and return the raw response.
     */
    public function request(string $method, string $path, array $json = []): Response
    {
        $pending = Http::timeout(15)
            ->connectTimeout(5)
            ->withHeaders([
                'Authorization' => 'Klaviyo-API-Key '.$this->connection->api_key,
                'revision'      => self::REVISION,
                'accept'        => 'application/vnd.api+json',
                'content-type'  => 'application/vnd.api+json',
            ]);

        $url = self::BASE_URI.$path;

        return match (mb_strtolower($method)) {
            'post'   => $pending->post($url, $json),
            'patch'  => $pending->patch($url, $json),
            'put'    => $pending->put($url, $json),
            'delete' => $pending->delete($url, $json),
            default  => $pending->get($url, $json),
        };
    }

    /**
     * Classify a response or transport failure into a delivery outcome.
     */
    public function outcome(Response|Throwable $response): ApiOutcome
    {
        if ($response instanceof Throwable) {
            return ApiOutcome::retryable($response->getMessage());
        }

        if ($response->successful() || $response->status() === 409) {
            return ApiOutcome::success();
        }

        $error = $this->errorMessage($response);

        return ($response->serverError() || $response->status() === 429)
            ? ApiOutcome::retryable($error)
            : ApiOutcome::permanent($error);
    }

    /**
     * The plain-language error of a failed response.
     */
    public function errorMessage(Response $response): string
    {
        $errors = $response->json('errors.0');

        return $errors['detail'] ?? $errors['title'] ?? 'Klaviyo API error ('.$response->status().')';
    }

    /**
     * The company account of the API key.
     */
    public function account(): ?array
    {
        return $this->request('GET', '/api/accounts')->json('data.0');
    }

    /**
     * Track one event.
     */
    public function createEvent(array $attributes): ApiOutcome
    {
        return $this->outcome($this->request('POST', '/api/events', [
            'data' => [
                'type'       => 'event',
                'attributes' => $attributes,
            ],
        ]));
    }

    /**
     * Create or update one profile.
     */
    public function importProfile(array $attributes): ApiOutcome
    {
        return $this->outcome($this->request('POST', '/api/profile-import', [
            'data' => [
                'type'       => 'profile',
                'attributes' => $attributes,
            ],
        ]));
    }

    /**
     * Subscribe profiles to a list for a message channel.
     */
    public function subscribeProfiles(string $listId, array $profiles, string $channel): ApiOutcome
    {
        return $this->subscriptionJob($listId, $profiles, $channel, true);
    }

    /**
     * Unsubscribe profiles from a list for a message channel.
     */
    public function unsubscribeProfiles(string $listId, array $profiles, string $channel): ApiOutcome
    {
        return $this->subscriptionJob($listId, $profiles, $channel, false);
    }

    /**
     * Create or update one catalog item.
     */
    public function upsertCatalogItem(array $item): ApiOutcome
    {
        return $this->outcome($this->request('PATCH', '/api/catalog-items/'.$item['data']['id'], $item));
    }

    /**
     * Remove one catalog item.
     */
    public function deleteCatalogItem(string $externalId): ApiOutcome
    {
        return $this->outcome($this->request('DELETE', '/api/catalog-items/'.$externalId));
    }

    /**
     * All metrics of the account.
     */
    public function metrics(): array
    {
        return $this->request('GET', '/api/metrics', ['page[size]' => 100])->json('data') ?? [];
    }

    /**
     * Events matching a filter, one page at a time. Profile and metric are
     * included so each event carries the identity and metric it refers to.
     */
    public function events(array $filter, ?string $pageCursor): ?array
    {
        $query = array_merge(
            ['filter' => implode(',', $filter)],
            ['include' => 'profile,metric', 'sort' => 'datetime'],
            $pageCursor ? ['page[cursor]' => $pageCursor] : [],
        );

        $response = $this->request('GET', '/api/events', $query);

        return $response->successful() ? ($response->json() ?? []) : null;
    }

    /**
     * Queue a bulk subscription change job.
     */
    protected function subscriptionJob(string $listId, array $profiles, string $channel, bool $subscribing): ApiOutcome
    {
        $type = $subscribing
            ? 'profile-subscription-bulk-create-job'
            : 'profile-subscription-bulk-delete-job';

        return $this->outcome($this->request('POST', '/api/'.$type, [
            'data' => [
                'type'       => $type,
                'attributes' => [
                    'profiles' => [
                        'data' => collect($profiles)->map(fn (array $profile) => [
                            'type'       => 'profile',
                            'attributes' => array_merge($profile, [
                                'subscriptions' => [
                                    $channel => ['marketing' => ['subscribed' => $subscribing]],
                                ],
                            ]),
                        ])->all(),
                    ],
                    'list_id' => $listId,
                ],
            ],
        ]));
    }
}
