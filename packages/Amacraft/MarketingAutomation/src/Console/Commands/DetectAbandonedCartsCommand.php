<?php

namespace Amacraft\MarketingAutomation\Console\Commands;

use Illuminate\Console\Command;
use Amacraft\MarketingAutomation\Services\AbandonedCartService;

class DetectAbandonedCartsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'automation:detect-abandoned-carts';

    /**
     * The console command description.
     */
    protected $description = 'Detect idle carts and enrol them into the abandoned cart flows';

    /**
     * Execute the console command.
     */
    public function handle(AbandonedCartService $service): void
    {
        $detected = $service->detect();

        $this->info("Detected {$detected} abandoned cart(s).");
    }
}
