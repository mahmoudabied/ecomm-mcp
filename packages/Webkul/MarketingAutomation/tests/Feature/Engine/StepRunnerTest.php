<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Customer\Models\Customer;
use Webkul\MarketingAutomation\Console\Commands\DispatchDueCommand;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Engine\EnrolmentService;
use Webkul\MarketingAutomation\Engine\StepRunner;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\EnrolmentStatus;
use Webkul\MarketingAutomation\Enums\FlowType;
use Webkul\MarketingAutomation\Enums\JourneyEvent;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Jobs\ProcessEnrolmentStep;
use Webkul\MarketingAutomation\Mail\AutomationMail;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Models\WhatsAppTemplate;
use Webkul\MarketingAutomation\Repositories\FlowRepository;
use Webkul\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Tests\ProviderFixtures;
use Webkul\Sales\Models\Order;

beforeEach(function () {
    // Named timezones depend on the machine's tz database; keep the math deterministic.
    core()->getAllChannels()->first()->forceFill(['timezone' => 'UTC'])->save();
});

function enrolContactFor(Customer $customer, array $overrides = []): Contact
{
    return Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
        'phone_e164'  => '+966501234567',
        ...$overrides,
    ]);
}

function runFlowNow(Enrolment $enrolment): void
{
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);
}

function makeFlowWithDefinition(array $steps, string $entry = 's1', array $flowOverrides = []): Flow
{
    $flow = Flow::factory()->create($flowOverrides);

    $flow->update(['draft_definition' => ['entry' => $entry, 'steps' => $steps]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

function enrolInto(Flow $flow, Contact $contact, ?string $triggerKey = null): Enrolment
{
    $service = app(EnrolmentService::class);

    $service->enrol(new TriggerEvent(
        type: $flow->trigger_type,
        channelId: $contact->channel_id,
        customerId: $contact->customer_id,
        email: $contact->email,
        phone: $contact->phone_e164,
        triggerKey: $triggerKey ?? 'test:'.$contact->id.':'.uniqid(),
        payload: [],
    ));

    return $flow->enrolments()->where('contact_id', $contact->id)->latest('id')->first();
}

it('walks email, wait and sms steps in order', function () {
    Mail::fake();

    Http::fake([
        'https://api.twilio.com/*' => Http::sequence()
            ->push(ProviderFixtures::body('twilio', 'send_success'), ProviderFixtures::status('twilio', 'send_success'))
            ->push(ProviderFixtures::body('twilio', 'send_success'), ProviderFixtures::status('twilio', 'send_success')),
    ]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'One']]], 'validity_hours' => 24, 'next' => 's2'],
        's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 60, 'next' => 's3'],
        's3' => ['type' => 'send_sms', 'body' => ['en' => 'Two'], 'validity_hours' => 24, 'next' => 's4'],
        's4' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();

    $contact = enrolContactFor($customer);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);
    app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Registration);

    $enrolment = enrolInto($flow, $contact);

    $enrolment->refresh();

    // The email fired immediately; the wait schedules the SMS an hour later.
    expect($enrolment->current_step_key)->toBe('s3')
        ->and($enrolment->next_run_at->greaterThan(now()->addMinutes(55)))->toBeTrue()
        ->and(MessageLog::where('message_channel', 'email')->count())->toBe(1);

    Mail::assertSent(AutomationMail::class, 1);

    // Nothing is due yet.
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    expect(MessageLog::where('message_channel', 'sms')->count())->toBe(0);

    $this->travel(61)->minutes();

    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    $enrolment->refresh();

    expect($enrolment->status)->toBe(EnrolmentStatus::Completed)
        ->and(MessageLog::where('message_channel', 'sms')->count())->toBe(1);
});

