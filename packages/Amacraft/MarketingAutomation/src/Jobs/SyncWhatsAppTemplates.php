<?php

namespace Amacraft\MarketingAutomation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Amacraft\MarketingAutomation\Contracts\Providers\WhatsAppTemplateSource;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Models\WhatsAppTemplate;
use Amacraft\MarketingAutomation\Services\ProviderManager;

class SyncWhatsAppTemplates implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ProviderConnection $provider,
    ) {
        $this->onQueue('automation');
    }

    /**
     * Mirror the provider's template list into the local cache table. The
     * fetch runs first, so a failed request never disables stored templates.
     */
    public function handle(ProviderManager $providerManager): void
    {
        $driver = $providerManager->driver($this->provider);

        if (! $driver instanceof WhatsAppTemplateSource) {
            return;
        }

        $fetched = $driver->fetchTemplates($this->provider);

        $seen = [];

        $now = now();

        $rows = [];

        foreach ($fetched as $data) {
            $rows[] = [
                'provider_connection_id' => $this->provider->id,
                'name'                   => $data->name,
                'language'               => $data->language,
                'external_id'            => $data->externalId,
                'category'               => $data->category,
                'status'                 => $data->status,
                'components'             => json_encode($data->components),
                'variable_count'         => $data->variableCount,
                'synced_at'              => $now,
            ];

            $seen["{$data->name}|{$data->language}"] = true;
        }

        DB::transaction(function () use ($rows, $seen) {
            $this->upsertTemplates($rows);

            WhatsAppTemplate::query()
                ->where('provider_connection_id', $this->provider->id)
                ->whereNotIn('status', ['disabled'])
                ->get()
                ->each(function (WhatsAppTemplate $template) use ($seen) {
                    $key = "{$template->name}|{$template->language}";

                    if (! isset($seen[$key])) {
                        $template->update(['status' => 'disabled']);
                    }
                });
        });
    }

    /**
     * Upsert the fetched rows by (connection, name, language).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function upsertTemplates(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        WhatsAppTemplate::query()->upsert(
            $rows,
            ['provider_connection_id', 'name', 'language'],
            ['external_id', 'category', 'status', 'components', 'variable_count', 'synced_at'],
        );
    }
}
