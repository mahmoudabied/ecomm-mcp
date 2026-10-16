<?php

use Illuminate\Support\Facades\Event;
use Webkul\Customer\Models\Customer;
use Webkul\GDPR\Models\GDPRDataRequest;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\Consent;
use Webkul\MarketingAutomation\Models\ConsentEvent;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\MessageEvent;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\StepStat;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\MarketingAutomation\Services\GdprService;

/**
 * A contact of the customer with a full automation trail.
 */
function gdprCustomerWithTrail(): array
{
    $customer = Customer::factory()->create();

    $contact = Contact::factory()->create([
        'channel_id'  => core()->getDefaultChannel()->id,
        'customer_id' => $customer->id,
        'email'       => $customer->email,
    ]);

    app(ConsentService::class)->grant(
        $contact,
        MessageChannel::Email,
        ConsentSource::Registration,
    );

    $log = MessageLog::factory()->create([
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'message_channel' => 'email',
        'recipient'       => $contact->email,
        'locale'          => 'en',
        'status'          => 'sent',
    ]);

    MessageEvent::query()->create([
        'message_log_id' => $log->id,
        'type'           => 'sent',
        'occurred_at'    => now(),
    ]);

    StepStat::factory()->create([
        'date'       => now()->toDateString(),
        'flow_id'    => Flow::factory()->create()->id,
        'step_key'   => 's1',
        'channel_id' => $contact->channel_id,
        'metric'     => 'sent',
    ]);

    return [$customer, $contact, $log];
}

it('erases every automation row when the customer is deleted from the admin', function () {
    [$customer, $contact, $log] = gdprCustomerWithTrail();

    Event::dispatch('customer.delete.before', $customer);

    expect(Contact::query()->where('id', $contact->id)->exists())->toBeFalse()
        ->and(Consent::query()->where('contact_id', $contact->id)->exists())->toBeFalse()
        ->and(ConsentEvent::query()->where('contact_id', $contact->id)->exists())->toBeFalse()
        ->and(MessageLog::query()->where('contact_id', $contact->id)->exists())->toBeFalse()
        ->and(MessageEvent::query()->where('message_log_id', $log->id)->exists())->toBeFalse()
        ->and(Enrolment::query()->where('contact_id', $contact->id)->exists())->toBeFalse()
        // The rollups are kept for reporting.
        ->and(StepStat::query()->count())->toBe(1);
});

it('erases when a delete request completes', function () {
    [$customer, $contact] = gdprCustomerWithTrail();

    $request = GDPRDataRequest::query()->create([
        'customer_id' => $customer->id,
        'email'       => $customer->email,
        'type'        => 'delete',
        'status'      => 'processing',
        'message'     => 'Please erase my data',
    ]);

    // Still processing: nothing is erased.
    Event::dispatch('customer.account.gdpr-request.update.after', $request);

    expect(Contact::query()->where('id', $contact->id)->exists())->toBeTrue();

    $request->update(['status' => 'completed']);

    Event::dispatch('customer.account.gdpr-request.update.after', $request);

    expect(Contact::query()->where('id', $contact->id)->exists())->toBeFalse();
});

it('does not erase other customers data', function () {
    [$customer, $contact] = gdprCustomerWithTrail();

    [$otherCustomer, $otherContact] = gdprCustomerWithTrail();

    Event::dispatch('customer.delete.before', $customer);

    expect(Contact::query()->where('id', $otherContact->id)->exists())->toBeTrue()
        ->and(Contact::query()->where('id', $contact->id)->exists())->toBeFalse();
});

it('exports only the customer rows as json', function () {
    [$customer, $contact] = gdprCustomerWithTrail();

    [$other, $otherContact] = gdprCustomerWithTrail();

    $data = app(GdprService::class)->export($customer);

    expect($data)->toHaveKeys(['consents', 'consent_events', 'enrolments', 'messages'])
        ->and(count($data['consents']))->toBe(1)
        ->and(count($data['messages']))->toBe(1)
        ->and($data['consents'][0]['contact_id'])->toBe($contact->id)
        ->and($data['messages'][0]['contact_id'])->toBe($contact->id)
        ->and($data['messages'][0]['contact_id'])->not->toBe($otherContact->id);

    $response = $this->actingAs($customer, 'customer')
        ->get(route('shop.customers.account.marketing-preferences.export'));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('marketing-data.json')
        ->and($response->json('consents'))->toHaveCount(1);
});
