<?php

namespace Amacraft\MarketingAutomation\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Core\Models\SubscribersList;
use Amacraft\MarketingAutomation\Database\Seeders\FlowTemplateSeeder;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactResolver;

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
    protected $description = 'Install marketing automation: seed templates and migrate newsletter consent';

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
    public function handle(FlowTemplateSeeder $seeder): int
    {
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
}
