<?php

use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Models\WhatsAppTemplate;
use Webkul\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Webkul\MarketingAutomation\Tests\ProviderFixtures;
use Webkul\User\Models\Admin;
use Webkul\User\Models\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

function providerEndpoint(string $action, ?int $id = null): string
{
    $routes = [
        'index'          => 'admin.marketing.automation.providers.index',
        'store'          => 'admin.marketing.automation.providers.store',
        'defaults'       => 'admin.marketing.automation.providers.defaults',
        'show'           => 'admin.marketing.automation.providers.show',
        'update'         => 'admin.marketing.automation.providers.update',
        'delete'         => 'admin.marketing.automation.providers.delete',
        'test'           => 'admin.marketing.automation.providers.test',
        'sync_templates' => 'admin.marketing.automation.providers.sync_templates',
    ];

    return $id !== null ? route($routes[$action], $id) : route($routes[$action]);
}

it('creates a provider connection in the untested state', function () {
    $this->loginAsAdmin();

    postJson(providerEndpoint('store'), [
        'name'            => 'Twilio main',
        'message_channel' => 'sms',
        'driver'          => 'twilio',
        'sender'          => 'Bagisto',
        'credentials'     => ['account_sid' => 'ACnew', 'auth_token' => 'secret-token-value'],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'untested');

    $connection = ProviderConnection::query()->where('name', 'Twilio main')->first();

    expect($connection)->not->toBeNull()
        ->and($connection->status->value)->toBe('untested')
        ->and($connection->uuid)->not->toBeEmpty();
});

it('masks secret credentials in the show endpoint', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create([
        'credentials' => ['account_sid' => 'ACvisible', 'auth_token' => 'super-secret-token'],
    ]);

    getJson(providerEndpoint('show', $connection->id))
        ->assertOk()
        ->assertDontSee('super-secret-token')
        ->assertJsonPath('data.credentials.account_sid', 'ACvisible')
        ->assertJsonPath('data.credentials.auth_token', '••••oken');
});

it('keeps the stored secret when the update request omits it', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create([
        'credentials' => ['account_sid' => 'ACkeep', 'auth_token' => 'original-secret'],
    ]);

    putJson(providerEndpoint('update', $connection->id), [
        'name'            => 'Twilio renamed',
        'message_channel' => 'sms',
        'driver'          => 'twilio',
        'sender'          => 'Bagisto',
        'credentials'     => ['account_sid' => 'ACchanged', 'auth_token' => ''],
    ])->assertOk();

    $connection->refresh();

    expect($connection->credentials['auth_token'])->toBe('original-secret')
        ->and($connection->credentials['account_sid'])->toBe('ACchanged')
        ->and($connection->name)->toBe('Twilio renamed');
});

it('marks the connection connected when the test message is accepted', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create([
        'credentials' => ['account_sid' => 'ACtest', 'auth_token' => 'tok'],
    ]);

    Http::fake([
        'https://api.twilio.com/*' => Http::response(
            ProviderFixtures::body('twilio', 'send_success'),
            ProviderFixtures::status('twilio', 'send_success'),
        ),
    ]);

    postJson(providerEndpoint('test', $connection->id), ['to' => '0501234567'])
        ->assertOk()
        ->assertJsonPath('data.status', 'connected');

    $connection->refresh();

    expect($connection->status->value)->toBe('connected')
        ->and($connection->last_error)->toBeNull();
});

it('reports the provider error and marks the connection failed when the test is rejected', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create([
        'credentials' => ['account_sid' => 'ACtest', 'auth_token' => 'tok'],
    ]);

    Http::fake([
        'https://api.twilio.com/*' => Http::response(
            ProviderFixtures::body('twilio', 'send_auth_error'),
            ProviderFixtures::status('twilio', 'send_auth_error'),
        ),
    ]);

    postJson(providerEndpoint('test', $connection->id), ['to' => '0501234567'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Authenticate');

    $connection->refresh();

    expect($connection->status->value)->toBe('error')
        ->and($connection->last_error)->toBe('Authenticate');
});

it('syncs WhatsApp templates and stores their statuses', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->whatsapp()->create([
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    Http::fake([
        'https://graph.facebook.com/v21.0/1111111111/message_templates*' => Http::response(
            ProviderFixtures::body('meta_cloud', 'templates'),
            200,
        ),
    ]);

    postJson(providerEndpoint('sync_templates', $connection->id))
        ->assertOk()
        ->assertJsonPath('data.synced', 2);

    $templates = WhatsAppTemplate::query()->where('provider_connection_id', $connection->id)->get();

    expect($templates)->toHaveCount(3)
        ->and($templates->firstWhere('name', 'hello_world')->status)->toBe('approved')
        ->and($templates->firstWhere('name', 'order_update')->status)->toBe('approved')
        ->and($templates->firstWhere('name', 'promo_october')->status)->toBe('paused');
});

it('stores the default connection of a sales channel', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create();
    $channel = core()->getAllChannels()->first();

    putJson(providerEndpoint('defaults'), [
        'channel_id'             => $channel->id,
        'message_channel'        => 'sms',
        'provider_connection_id' => $connection->id,
    ])->assertOk();

    $this->assertDatabaseHas('automation_provider_channel_defaults', [
        'channel_id'             => $channel->id,
        'message_channel'        => 'sms',
        'provider_connection_id' => $connection->id,
    ]);
});

it('blocks deleting a connection that backs an active flow default', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create();
    $channel = core()->getAllChannels()->first();

    app(ProviderConnectionRepository::class)
        ->setDefault($channel->id, MessageChannel::Sms, $connection->id);

    $flow = Flow::factory()->active()->create();
    $flow->channels()->attach([$channel->id]);

    deleteJson(providerEndpoint('delete', $connection->id))
        ->assertUnprocessable()
        ->assertJsonPath('message', trans('marketing_automation::app.providers.delete-blocked'));

    $this->assertModelExists($connection);
});

