<?php

namespace Amacraft\MarketingAutomation\Console\Commands;

use Amacraft\Klaviyo\Providers\KlaviyoServiceProvider;
use Illuminate\Console\Command;
use ReflectionClass;
use Webkul\Core\Models\CoreConfig;

class UninstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:uninstall
                            {--force : Roll back tables and config without asking for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Roll back marketing automation tables and remove its stored settings';

    /**
     * Config code prefixes owned by the package and its optional connector.
     *
     * @var array<int, string>
     */
    protected const CONFIG_PREFIXES = ['marketing_automation.', 'klaviyo.'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(trans('marketing_automation::app.commands.uninstall.confirmation'))) {
            $this->warn(trans('marketing_automation::app.commands.uninstall.aborted'));

            return self::FAILURE;
        }

        $this->rollbackKlaviyo();

        $this->rollbackPackage(dirname(__DIR__, 2).'/Database/Migrations');

        $this->forgetSettings();

        $this->info(trans('marketing_automation::app.commands.uninstall.completed'));

        return self::SUCCESS;
    }

    /**
     * Roll back the optional Klaviyo connector first; its tables reference the
     * core automation tables.
     */
    protected function rollbackKlaviyo(): void
    {
        if (! class_exists(KlaviyoServiceProvider::class)) {
            return;
        }

        $source = (new ReflectionClass(KlaviyoServiceProvider::class))->getFileName();

        $this->rollbackPackage(dirname($source, 2).'/Database/Migrations');
    }

    /**
     * Roll back one package's migrations resolved from its own path, so the
     * command works from packages/ and vendor/ installs alike.
     */
    protected function rollbackPackage(string $migrationsPath): void
    {
        $this->call('migrate:rollback', [
            '--path'  => $migrationsPath,
            '--force' => true,
        ]);
    }

    /**
     * Delete every stored setting row the package wrote.
     */
    protected function forgetSettings(): void
    {
        foreach (self::CONFIG_PREFIXES as $prefix) {
            CoreConfig::query()->where('code', 'like', $prefix.'%')->delete();
        }
    }
}
