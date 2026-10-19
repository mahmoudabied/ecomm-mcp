<?php

use Amacraft\MarketingAutomation\Services\HealthCheck;

it('flags the sync queue driver as unhealthy', function () {
    config(['queue.default' => 'sync']);

    $report = app(HealthCheck::class)->report();

    expect($report['queue_driver'])->toBe('sync')
        ->and($report['queue_ok'])->toBeFalse()
        ->and($report['scheduler_ok'])->toBeFalse()
        ->and($report['scheduler_last_seen_at'])->toBeNull();
});

it('treats a fresh scheduler heartbeat as healthy', function () {
    config(['queue.default' => 'database']);

    $healthCheck = app(HealthCheck::class);
    $healthCheck->beat();

    $report = $healthCheck->report();

    expect($report['queue_ok'])->toBeTrue()
        ->and($report['scheduler_ok'])->toBeTrue()
        ->and($report['scheduler_last_seen_at'])->not->toBeNull();
});

it('shows the health page with the queue warning to an admin', function () {
    config(['queue.default' => 'sync']);

    $this->loginAsAdmin();

    $this->get(route('admin.marketing.automation.health.index'))
        ->assertOk()
        ->assertSeeText(trans('marketing_automation::app.health.queue'))
        ->assertSeeText(trans('marketing_automation::app.health.warning'));
});

it('shows the SMTP hint only when the mail check fails', function () {
    $this->loginAsAdmin();

    config(['mail.default' => 'log']);

    $this->get(route('admin.marketing.automation.health.index'))
        ->assertOk()
        ->assertSeeText(trans('marketing_automation::app.health.mail-hint'));

    config(['mail.default' => 'smtp']);

    $this->get(route('admin.marketing.automation.health.index'))
        ->assertOk()
        ->assertDontSeeText(trans('marketing_automation::app.health.mail-hint'));
});

it('forbids admins without the settings permission', function () {
    $this->loginAsAdmin();

    $admin = auth()->guard('admin')->user();

    $admin->role->update(['permission_type' => 'custom', 'permissions' => ['dashboard']]);

    $this->get(route('admin.marketing.automation.health.index'))
        ->assertUnauthorized();
});
