<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Customer\Models\Customer;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Engine\EnrolmentService;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\FlowType;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Mail\AutomationMail;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Repositories\FlowRepository;
use Webkul\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\SendPolicy;
use Webkul\MarketingAutomation\Tests\ProviderFixtures;

beforeEach(function () {
    $channel = core()->getAllChannels()->first();

    $channel->forceFill(['timezone' => 'Asia/Riyadh'])->save();

    // Through the repository, so its query cache is flushed for the next read.
    app(CoreConfigRepository::class)->create([
        'marketing_automation' => [
            'sending' => ['limits' => [
                'quiet_hours_enabled' => 1,
                'quiet_hours_start'   => '22:00',
                'quiet_hours_end'     => '09:00',
                'cap_count'           => 2,
                'cap_window_hours'    => 24,
            ]],
        ],
        'channel' => $channel->code,
        'locale'  => null,
    ]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault($channel->id, MessageChannel::Sms, $connection->id);

    Http::fake([
        'https://api.twilio.com/*' => Http::response(
            ProviderFixtures::body('twilio', 'send_success'),
            ProviderFixtures::status('twilio', 'send_success'),
        ),
    ]);
});

/**
 * A single-message flow of the given channel and type.
 */
function timingFlow(MessageChannel $channel, FlowType $type, int $validityHours = 24): Flow
{
    $step = match ($channel) {
        MessageChannel::Email => [
            'type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'Body']]],
        ],
        default => ['type' => 'send_sms', 'body' => ['en' => 'Hi']],
    };

    $flow = Flow::factory()->create(['type' => $type->value]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => $step + ['validity_hours' => $validityHours, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

function timingContact(array $overrides = []): Contact
{
    $customer = Customer::factory()->create();

    $contact = Contact::factory()->create(array_merge([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
        'phone_e164'  => '+966501234567',
    ], $overrides));

    app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);
    app(ConsentService::class)->grant($contact, MessageChannel::Sms, ConsentSource::Registration);

    return $contact;
}

function timingEnrol(Flow $flow, Contact $contact): Enrolment
{
    app(EnrolmentService::class)->enrol(new TriggerEvent(
        type: $flow->trigger_type,
        channelId: $contact->channel_id,
        customerId: $contact->customer_id,
        email: $contact->email,
        phone: $contact->phone_e164,
        triggerKey: 'timing:'.$contact->id.':'.uniqid(),
        payload: [],
    ));

    return $flow->enrolments()->where('contact_id', $contact->id)->latest('id')->first();
}

function timingLogsInWindow(Contact $contact, bool $marketing): void
{
    MessageLog::factory()->count(2)->create([
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'message_channel' => MessageChannel::Sms->value,
        'is_marketing'    => $marketing,
        'is_test'         => false,
        'status'          => 'sent',
        'recipient'       => (string) $contact->phone_e164,
        'locale'          => 'en',
        'created_at'      => now()->subHour(),
    ]);
}

it('defers a marketing sms sent inside quiet hours to the morning end', function () {
    // Freeze first: the flow must activate inside the frozen time.
    $this->travelTo(Carbon::today('Asia/Riyadh')->setTime(23, 0)->timezone('UTC'));

    $flow = timingFlow(MessageChannel::Sms, FlowType::Marketing);

    $contact = timingContact();

    $enrolment = timingEnrol($flow, $contact);

    $expected = Carbon::today('Asia/Riyadh')->addDay()->setTime(9, 0)->timezone('UTC');

    expect($enrolment->refresh()->current_step_key)->toBe('s1')
        ->and($enrolment->next_run_at->equalTo($expected))->toBeTrue()
        ->and($enrolment->events()->where('event', 'deferred')->where('reason', 'quiet_hours')->exists())->toBeTrue()
        ->and(MessageLog::query()->count())->toBe(0);
});

it('sends marketing email inside quiet hours', function () {
    Mail::fake();

    $this->travelTo(Carbon::today('Asia/Riyadh')->setTime(23, 0)->timezone('UTC'));

    $flow = timingFlow(MessageChannel::Email, FlowType::Marketing);

    $contact = timingContact();

    timingEnrol($flow, $contact);

    expect(MessageLog::query()->where('message_channel', 'email')->where('status', 'sent')->count())->toBe(1);

    Mail::assertSent(AutomationMail::class, 1);
});

it('sends a transactional sms immediately inside quiet hours', function () {
    $this->travelTo(Carbon::today('Asia/Riyadh')->setTime(23, 0)->timezone('UTC'));

    $flow = timingFlow(MessageChannel::Sms, FlowType::Transactional);

    $contact = timingContact();

    timingEnrol($flow, $contact);

    expect(MessageLog::query()->where('message_channel', 'sms')->where('status', 'sent')->count())->toBe(1);
});

it('defers a marketing sms past the frequency cap', function () {
    $this->travelTo(Carbon::today('Asia/Riyadh')->setTime(12, 0)->timezone('UTC'));

    $flow = timingFlow(MessageChannel::Sms, FlowType::Marketing, 1);

    $contact = timingContact();

    timingLogsInWindow($contact, marketing: true);

    $enrolment = timingEnrol($flow, $contact);

    expect($enrolment->refresh()->events()->where('event', 'skipped')->where('reason', 'frequency_cap')->exists())->toBeTrue()
        ->and(MessageLog::query()->count())->toBe(2);
});

it('defers to the oldest message when the cap allows a later retry', function () {
    $this->travelTo(Carbon::today('Asia/Riyadh')->setTime(12, 0)->timezone('UTC'));

    $flow = timingFlow(MessageChannel::Sms, FlowType::Marketing, 48);

    $contact = timingContact();

    timingLogsInWindow($contact, marketing: true);

    $enrolment = timingEnrol($flow, $contact);

    // Oldest + 24h + 1 minute.
    $expected = now()->subHour()->addHours(24)->addMinute();

    expect($enrolment->refresh()->current_step_key)->toBe('s1')
        ->and($enrolment->next_run_at->equalTo($expected))->toBeTrue()
        ->and($enrolment->events()->where('event', 'deferred')->where('reason', 'frequency_cap')->exists())->toBeTrue();
});

it('does not count transactional messages toward the cap', function () {
    $this->travelTo(Carbon::today('Asia/Riyadh')->setTime(12, 0)->timezone('UTC'));

    $flow = timingFlow(MessageChannel::Sms, FlowType::Marketing);

    $contact = timingContact();

    timingLogsInWindow($contact, marketing: false);

    timingEnrol($flow, $contact);

    expect(MessageLog::query()->where('is_marketing', true)->count())->toBe(1)
        ->and(MessageLog::query()->count())->toBe(3);
});

it('leaves quiet hours alone for messages outside the window', function () {
    $this->travelTo(Carbon::today('Asia/Riyadh')->setTime(14, 0)->timezone('UTC'));

    $flow = timingFlow(MessageChannel::Sms, FlowType::Marketing);

    $contact = timingContact();

    timingEnrol($flow, $contact);

    expect(MessageLog::query()->where('message_channel', 'sms')->where('status', 'sent')->count())->toBe(1);
});

it('does not count the enrolment\'s own queued message toward the cap', function () {
    $this->travelTo(Carbon::today('Asia/Riyadh')->setTime(12, 0)->timezone('UTC'));

    $flow = timingFlow(MessageChannel::Sms, FlowType::Marketing);

    $contact = timingContact();

    $enrolment = Enrolment::factory()->create([
        'flow_id'    => $flow->id,
        'contact_id' => $contact->id,
        'channel_id' => $contact->channel_id,
    ]);

    // One message already sent, plus the queued one a retry left behind.
    foreach (['sent' => null, 'queued' => $enrolment->id] as $status => $enrolmentId) {
        MessageLog::factory()->create([
            'enrolment_id'    => $enrolmentId,
            'contact_id'      => $contact->id,
            'channel_id'      => $contact->channel_id,
            'message_channel' => MessageChannel::Sms->value,
            'is_marketing'    => true,
            'is_test'         => false,
            'status'          => $status,
            'recipient'       => (string) $contact->phone_e164,
            'locale'          => 'en',
            'created_at'      => now()->subHour(),
        ]);
    }

    $policy = app(SendPolicy::class);

    $salesChannel = core()->getAllChannels()->first();

    expect($policy->check($contact, MessageChannel::Sms, FlowType::Marketing, $salesChannel, $enrolment->id)->allowed)->toBeTrue()
        ->and($policy->check($contact, MessageChannel::Sms, FlowType::Marketing, $salesChannel)->allowed)->toBeFalse();
});
