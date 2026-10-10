<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Webkul\Customer\Models\Customer;
use Webkul\MarketingAutomation\Console\Commands\DispatchDueCommand;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Engine\EnrolmentService;
use Webkul\MarketingAutomation\Engine\StepRunner;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Jobs\ProcessEnrolmentStep;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Repositories\FlowRepository;
use Webkul\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Webkul\MarketingAutomation\Services\ConsentService;

beforeEach(function () {
    core()->getAllChannels()->first()->forceFill(['timezone' => 'UTC'])->save();
});

function makeLockingFlow(): Flow
{
    $flow = Flow::factory()->create();

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

it('claims the enrolment at creation so the step cannot send twice', function () {
    Mail::fake();

    Queue::fake();

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeLockingFlow();

    $customer = Customer::factory()->create();

    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
    ]);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    app(EnrolmentService::class)->enrol(new TriggerEvent(
        type: $flow->trigger_type,
        channelId: $contact->channel_id,
        customerId: $contact->customer_id,
        email: $contact->email,
        phone: $contact->phone_e164,
        triggerKey: 'lock:test:'.uniqid(),
        payload: [],
    ));

    /** @var Enrolment $enrolment */
    $enrolment = $flow->enrolments()->first();

    expect($enrolment->lock_token)->not->toBeNull()
        ->and($enrolment->locked_until)->not->toBeNull();

    // While the claim holds, the dispatcher leaves the row alone.
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    expect(MessageLog::count())->toBe(0);

    // The queued job is lost; once the claim expires the dispatcher takes over.
    $this->travel(6)->minutes();

    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    expect(MessageLog::count())->toBe(1);

    // A second dispatch run must not repeat the step.
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    // A stale queued job carrying the original token is ignored too.
    (new ProcessEnrolmentStep($enrolment->id, $enrolment->lock_token))->handle(app(StepRunner::class));

    expect(MessageLog::count())->toBe(1)
        ->and($enrolment->refresh()->status->value)->toBe('completed');
});