it('takes the matching condition branch', function () {
    Mail::fake();

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'condition', 'rule' => ['all' => [['field' => 'order_count', 'op' => 'gte', 'value' => 1]]], 'yes' => 's2', 'no' => 's3'],
        's2' => ['type' => 'send_email', 'subject' => ['en' => 'Buyer'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's4'],
        's3' => ['type' => 'send_email', 'subject' => ['en' => 'Not yet'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's4'],
        's4' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();
    $contact = enrolContactFor($customer);
    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    $enrolment = enrolInto($flow, $contact);

    expect($enrolment->events()->where('event', JourneyEvent::ConditionNo->value)->exists())->toBeTrue();

    $customer2 = Customer::factory()->create();

    Order::factory()->create([
        'customer_id'    => $customer2->id,
        'customer_email' => $customer2->email,
        'created_at'     => now(),
    ]);
    $contact2 = enrolContactFor($customer2);
    app(ConsentService::class)->grant($contact2, MessageChannel::Email, ConsentSource::Registration);

    $enrolment2 = enrolInto($flow, $contact2);

    expect($enrolment2->events()->where('event', JourneyEvent::ConditionYes->value)->exists())->toBeTrue();
});

it('skips marketing sends without consent and continues the flow', function () {
    Mail::fake();

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_sms', 'body' => ['en' => 'Hi'], 'validity_hours' => 24, 'next' => 's2'],
        's2' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();
    $contact = enrolContactFor($customer);

    $enrolment = enrolInto($flow, $contact);

    $event = $enrolment->events()->where('event', JourneyEvent::Skipped->value)->first();

    expect($event)->not->toBeNull()
        ->and($event->reason)->toBe('no_consent')
        ->and($enrolment->refresh()->status)->toBe(EnrolmentStatus::Completed);
});

it('falls back to email when SMS consent is missing', function () {
    Mail::fake();

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => [
            'type'             => 'send_sms',
            'body'             => ['en' => 'SMS text'],
            'validity_hours'   => 24,
            'fallback_channel' => 'email',
            'fallback_step'    => [
                'subject'        => ['en' => 'Fallback subject'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'Fallback body']]],
                'validity_hours' => 24,
            ],
            'next' => 's2',
        ],
        's2' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();
    $contact = enrolContactFor($customer);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    $enrolment = enrolInto($flow, $contact);

    $log = MessageLog::query()->first();

    expect($log->message_channel->value)->toBe('email')
        ->and($log->is_fallback)->toBeTrue()
        ->and($enrolment->events()->where('event', JourneyEvent::FallbackUsed->value)->exists())->toBeTrue();

    Mail::assertSent(AutomationMail::class, 1);
});

it('falls back to email when the provider rejects permanently', function () {
    Mail::fake();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(
            ProviderFixtures::body('twilio', 'send_invalid_number'),
            ProviderFixtures::status('twilio', 'send_invalid_number'),
        ),
    ]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => [
            'type'             => 'send_sms',
            'body'             => ['en' => 'SMS text'],
            'validity_hours'   => 24,
            'fallback_channel' => 'email',
            'fallback_step'    => [
                'subject'        => ['en' => 'Fallback'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'Fallback body']]],
                'validity_hours' => 24,
            ],
            'next' => 's2',
        ],
        's2' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();
    $contact = enrolContactFor($customer);

    app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Registration);
    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    $enrolment = enrolInto($flow, $contact);

    expect(MessageLog::where('message_channel', 'sms')->first()->status->value)->toBe('failed')
        ->and(MessageLog::where('message_channel', 'email')->where('is_fallback', true)->count())->toBe(1);

    Mail::assertSent(AutomationMail::class, 1);
});

it('records the retry on a rate limited send and reuses the message log', function () {
    Mail::fake();

    Http::fake([
        'https://api.twilio.com/*' => Http::sequence()
            ->push(ProviderFixtures::body('twilio', 'send_rate_limited'), ProviderFixtures::status('twilio', 'send_rate_limited'))
            ->push(ProviderFixtures::body('twilio', 'send_success'), ProviderFixtures::status('twilio', 'send_success')),
    ]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_sms', 'body' => ['en' => 'Hi'], 'validity_hours' => 24, 'next' => 's2'],
        's2' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();
    $contact = enrolContactFor($customer);

    app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Registration);

    $enrolment = enrolInto($flow, $contact);

    // The first attempt was recorded and the step stays current.
    expect(MessageLog::first()->attempts)->toBe(1)
        ->and($enrolment->current_step_key)->toBe('s1')
        ->and($enrolment->next_run_at->greaterThan(now()))->toBeTrue()
        ->and($enrolment->locked_until?->equalTo($enrolment->next_run_at))->toBeTrue();

    // The dispatcher does not reclaim the row while the backoff holds.
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    expect(MessageLog::first()->attempts)->toBe(1);

    $this->travel(6)->minutes();

    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    $log = MessageLog::first();

    expect(MessageLog::count())->toBe(1)
        ->and($log->refresh()->status->value)->toBe('sent')
        ->and($log->attempts)->toBe(1)
        ->and($enrolment->refresh()->status)->toBe(EnrolmentStatus::Completed);
});