it('allows deleting a connection no active flow depends on', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create();

    deleteJson(providerEndpoint('delete', $connection->id))->assertOk();

    $this->assertModelMissing($connection);
});

it('forbids a limited admin from managing providers', function () {
    $role = Role::factory()->create([
        'name'            => 'Limited',
        'permission_type' => 'custom',
        'permissions'     => ['dashboard'],
    ]);

    $this->loginAsAdmin(Admin::factory()->create(['role_id' => $role->id]));

    $connection = ProviderConnection::factory()->sms()->create();

    getJson(providerEndpoint('show', $connection->id))->assertUnauthorized();

    postJson(providerEndpoint('store'), ['name' => 'X'])->assertUnauthorized();

    putJson(providerEndpoint('defaults'), [])->assertUnauthorized();

    deleteJson(providerEndpoint('delete', $connection->id))->assertUnauthorized();
});

it('keeps the stored secret when the masked values from show are echoed back', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create([
        'credentials' => ['account_sid' => 'ACecho', 'auth_token' => 'echoed-secret-value'],
    ]);

    $shown = getJson(providerEndpoint('show', $connection->id))
        ->assertOk()
        ->json('data');

    putJson(providerEndpoint('update', $connection->id), [
        'name'            => $shown['name'],
        'message_channel' => $shown['message_channel'],
        'driver'          => $shown['driver'],
        'sender'          => $shown['sender'],
        'credentials'     => $shown['credentials'],
    ])->assertOk();

    $connection->refresh();

    expect($shown['credentials']['auth_token'])->toStartWith('••••')
        ->and($connection->credentials['auth_token'])->toBe('echoed-secret-value')
        ->and($connection->credentials['account_sid'])->toBe('ACecho');
});

it('exposes the webhook url and the Meta verify token in the show endpoint', function () {
    $this->loginAsAdmin();

    $meta = ProviderConnection::factory()->whatsapp()->create([
        'driver'      => 'meta_cloud',
        'credentials' => ['access_token' => 'mtok', 'phone_number_id' => 'PN1', 'waba_id' => '1111111111', 'app_secret' => 'msecret'],
    ]);

    getJson(providerEndpoint('show', $meta->id))
        ->assertOk()
        ->assertJsonPath('data.webhook_url', route('automation.webhooks.receive', ['meta_cloud', $meta->uuid]))
        ->assertJsonPath('data.verify_token', $meta->webhook_secret);

    $msegat = ProviderConnection::factory()->sms()->create(['driver' => 'msegat']);

    getJson(providerEndpoint('show', $msegat->id))
        ->assertOk()
        ->assertJsonPath('data.webhook_url', route('automation.webhooks.receive', ['msegat', $msegat->uuid]).'?s='.$msegat->webhook_secret)
        ->assertJsonPath('data.verify_token', null);
});

it('refuses to sync templates for an SMS connection', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create();

    Http::fake();

    postJson(providerEndpoint('sync_templates', $connection->id))
        ->assertUnprocessable()
        ->assertJsonPath('message', trans('marketing_automation::app.providers.templates-not-supported'));

    Http::assertNothingSent();
});

it('clears the default connection of a sales channel', function () {
    $this->loginAsAdmin();

    $connection = ProviderConnection::factory()->sms()->create();
    $channel = core()->getAllChannels()->first();

    app(ProviderConnectionRepository::class)
        ->setDefault($channel->id, MessageChannel::Sms, $connection->id);

    putJson(providerEndpoint('defaults'), [
        'channel_id'             => $channel->id,
        'message_channel'        => 'sms',
        'provider_connection_id' => null,
    ])->assertOk();

    $this->assertDatabaseMissing('automation_provider_channel_defaults', [
        'channel_id'      => $channel->id,
        'message_channel' => 'sms',
    ]);
});

it('renders the drawer and modal slots on the providers page', function () {
    $this->loginAsAdmin();

    $this->get(providerEndpoint('index'))
        ->assertOk()
        ->assertSee(trans('marketing_automation::app.providers.form.webhook-url'))
        ->assertSee(trans('marketing_automation::app.providers.test.recipient'))
        ->assertDontSee('<template #header>', false);
});
