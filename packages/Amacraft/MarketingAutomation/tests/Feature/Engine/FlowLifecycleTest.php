<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Webkul\Customer\Models\Customer;
use Amacraft\MarketingAutomation\Console\Commands\DispatchDueCommand;
use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Engine\EnrolmentService;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\EnrolmentStatus;
use Amacraft\MarketingAutomation\Enums\FlowStatus;
use Amacraft\MarketingAutomation\Enums\JourneyEvent;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\SkipReason;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;
use Amacraft\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Tests\ProviderFixtures;

beforeEach(function () {
    core()->getAllChannels()->first()->forceFill(['timezone' => 'UTC'])->save();
});

function makeLifecycleFlow(): Flow
{
    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(
        core()->getAllChannels()->first()->id,
        MessageChannel::Sms,
        $connection->id,
    );

    $flow = Flow::factory()->create(['trigger_type' => 'customer_registered']);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'One']]], 'validity_hours' => 48, 'next' => 's2'],
            's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 60, 'next' => 's3'],
            's3' => ['type' => 'send_sms', 'body' => ['en' => 'Two'], 'validity_hours' => 48, 'next' => 's4'],
            's4' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

function enrolLifecycleContact(Flow $flow, string $email): Enrolment
{
    $customer = Customer::factory()->create(['email' => $email]);

    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
        'phone_e164'  => '+966501234567',
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);
    app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Registration);

    app(EnrolmentService::class)->enrol(new TriggerEvent(
        type: TriggerType::CustomerRegistered,
        channelId: $contact->channel_id,
        customerId: $customer->id,
        email: $customer->email,
        phone: $contact->phone_e164,
        triggerKey: 'lc:'.$contact->id,
        payload: [],
    ));

    return $flow->enrolments()->latest('id')->first();
}

it('sends nothing while the flow is paused', function () {
    Mail::fake();

    $flow = makeLifecycleFlow();

    $enrolment = enrolLifecycleContact($flow, 'paused@example.com');

    // The email already went out; the wait step is pending.
    expect($enrolment->refresh()->current_step_key)->toBe('s3');

    app(FlowRepository::class)->pause($flow);

    $this->travel(2)->hours();

    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    expect(MessageLog::where('message_channel', 'sms')->count())->toBe(0)
        ->and($enrolment->refresh()->status)->toBe(EnrolmentStatus::Active);
});

it('resumes a slightly overdue step and sends it', function () {
    Mail::fake();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(
            ProviderFixtures::body('twilio', 'send_success'),
            ProviderFixtures::status('twilio', 'send_success'),
        ),
    ]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeLifecycleFlow();

    $enrolment = enrolLifecycleContact($flow, 'resume-fast@example.com');

    app(FlowRepository::class)->pause($flow);

    $this->travel(5)->hours();

    app(FlowRepository::class)->resume($flow);

    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    expect(MessageLog::where('message_channel', 'sms')->count())->toBe(1)
        ->and($enrolment->refresh()->status)->toBe(EnrolmentStatus::Completed);
});

it('skips steps that are more than a day overdue on resume', function () {
    Mail::fake();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(
            ProviderFixtures::body('twilio', 'send_success'),
            ProviderFixtures::status('twilio', 'send_success'),
        ),
    ]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeLifecycleFlow();

    $enrolment = enrolLifecycleContact($flow, 'resume-late@example.com');

    app(FlowRepository::class)->pause($flow);

    $this->travel(30)->hours();

    app(FlowRepository::class)->resume($flow);

    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    $skipped = $enrolment->events()
        ->where('event', JourneyEvent::Skipped->value)
        ->where('reason', SkipReason::Expired->value)
        ->first();

    expect($skipped)->not->toBeNull()
        ->and(MessageLog::where('message_channel', 'sms')->count())->toBe(0)
        ->and($enrolment->refresh()->status)->toBe(EnrolmentStatus::Completed);
});

it('publishes version 2 when an active flow is edited and reactivated', function () {
    Mail::fake();

    $flow = makeLifecycleFlow();

    $enrolment = enrolLifecycleContact($flow, 'versions@example.com');

    $firstVersionId = $enrolment->flow_version_id;

    // Edit the draft content, then re-activate.
    $draft = $flow->draft_definition;
    $draft['steps']['s1']['subject']['en'] = 'Edited subject';

    $flow->update(['draft_definition' => $draft]);

    $version = app(FlowRepository::class)->activate($flow);

    expect($version->version)->toBe(2);

    $freshEnrolment = enrolLifecycleContact($flow, 'versions-new@example.com');

    expect($enrolment->refresh()->flow_version_id)->toBe($firstVersionId)
        ->and($freshEnrolment->flow_version_id)->toBe($version->id);
});

it('duplicates a flow into a draft copy', function () {
    $flow = makeLifecycleFlow();

    $copy = app(FlowRepository::class)->duplicate($flow);

    expect($copy->id)->not->toBe($flow->id)
        ->and($copy->name)->toBe($flow->name.' (copy)')
        ->and($copy->status->value)->toBe(FlowStatus::Draft->value)
        ->and($copy->channels->pluck('id')->all())->toBe($flow->channels->pluck('id')->all())
        ->and($copy->draft_definition)->toBe($flow->draft_definition);
});

it('hard-deletes the flow and its enrolments when the flow is deleted', function () {
    Mail::fake();

    $flow = makeLifecycleFlow();

    $enrolment = enrolLifecycleContact($flow, 'deleted@example.com');

    app(FlowRepository::class)->deleteFlow($flow);

    expect(Flow::find($flow->id))->toBeNull()
        ->and($enrolment->fresh())->toBeNull();
});

it('stamps resumed_at when a paused flow is activated again', function () {
    $flow = makeLifecycleFlow();

    expect($flow->resumed_at)->toBeNull();

    app(FlowRepository::class)->pause($flow);

    $this->travel(2)->hours();

    app(FlowRepository::class)->activate($flow->refresh());

    $flow->refresh();

    expect($flow->status)->toBe(FlowStatus::Active)
        ->and($flow->paused_at)->toBeNull()
        ->and($flow->resumed_at?->timestamp)->toBe(now()->timestamp);
});

it('leaves resumed_at alone when an active flow is re-activated', function () {
    $flow = makeLifecycleFlow();

    app(FlowRepository::class)->activate($flow);

    expect($flow->refresh()->resumed_at)->toBeNull();
});
