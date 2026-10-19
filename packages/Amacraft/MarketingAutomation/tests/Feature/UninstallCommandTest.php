<?php

use Illuminate\Support\Facades\Artisan;
use Webkul\Core\Models\CoreConfig;

/**
 * Every table the package and its Klaviyo connector create in their migrations.
 *
 * @return array<int, string>
 */
function packageTables(): array
{
    $files = array_merge(
        glob(dirname(__DIR__, 2).'/src/Database/Migrations/*.php'),
        glob(dirname(__DIR__, 3).'/Klaviyo/src/Database/Migrations/*.php'),
    );

    $tables = [];

    foreach ($files as $file) {
        preg_match_all("/Schema::create\\('([a-z_]+)'/", file_get_contents($file), $matches);

        array_push($tables, ...$matches[1]);
    }

    return $tables;
}

it('drops every package table across all migration batches', function () {
    $tables = packageTables();

    Artisan::call('automation:uninstall', ['--pretend' => true]);

    $output = Artisan::output();

    expect($tables)->not->toBeEmpty();

    foreach ($tables as $table) {
        expect($output)->toMatch("/drop table if exists [`\"]?{$table}[`\"]?/i");
    }
});

it('keeps the stored settings on a dry run', function () {
    CoreConfig::query()->create([
        'code'  => 'marketing_automation.general.settings.enabled',
        'value' => '1',
    ]);

    Artisan::call('automation:uninstall', ['--pretend' => true]);

    expect(CoreConfig::query()->where('code', 'marketing_automation.general.settings.enabled')->exists())->toBeTrue();
});
