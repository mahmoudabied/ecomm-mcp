<?php

use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Engine\EnrolmentService;
use Amacraft\MarketingAutomation\Enums\EnrolmentStatus;
use Amacraft\MarketingAutomation\Enums\FlowStatus;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;
use Illuminate\Support\Facades\Event;
use Webkul\Core\Models\CoreConfig;
use Webkul\Customer\Models\Customer;

function definitionWithSingleEmail(): array
{
    return [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi {{ customer.first_name }}'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'Hello']]], 'validity_hours' => 24, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ];
}

function makeActiveFlowForTrigger(TriggerType $triggerType, array $overrides = []): Flow
{
    $flow = Flow::factory()->create([
        'trigger_type' => $triggerType->value,
        'status'       => FlowStatus::Draft->value,
        ...$overrides,
    ]);

    $flow->update(['draft_definition' => definitionWithSingleEmail()]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

function customerTriggerEvent(int $customerId, ?string $email): TriggerEvent
{
    return new TriggerEvent(
        type: TriggerType::CustomerRegistered,
        channelId: core()->getAllChannels()->first()->id,
        customerId: $customerId,
        email: $email,
        phone: null,
        triggerKey: 'customer:'.$customerId,
        payload: ['customer_id' => $customerId],
    );
}

it('enrols a matching trigger event into an active flow', function () {
    Queue::fake();

    $flow = makeActiveFlowForTrigger(TriggerType::CustomerRegistered);

    $customer = Customer::factory()->create(['email' => 'sara@example.com']);

    $enrolments = app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email));

    expect($enrolments)->toHaveCount(1)
        ->and($enrolments[0]->flow_id)->toBe($flow->id)
        ->and($enrolments[0]->trigger_key)->toBe('customer:'.$customer->id)
        ->and($enrolments[0]->status)->toBe(EnrolmentStatus::Active)
        ->and($enrolments[0]->current_step_key)->toBe('s1');
});

it('keeps a single enrolment for a duplicate trigger key', function () {
    Queue::fake();

    makeActiveFlowForTrigger(TriggerType::CustomerRegistered);

    $customer = Customer::factory()->create(['email' => 'dup@example.com']);

    app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email));
    app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email));

    expect(Enrolment::count())->toBe(1);
});

it('blocks re-entry inside the window but allows it after', function () {
    Queue::fake();

    $flow = makeActiveFlowForTrigger(TriggerType::CustomerRegistered, ['reentry_window_hours' => 24]);

    $customer = Customer::factory()->create(['email' => 'window@example.com']);

    // First enrolment uses key A; a different key inside the window must be skipped.
    app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email));

    $second = new TriggerEvent(
        type: TriggerType::CustomerRegistered,
        channelId: core()->getAllChannels()->first()->id,
        customerId: $customer->id,
        email: $customer->email,
        phone: null,
        triggerKey: 'customer:'.$customer->id.':second',
        payload: [],
    );

    expect(app(EnrolmentService::class)->enrol($second))->toHaveCount(0);

    $this->travel(25)->hours();

    expect(app(EnrolmentService::class)->enrol($second))->toHaveCount(1);
});

it('does not enrol into draft or paused flows', function () {
    Queue::fake();

    $customer = Customer::factory()->create(['email' => 'draft@example.com']);

    $draft = Flow::factory()->create(['trigger_type' => TriggerType::CustomerRegistered->value]);
    $draft->channels()->sync([core()->getAllChannels()->first()->id]);

    $paused = makeActiveFlowForTrigger(TriggerType::CustomerRegistered);
    app(FlowRepository::class)->pause($paused);

    expect(app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email)))->toHaveCount(0);
});

it('does not enrol into a flow that is not scoped to the event channel', function () {
    Queue::fake();

    $flow = makeActiveFlowForTrigger(TriggerType::CustomerRegistered);

    // Attach no sales channel, so the event's channel never matches.
    $flow->channels()->detach();

    $customer = Customer::factory()->create(['email' => 'channel@example.com']);

    expect(app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email)))->toHaveCount(0);
});

it('does not enrol when routing is disabled or delegated for the template', function () {
    Queue::fake();

    $channel = core()->getAllChannels()->first();

    CoreConfig::query()->updateOrCreate(
        ['code' => 'marketing_automation.routing.settings.welcome', 'channel_code' => $channel->code],
        ['value' => 'disabled'],
    );

    $flow = makeActiveFlowForTrigger(TriggerType::CustomerRegistered, ['template_key' => 'welcome']);

    $customer = Customer::factory()->create(['email' => 'routed@example.com']);

    expect(app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email)))->toHaveCount(0);

    CoreConfig::query()->updateOrCreate(
        ['code' => 'marketing_automation.routing.settings.welcome', 'channel_code' => $channel->code],
        ['value' => 'klaviyo'],
    );

    expect(app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email)))->toHaveCount(0);
});

it('does not enrol events that happened before activation', function () {
    Queue::fake();

    $flow = makeActiveFlowForTrigger(TriggerType::CustomerRegistered);

    $customer = Customer::factory()->create(['email' => 'retro@example.com']);

    // A flow that was activated in the future cannot catch older events.
    $flow->update(['activated_at' => now()->addDay()]);

    expect(app(EnrolmentService::class)->enrol(customerTriggerEvent($customer->id, $customer->email)))->toHaveCount(0);
});