it('gives up after repeated retryable errors, then falls back and advances', function () {
    Mail::fake();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(
            ProviderFixtures::body('twilio', 'send_rate_limited'),
            ProviderFixtures::status('twilio', 'send_rate_limited'),
        ),
    ]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => [
            'type'             => 'send_sms',
            'body'             => ['en' => 'SMS text'],
            'validity_hours'   => 24,
            'fallback_channel' => 'email',
            'fallback_step'    => [
                'subject'        => ['en' => 'Fallback'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'Fallback body']]],
                'validity_hours' => 24,
            ],
            'next' => 's2',
        ],
        's2' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();
    $contact = enrolContactFor($customer);

    app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Registration);
    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    enrolInto($flow, $contact);

    $this->travel(6)->minutes();
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    $this->travel(16)->minutes();
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    $this->travel(31)->minutes();
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    $smsLog = MessageLog::query()->where('message_channel', 'sms')->first();

    expect($smsLog->attempts)->toBe(4)
        ->and($smsLog->status->value)->toBe('failed')
        ->and($enrolment = $flow->enrolments()->first())
        ->and($enrolment->events()->where('event', JourneyEvent::Failed->value)->where('reason', 'retries_exhausted')->exists())->toBeTrue()
        ->and(MessageLog::query()->where('message_channel', 'email')->where('is_fallback', true)->count())->toBe(1)
        ->and($enrolment->refresh()->status)->toBe(EnrolmentStatus::Completed);

    Mail::assertSent(AutomationMail::class, 1);
});

it('skips sends to an invalid contact', function () {
    Mail::fake();

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_sms', 'body' => ['en' => 'Hi'], 'validity_hours' => 24, 'next' => 's2'],
        's2' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();

    $contact = enrolContactFor($customer, ['phone_e164' => null]);

    app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Registration);

    $enrolment = enrolInto($flow, $contact);

    $event = $enrolment->events()->where('event', JourneyEvent::Skipped->value)->first();

    expect($event)->not->toBeNull()
        ->and($event->reason)->toBe('invalid_contact');
});

it('sends transactional messages without marketing consent', function () {
    Mail::fake();

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_email', 'subject' => ['en' => 'Receipt'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
        's2' => ['type' => 'exit'],
    ], 's1', ['type' => FlowType::Transactional->value]);

    $customer = Customer::factory()->create();

    $contact = enrolContactFor($customer);

    $enrolment = enrolInto($flow, $contact);

    expect(MessageLog::count())->toBe(1)
        ->and(MessageLog::first()->is_marketing)->toBeFalse();

    Mail::assertSent(AutomationMail::class, 1);
});

it('uses the channel sender address for automation emails', function () {
    Mail::fake();

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
        's2' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();
    $contact = enrolContactFor($customer);

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    // No channel override: fall back to the mailer config.
    enrolInto($flow, $contact);

    $addresses = [];

    Mail::assertSent(AutomationMail::class, function (AutomationMail $mail) use (&$addresses) {
        $mail->build();

        $addresses[] = $mail->from[0]['address'];

        return true;
    });

    expect($addresses)->toContain(config('mail.from.address'));

    // Through the repository, so its query cache is flushed for the next read.
    app(CoreConfigRepository::class)->create([
        'marketing_automation' => [
            'email' => [
                'sender' => ['sender_address' => 'automation@shop.test'],
            ],
        ],
        'channel' => core()->getAllChannels()->first()->code,
        'locale'  => null,
    ]);

    $contact2 = enrolContactFor(Customer::factory()->create());

    app(ConsentService::class)->grant($contact2, MessageChannel::Email, ConsentSource::Registration);

    enrolInto($flow, $contact2);

    $addresses = [];

    Mail::assertSent(AutomationMail::class, function (AutomationMail $mail) use (&$addresses) {
        $mail->build();

        $addresses[] = $mail->from[0]['address'];

        return true;
    });

    expect($addresses)->toContain('automation@shop.test');
});

