<?php

namespace Amacraft\MarketingAutomation\Console\Commands;

use Amacraft\MarketingAutomation\Database\Seeders\FlowTemplateSeeder;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Amacraft\MarketingAutomation\Services\HealthCheck;
use Illuminate\Console\Command;
use Webkul\Core\Models\SubscribersList;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install marketing automation: migrate, seed templates and migrate newsletter consent';

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
        protected ConsentService $consentService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(FlowTemplateSeeder $seeder, HealthCheck $healthCheck): int
    {
        $this->call('migrate', ['--force' => true]);

        $seeder->run();

        $migrated = 0;

        SubscribersList::query()
            ->where('is_subscribed', 1)
            ->chunkById(500, function ($subscribers) use (&$migrated) {
                foreach ($subscribers as $subscriber) {
                    $migrated += $this->migrateSubscriber($subscriber);
                }
            });

        $this->info(trans('marketing_automation::app.commands.install.migrated', ['count' => $migrated]));

        $this->reportHealth($healthCheck);

        $this->printChecklist();

        return self::SUCCESS;
    }

    /**
     * Grant newsletter email consent to one subscriber when missing.
     */
    protected function migrateSubscriber($subscriber): int
    {
        $contact = $this->contactResolver->resolve(
            $subscriber->channel_id,
            $subscriber->customer_id,
            $subscriber->email
        );

        if ($this->consentService->status($contact, MessageChannel::Email) !== ConsentStatus::None) {
            return 0;
        }

        $this->consentService->grant($contact, MessageChannel::Email, ConsentSource::MigratedNewsletter);

        return 1;
    }

    /**
     * Surface the checks the admin health page also shows, so a broken
     * scheduler or sync queue driver is caught during install.
     */
    protected function reportHealth(HealthCheck $healthCheck): void
    {
        $report = $healthCheck->report();

        if ($report['scheduler_ok']) {
            $this->info(trans('marketing_automation::app.commands.install.scheduler-ok', [
                'time' => (string) $report['scheduler_last_seen_at'],
            ]));
        } else {
            $this->warn(trans('marketing_automation::app.commands.install.scheduler-not-running'));
        }

        if (! $report['queue_ok']) {
            $this->warn(trans('marketing_automation::app.commands.install.queue-sync'));
        }
    }

    /**
     * Print the cron, queue worker and webhook URL steps left for the merchant.
     */
    protected function printChecklist(): void
    {
        $this->components->info(trans('marketing_automation::app.commands.install.checklist-title'));

        $this->components->bulletList([
            trans('marketing_automation::app.commands.install.cron', [
                'command' => 'cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
            ]),
            trans('marketing_automation::app.commands.install.queues', [
                'queues' => $this->workerQueueNames(),
            ]),
            trans('marketing_automation::app.commands.install.webhook-url'),
        ]);
    }

    /**
     * The --queue list for queue:work, from the configured queue names; a null
     * queue is served by the connection's default, which is always included.
     */
    protected function workerQueueNames(): string
    {
        $defaultQueue = (string) config('queue.connections.'.config('queue.default').'.queue', 'default');

        $names = collect([
            config('marketing-automation.queues.automation'),
            config('marketing-automation.queues.klaviyo'),
            $defaultQueue,
        ])
            ->filter()
            ->unique()
            ->implode(',');

        return $names ?: 'default';
    }
}
