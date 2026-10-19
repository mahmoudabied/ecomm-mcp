<?php

use Illuminate\Support\Facades\DB;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\MessageChannel as MessageChannelEnum;
use Amacraft\MarketingAutomation\Models\AbandonedCart;
use Amacraft\MarketingAutomation\Models\Attribution;
use Amacraft\MarketingAutomation\Models\BackInStockSubscription;
use Amacraft\MarketingAutomation\Models\Consent;
use Amacraft\MarketingAutomation\Models\ConsentEvent;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\EnrolmentEvent;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\FlowVersion;
use Amacraft\MarketingAutomation\Models\MessageEvent;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Models\StepStat;
use Amacraft\MarketingAutomation\Models\WhatsAppTemplate;

it('creates every automation model through its factory', function () {
    Contact::factory()->create();
    Consent::factory()->create();
    ConsentEvent::factory()->create();
    Flow::factory()->create();
    FlowVersion::factory()->create();
    Enrolment::factory()->create();
    EnrolmentEvent::factory()->create();
    MessageLog::factory()->create();
    MessageEvent::factory()->create();
    ProviderConnection::factory()->create();
    WhatsAppTemplate::factory()->create();
    AbandonedCart::factory()->create();
    BackInStockSubscription::factory()->create();
    Attribution::factory()->create();
    StepStat::factory()->create();

    expect(Contact::count())->toBeGreaterThanOrEqual(8)
        ->and(Consent::count())->toBe(1)
        ->and(ConsentEvent::count())->toBe(1)
        ->and(Flow::count())->toBeGreaterThanOrEqual(1)
        ->and(FlowVersion::count())->toBeGreaterThanOrEqual(1)
        ->and(Enrolment::count())->toBeGreaterThanOrEqual(1)
        ->and(EnrolmentEvent::count())->toBe(1)
        ->and(MessageLog::count())->toBeGreaterThanOrEqual(1)
        ->and(MessageEvent::count())->toBe(1)
        ->and(ProviderConnection::count())->toBeGreaterThanOrEqual(1)
        ->and(WhatsAppTemplate::count())->toBe(1)
        ->and(AbandonedCart::count())->toBe(1)
        ->and(BackInStockSubscription::count())->toBe(1)
        ->and(Attribution::count())->toBe(1)
        ->and(StepStat::count())->toBe(1);
});

it('stores provider credentials encrypted at rest', function () {
    $connection = ProviderConnection::factory()->sms()->create([
        'credentials' => ['auth_token' => 'super-secret-token'],
    ]);

    $raw = DB::table('automation_provider_connections')
        ->where('id', $connection->id)
        ->value('credentials');

    expect($raw)->not->toContain('super-secret-token')
        ->and($connection->refresh()->credentials)->toBe(['auth_token' => 'super-secret-token']);
});

it('auto-fills uuids and webhook secrets on creation', function () {
    $contact = Contact::factory()->create();
    $connection = ProviderConnection::factory()->create();
    $log = MessageLog::factory()->create();

    expect($contact->uuid)->not->toBeEmpty()
        ->and($connection->uuid)->not->toBeEmpty()
        ->and($connection->webhook_secret)->toHaveLength(40)
        ->and($log->uuid)->not->toBeEmpty();
});

it('forbids updating an immutable flow version', function () {
    $version = FlowVersion::factory()->create();

    $version->definition = ['entry' => 's1', 'steps' => []];

    $version->save();
})->throws(LogicException::class);

it('supports the documented factory states', function () {
    $activeFlow = Flow::factory()->active()->create();
    $transactionalFlow = Flow::factory()->transactional()->create();
    $grantedConsent = Consent::factory()->granted(MessageChannelEnum::Whatsapp)->create();
    $smsConnection = ProviderConnection::factory()->sms('msegat')->create();
    $whatsappConnection = ProviderConnection::factory()->whatsapp()->create();
    $connectedConnection = ProviderConnection::factory()->whatsapp()->connected()->create();

    expect($activeFlow->status->value)->toBe('active')
        ->and($activeFlow->activated_at)->not->toBeNull()
        ->and($transactionalFlow->type->value)->toBe('transactional')
        ->and($grantedConsent->channel)->toBe(MessageChannelEnum::Whatsapp)
        ->and($grantedConsent->status->value)->toBe('granted')
        ->and($smsConnection->driver)->toBe('msegat')
        ->and($smsConnection->message_channel)->toBe(MessageChannel::Sms)
        ->and($whatsappConnection->driver)->toBe('meta_cloud')
        ->and($connectedConnection->status->value)->toBe('connected');
});