it('completes the journey on an exit step', function () {
    Mail::fake();

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
        's2' => ['type' => 'exit'],
    ]);

    $customer = Customer::factory()->create();
    $contact = enrolContactFor($customer);

    $enrolment = enrolInto($flow, $contact);

    expect($enrolment->refresh()->status)->toBe(EnrolmentStatus::Completed)
        ->and($enrolment->events()->where('event', JourneyEvent::Exited->value)->exists())->toBeTrue();
});

it('closes a throwing step through failed(): marks the log, applies the fallback and advances', function () {
    Mail::fake();

    Http::fake(['https://api.twilio.com/*' => Http::response(ProviderFixtures::body('twilio', 'send_success'), 201)]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => [
            'type'             => 'send_sms',
            'body'             => ['en' => 'SMS text'],
            'validity_hours'   => 24,
            'fallback_channel' => 'email',
            'fallback_step'    => ['subject' => ['en' => 'Fallback'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'Fallback body']]]],
            'next'             => 's2',
        ],
        's2' => ['type' => 'exit'],
    ]);

    $contact = enrolContactFor(Customer::factory()->create());

    app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Registration);
    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    // The SMS send blows up before reaching the provider.
    Event::listen('marketing_automation.message.send.before', function ($log, $message) {
        if ($message !== 'email') {
            throw new RuntimeException('provider SDK exploded');
        }
    });

    // The --now dispatcher has no queue to retry, so it closes the step via failed().
    Queue::fake();
    $enrolment = enrolInto($flow, $contact);
    Queue::swap(app('queue'));

    $this->travel(6)->minutes();

    $this->artisan(DispatchDueCommand::class, ['--now' => true]);
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    $enrolment->refresh();

    expect(MessageLog::query()->where('message_channel', 'sms')->sole()->status->value)->toBe('failed')
        ->and(MessageLog::query()->where('message_channel', 'email')->where('is_fallback', true)->sole()->status->value)->toBe('sent')
        ->and($enrolment->events()->where('event', JourneyEvent::Failed->value)->where('reason', 'retries_exhausted')->count())->toBe(1)
        ->and($enrolment->events()->where('event', JourneyEvent::FallbackUsed->value)->count())->toBe(1)
        ->and($enrolment->status)->toBe(EnrolmentStatus::Completed)
        ->and($enrolment->lock_token)->toBeNull();

    Mail::assertSent(AutomationMail::class, 1);
});

it('keeps the claim through the retry backoff and never re-sends a delivered message', function () {
    Mail::fake();

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
        's2' => ['type' => 'exit'],
    ]);

    $contact = enrolContactFor(Customer::factory()->create());

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    Queue::fake();
    $enrolment = enrolInto($flow, $contact);
    Queue::swap(app('queue'));

    // The mail goes out, then something after the send throws once.
    $throwOnce = true;

    Event::listen('marketing_automation.message.send.after', function () use (&$throwOnce) {
        if ($throwOnce) {
            $throwOnce = false;

            throw new RuntimeException('post-send hiccup');
        }
    });

    $job = new ProcessEnrolmentStep($enrolment->id, $enrolment->lock_token);

    expect(fn () => $job->handle(app(StepRunner::class)))->toThrow(RuntimeException::class);

    // The claim now outlives the 60s backoff plus pickup margin, so the
    // dispatcher cannot steal the row and the retry keeps its token.
    $enrolment->refresh();

    expect($enrolment->locked_until->greaterThan(now()->addSeconds(300)))->toBeTrue();

    $this->travel(6)->minutes();
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    expect($enrolment->refresh()->current_step_key)->toBe('s1');

    // The queued retry runs with its original token.
    $job->handle(app(StepRunner::class));

    $enrolment->refresh();

    expect(MessageLog::query()->where('enrolment_id', $enrolment->id)->count())->toBe(1)
        ->and($enrolment->events()->where('event', JourneyEvent::Sent->value)->count())->toBe(1)
        ->and($enrolment->status)->toBe(EnrolmentStatus::Completed);

    Mail::assertSent(AutomationMail::class, 1);
});

