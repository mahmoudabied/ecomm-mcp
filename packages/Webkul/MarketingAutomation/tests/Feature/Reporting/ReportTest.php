<?php

use Illuminate\Support\Facades\Event;
use Webkul\Customer\Models\Customer;
use Webkul\MarketingAutomation\DTOs\TriggerEvent;
use Webkul\MarketingAutomation\Engine\EnrolmentService;
use Webkul\MarketingAutomation\Engine\ExitGoalService;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Repositories\FlowRepository;
use Webkul\MarketingAutomation\Repositories\StepStatRepository;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderAddress;
use Webkul\Sales\Models\OrderPayment;

/**
 * A two-step marketing flow: email, then exit. Carries the order-placed goal.
 */
function reportFlow(): Flow
{
    $flow = Flow::factory()->create([
        'trigger_type' => 'customer_registered',
        'exit_goals'   => [['type' => 'order_placed']],
    ]);

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => [
                'type'           => 'send_email',
                'subject'        => ['en' => 'Hello'],
                'blocks'         => ['en' => [['type' => 'text', 'text' => 'Body']]],
                'validity_hours' => 24,
                'next'           => 's2',
            ],
            's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 60, 'next' => 's3'],
            's3' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    app(FlowRepository::class)->activate($flow);

    return $flow->refresh();
}

/**
 * Enrol a contact of the given email into the flow, optionally with email consent.
 */
function reportEnrol(Flow $flow, string $email, bool $withConsent = false): Contact
{
    $customer = Customer::factory()->create(['email' => $email]);

    $contact = Contact::factory()->create([
        'channel_id'  => core()->getAllChannels()->first()->id,
        'customer_id' => $customer->id,
        'email'       => $email,
    ]);

    if ($withConsent) {
        app(ConsentService::class)->grant($contact, MessageChannel::Email, ConsentSource::Registration);
    }

    app(EnrolmentService::class)->enrol(new TriggerEvent(
        type: $flow->trigger_type,
        channelId: $contact->channel_id,
        customerId: $contact->customer_id,
        email: $contact->email,
        phone: null,
        triggerKey: 'report:'.$contact->id,
        payload: [],
    ));

    return $contact;
}

/**
 * An order of the contact, with its billing address and payment attached.
 */
function reportOrder(Contact $contact): Order
{
    $order = Order::factory()->create([
        'channel_id'     => $contact->channel_id,
        'customer_id'    => $contact->customer_id,
        'customer_email' => $contact->email,
        'created_at'     => now(),
    ]);

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'address_type' => 'order_billing',
        'email'        => $contact->email,
    ]);

    OrderPayment::factory()->create(['order_id' => $order->id]);

    return $order;
}

it('reports per-step counts for a flow run with mixed outcomes', function () {
    $flow = reportFlow();

    reportEnrol($flow, 'converted@example.com', withConsent: true);

    reportEnrol($flow, 'skipped@example.com');

    Event::listen('marketing_automation.message.send.before', fn () => false);

    reportEnrol($flow, 'failed@example.com', withConsent: true);

    reportEnrol($flow, 'completed@example.com');

    app(ExitGoalService::class)->orderPlaced(reportOrder(Contact::query()->where('email', 'converted@example.com')->first()));

    $this->loginAsAdmin();

    $response = $this
        ->getJson(route('admin.marketing.automation.flows.report', [$flow->id, 'from' => now()->subDays(1)->toDateString(), 'to' => now()->addDay()->toDateString()]))
        ->assertOk();

    $data = $response->json('data');

    expect($data['flow']['enrolled'])->toBe(4)
        ->and($data['flow']['sent'])->toBe(1)
        ->and($data['flow']['failed'])->toBe(1)
        ->and($data['flow']['skipped'])->toBe(2)
        ->and($data['flow']['converted'])->toBe(1)
        ->and($data['steps']['s1']['skipped_by_reason'])->toBe(['no_consent' => 2]);
});

it('filters the report by date range and channel', function () {
    $flow = reportFlow();

    reportEnrol($flow, 'range@example.com');

    $this->loginAsAdmin();

    $this
        ->getJson(route('admin.marketing.automation.flows.report', [$flow->id, 'from' => now()->subDays(1)->toDateString(), 'to' => now()->addDay()->toDateString()]))
        ->assertOk()
        ->assertJsonPath('data.flow.enrolled', 1);

    $this->getJson(route('admin.marketing.automation.flows.report', [$flow->id, 'from' => now()->subDays(1)->toDateString(), 'to' => now()->addDay()->toDateString(), 'channel_id' => 999999]))
        ->assertOk()
        ->assertJsonPath('data.flow.enrolled', 0);

    $this->getJson(route('admin.marketing.automation.flows.report', [$flow->id, 'from' => now()->subYears(2)->toDateString(), 'to' => now()->subYear()->toDateString()]))
        ->assertOk()
        ->assertJsonPath('data.flow.enrolled', 0);
});

