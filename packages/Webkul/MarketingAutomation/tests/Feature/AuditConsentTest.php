<?php

use Illuminate\Support\Facades\DB;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\MessageLog;

function auditConsentEvent(Contact $contact, string $channel, string $status, $at): void
{
    DB::table('automation_consent_events')->insert([
        'contact_id' => $contact->id,
        'channel'    => $channel,
        'status'     => $status,
        'source'     => 'checkout',
        'created_at' => $at,
    ]);
}

it('flags only the message sent after a revoke and exits with a failure', function () {
    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    auditConsentEvent($contact, 'email', 'granted', now()->subDays(5));

    MessageLog::factory()->create([
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'message_channel' => 'email',
        'is_marketing'    => true,
        'is_test'         => false,
        'status'          => 'sent',
        'sent_at'         => now()->subDays(2),
    ]);

    auditConsentEvent($contact, 'email', 'revoked', now()->subDay());

    $violating = MessageLog::factory()->create([
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'message_channel' => 'email',
        'is_marketing'    => true,
        'is_test'         => false,
        'status'          => 'sent',
        'sent_at'         => now()->subHours(2),
    ]);

    $this->artisan('automation:audit-consent')
        ->expectsTable(
            ['Log ID', 'Contact ID', 'Channel', 'Sent At', 'Reason'],
            [[$violating->id, $contact->id, 'email', (string) $violating->sent_at, 'consent_revoked_before_send']],
        )
        ->assertExitCode(1);
});

it('passes with no violations when every send had a granted consent', function () {
    $contact = Contact::factory()->create([
        'channel_id' => core()->getAllChannels()->first()->id,
    ]);

    auditConsentEvent($contact, 'sms', 'granted', now()->subDays(5));

    MessageLog::factory()->create([
        'contact_id'      => $contact->id,
        'channel_id'      => $contact->channel_id,
        'message_channel' => 'sms',
        'is_marketing'    => true,
        'is_test'         => false,
        'is_fallback'     => true,
        'status'          => 'sent',
        'sent_at'         => now()->subDay(),
    ]);

    $this->artisan('automation:audit-consent')
        ->expectsOutput('No violations.')
        ->assertExitCode(0);
});