it('retries a rate limited fallback without redoing the primary or duplicating journey entries', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::sequence()
            ->push(ProviderFixtures::body('meta_cloud', 'send_rate_limited'), ProviderFixtures::status('meta_cloud', 'send_rate_limited'))
            ->push(ProviderFixtures::body('meta_cloud', 'send_success'), ProviderFixtures::status('meta_cloud', 'send_success')),
    ]);

    $whatsapp = ProviderConnection::factory()->whatsapp()->connected()->create([
        'credentials' => ['access_token' => 'token', 'phone_number_id' => '123', 'waba_id' => '456', 'app_secret' => 'secret'],
    ]);
    $sms = ProviderConnection::factory()->sms()->connected()->create();

    $channelId = core()->getAllChannels()->first()->id;

    app(ProviderConnectionRepository::class)->setDefault($channelId, MessageChannel::Whatsapp, $whatsapp->id);
    app(ProviderConnectionRepository::class)->setDefault($channelId, MessageChannel::Sms, $sms->id);

    $template = WhatsAppTemplate::factory()->create(['provider_connection_id' => $whatsapp->id]);

    $flow = makeFlowWithDefinition([
        's1' => [
            'type'             => 'send_sms',
            'body'             => ['en' => 'SMS text'],
            'validity_hours'   => 24,
            'fallback_channel' => 'whatsapp',
            'fallback_step'    => ['template_id' => $template->id, 'variables' => [], 'language' => ['en' => 'en_US']],
            'next'             => 's2',
        ],
        's2' => ['type' => 'exit'],
    ]);

    $contact = enrolContactFor(Customer::factory()->create());

    // No SMS consent: the primary is skipped and WhatsApp takes over.
    app(ConsentService::class)->grant($contact, MessageChannel::Whatsapp, ConsentSource::Registration);

    $enrolment = enrolInto($flow, $contact);

    expect($enrolment->refresh()->current_step_key)->toBe('s1')
        ->and(MessageLog::query()->where('is_fallback', true)->sole()->status->value)->toBe('queued');

    $this->travel(6)->minutes();
    $this->artisan(DispatchDueCommand::class, ['--now' => true]);

    $enrolment->refresh();

    expect(MessageLog::query()->where('enrolment_id', $enrolment->id)->count())->toBe(1)
        ->and(MessageLog::query()->where('is_fallback', true)->sole()->status->value)->toBe('sent')
        ->and($enrolment->events()->where('event', JourneyEvent::Skipped->value)->count())->toBe(1)
        ->and($enrolment->events()->where('event', JourneyEvent::FallbackUsed->value)->count())->toBe(1)
        ->and($enrolment->status)->toBe(EnrolmentStatus::Completed);

    Http::assertSentCount(2);
});

it('treats an empty-string fallback channel as no fallback', function () {
    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(core()->getAllChannels()->first()->id, MessageChannel::Sms, $connection->id);

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_sms', 'body' => ['en' => 'Hi'], 'validity_hours' => 24, 'fallback_channel' => '', 'fallback_step' => [], 'next' => 's2'],
        's2' => ['type' => 'exit'],
    ]);

    $contact = enrolContactFor(Customer::factory()->create());

    $enrolment = enrolInto($flow, $contact);

    expect($enrolment->refresh()->status)->toBe(EnrolmentStatus::Completed)
        ->and($enrolment->events()->where('event', JourneyEvent::Skipped->value)->count())->toBe(1)
        ->and(MessageLog::query()->where('enrolment_id', $enrolment->id)->count())->toBe(0);
});

it('completes the enrolment right after a step that has no next step', function () {
    Mail::fake();

    $flow = makeFlowWithDefinition([
        's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24],
    ]);

    $contact = enrolContactFor(Customer::factory()->create());

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);

    $enrolment = enrolInto($flow, $contact)->refresh();

    expect($enrolment->status)->toBe(EnrolmentStatus::Completed)
        ->and($enrolment->completed_at)->not->toBeNull()
        ->and($enrolment->next_run_at)->toBeNull();

    Mail::assertSent(AutomationMail::class, 1);
});