it('lists the journey of an enrolment with a masked recipient', function () {
    $flow = reportFlow();

    $contact = reportEnrol($flow, 'sara@example.com', withConsent: true);

    $enrolment = Enrolment::query()->where('contact_id', $contact->id)->firstOrFail();

    $this->loginAsAdmin();

    $this
        ->getJson(route('admin.marketing.automation.enrolments.journey', $enrolment->id))
        ->assertOk()
        ->assertJsonPath('data.enrolment.id', $enrolment->id);

    $events = Enrolment::query()->find($enrolment->id)->events()->orderBy('id')->get();

    expect($events->count())->toBeGreaterThanOrEqual(2);

    $journey = $this->getJson(route('admin.marketing.automation.enrolments.journey', $enrolment->id))->json('data.events');

    expect($journey[0]['event'])->toBe('entered')
        ->and($journey[1]['event'])->toBe('sent')
        ->and($journey[1]['message']['recipient_masked'])->toBe('s***@example.com')
        ->and($journey[1]['message']['channel'])->toBe('email');
});

it('renders the report page for browsers', function () {
    $flow = reportFlow();

    $this->loginAsAdmin();

    $this
        ->get(route('admin.marketing.automation.flows.report', $flow->id))
        ->assertOk()
        ->assertSee($flow->name)
        ->assertSee(trans('marketing_automation::app.reports.enrolled'));
});

it('purges aged message and enrolment data but keeps rollups and attributions', function () {
    $flow = reportFlow();

    $contact = reportEnrol($flow, 'purge@example.com');

    $enrolment = Enrolment::query()->where('contact_id', $contact->id)->firstOrFail();

    $aged = now()->subMonths(13);

    $oldLogId = DB::table('automation_message_logs')->insertGetId([
        'enrolment_id'    => $enrolment->id,
        'flow_id'         => $flow->id,
        'step_key'        => 's1',
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'message_channel' => 'email',
        'is_marketing'    => 1,
        'recipient'       => 'purge@example.com',
        'locale'          => 'en',
        'status'          => 'sent',
        'attempts'        => 0,
        'created_at'      => $aged,
        'updated_at'      => $aged,
    ]);

    DB::table('automation_message_events')->insert([
        'message_log_id' => $oldLogId,
        'type'           => 'sent',
        'occurred_at'    => $aged,
        'created_at'     => $aged,
    ]);

    DB::table('automation_enrolment_events')->insert([
        'enrolment_id' => $enrolment->id,
        'step_key'     => 's1',
        'event'        => 'sent',
        'created_at'   => $aged,
    ]);

    // A cancel that predates stamping `completed_at`: it must still be purged.
    $staleCancelledId = DB::table('automation_enrolments')->insertGetId([
        'flow_id'          => $flow->id,
        'flow_version_id'  => $enrolment->flow_version_id,
        'contact_id'       => $contact->id,
        'channel_id'       => $contact->channel_id,
        'trigger_key'      => 'purge-stale:'.$contact->id,
        'trigger_payload'  => '{}',
        'status'           => 'cancelled',
        'current_step_key' => 's1',
        'created_at'       => $aged,
        'updated_at'       => $aged,
    ]);

    $before = app(StepStatRepository::class)
        ->report($flow->id, $aged->copy()->subDay(), $aged->copy()->addDay(), null);

    $this->artisan('automation:purge-logs')->assertExitCode(0);

    expect(DB::table('automation_message_logs')->where('id', $oldLogId)->exists())->toBeFalse()
        ->and(DB::table('automation_message_events')->where('message_log_id', $oldLogId)->exists())->toBeFalse()
        ->and(DB::table('automation_enrolment_events')->where('created_at', '<', $aged)->exists())->toBeFalse()
        ->and(DB::table('automation_enrolments')->where('id', $staleCancelledId)->exists())->toBeFalse()
        ->and(DB::table('automation_step_stats')->count())->toBeGreaterThan(0);

    $after = app(StepStatRepository::class)
        ->report($flow->id, $aged->copy()->subDay(), $aged->copy()->addDay(), null);

    expect($after['flow'])->toEqual($before['flow']);
});
