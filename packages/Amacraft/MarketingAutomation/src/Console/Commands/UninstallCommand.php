<?php

namespace Amacraft\MarketingAutomation\Console\Commands;

use Amacraft\Klaviyo\Providers\KlaviyoServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\Console\Output\BufferedOutput;
use Webkul\Core\Models\CoreConfig;

class UninstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:uninstall
                            {--force : Roll back tables and config without asking for confirmation}
                            {--pretend : Print the SQL that would run and change nothing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Roll back marketing automation tables and remove its stored settings and published styles';

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
        if (
            ! $this->option('force')
            && ! $this->option('pretend')
            && ! $this->confirm(trans('marketing_automation::app.commands.uninstall.confirmation'))
        ) {
            $this->warn(trans('marketing_automation::app.commands.uninstall.aborted'));

            return self::FAILURE;
        }

        $this->rollbackKlaviyo();

        $this->rollbackPackage(dirname(__DIR__, 2).'/Database/Migrations');

        if ($this->option('pretend')) {
            return self::SUCCESS;
        }

        $this->forgetSettings();

        File::deleteDirectory(public_path('vendor/marketing-automation'));

        $this->info(trans('marketing_automation::app.commands.uninstall.completed'));

        return self::SUCCESS;
    }

    /**
     * Roll back the optional Klaviyo connector first, while its package code is still installed.
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
     * Reset every migration that ran from the package path, whatever batch it landed in.
     * The path is absolute, so the command works from packages/ and vendor/ installs alike.
     */
    protected function rollbackPackage(string $migrationsPath): void
    {
        $output = new BufferedOutput;

        $this->runCommand('migrate:reset', [
            '--path'     => $migrationsPath,
            '--realpath' => true,
            '--force'    => true,
            '--pretend'  => (bool) $this->option('pretend'),
        ], $output);

        $this->relayWithoutHostNoise($output->fetch());
    }

    /**
     * Echo the reset output minus the "Migration not found" line migrate:reset prints for every host migration.
     */
    protected function relayWithoutHostNoise(string $output): void
    {
        foreach (preg_split('/\R/', $output) as $line) {
            if (! str_contains($line, 'Migration not found')) {
                $this->output->writeln($line);
            }
        }
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
