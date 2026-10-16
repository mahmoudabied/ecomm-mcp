<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Mail\AutomationMail;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Repositories\ProviderConnectionRepository;
use Webkul\MarketingAutomation\Tests\ProviderFixtures;
use Webkul\User\Models\Admin;
use Webkul\User\Models\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

function flowsEndpoint(string $action, ...$params): string
{
    $routes = [
        'index'         => 'admin.marketing.automation.flows.index',
        'templates'     => 'admin.marketing.automation.flows.templates',
        'store'         => 'admin.marketing.automation.flows.store',
        'edit'          => 'admin.marketing.automation.flows.edit',
        'update'        => 'admin.marketing.automation.flows.update',
        'activate'      => 'admin.marketing.automation.flows.activate',
        'pause'         => 'admin.marketing.automation.flows.pause',
        'resume'        => 'admin.marketing.automation.flows.resume',
        'duplicate'     => 'admin.marketing.automation.flows.duplicate',
        'delete'        => 'admin.marketing.automation.flows.delete',
        'test'          => 'admin.marketing.automation.flows.test',
        'preview'       => 'admin.marketing.automation.flows.preview',
        'preview_draft' => 'admin.marketing.automation.flows.preview_draft',
    ];

    return route($routes[$action], ...$params);
}

function validFlowPayload(array $overrides = []): array
{
    return [
        'name'                 => 'Welcome series',
        'trigger_type'         => 'customer_registered',
        'trigger_config'       => [],
        'type'                 => 'marketing',
        'channel_ids'          => [core()->getAllChannels()->first()->id],
        'reentry_window_hours' => 168,
        'exit_goals'           => [['type' => 'order_placed']],
        ...$overrides,
    ];
}

it('stores a flow in the draft state', function () {
    $this->loginAsAdmin();

    postJson(flowsEndpoint('store'), validFlowPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.name', 'Welcome series');
});

it('returns the prebuilt templates', function () {
    $this->loginAsAdmin();

    getJson(flowsEndpoint('templates'))
        ->assertOk()
        ->assertJsonPath('data.0.key', 'welcome');
});

it('validates and publishes a version on activate', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create();

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    postJson(flowsEndpoint('activate', $flow->id))
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.version', 1);

    expect($flow->refresh()->currentVersion->version)->toBe(1);
});

it('rejects activation of an invalid definition with per-step errors', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create();

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's404'],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    postJson(flowsEndpoint('activate', $flow->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('steps.s1');
});

it('sends an email test for a step without an enrolment', function () {
    Mail::fake();

    $this->loginAsAdmin();

    $flow = Flow::factory()->create();

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Test'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    postJson(flowsEndpoint('test', ['flow' => $flow->id, 'stepKey' => 's1']), [
        'recipient' => 'tester@example.com',
        'locale'    => 'en',
        'sample'    => 'sample',
    ])->assertOk()
        ->assertJsonPath('data.status', 'sent');

    $log = MessageLog::query()->first();

    expect($log->is_test)->toBeTrue()
        ->and($log->enrolment_id)->toBeNull()
        ->and($log->recipient)->toBe('tester@example.com');

    Mail::assertSent(AutomationMail::class, 1);
});

it('sends an SMS test through the default provider', function () {
    $this->loginAsAdmin();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(
            ProviderFixtures::body('twilio', 'send_success'),
            ProviderFixtures::status('twilio', 'send_success'),
        ),
    ]);

    $connection = ProviderConnection::factory()->sms()->connected()->create();

    app(ProviderConnectionRepository::class)->setDefault(
        core()->getAllChannels()->first()->id,
        MessageChannel::Sms,
        $connection->id,
    );

    $flow = Flow::factory()->create();

    $flow->update(['draft_definition' => [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_sms', 'body' => ['en' => 'Test SMS'], 'validity_hours' => 24, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ]]);

    $flow->channels()->sync([core()->getAllChannels()->first()->id]);

    postJson(flowsEndpoint('test', ['flow' => $flow->id, 'stepKey' => 's1']), [
        'recipient' => '0501234567',
        'locale'    => 'en',
        'sample'    => 'sample',
    ])->assertOk()
        ->assertJsonPath('data.status', 'sent');

    expect(MessageLog::first()->message_channel->value)->toBe('sms')
        ->and(MessageLog::first()->provider_connection_id)->toBe($connection->id);
});

it('pauses, resumes and duplicates a flow', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create(['name' => 'Original', 'status' => 'active']);

    postJson(flowsEndpoint('pause', $flow->id))->assertOk()->assertJsonPath('data.status', 'paused');
    postJson(flowsEndpoint('resume', $flow->id))->assertOk()->assertJsonPath('data.status', 'active');

    postJson(flowsEndpoint('duplicate', $flow->id))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Original (copy)')
        ->assertJsonPath('data.status', 'draft');
});

it('refuses to pause a flow that is not active or resume one that is not paused', function () {
    $this->loginAsAdmin();

    $draft = Flow::factory()->create(['status' => 'draft']);

    postJson(flowsEndpoint('pause', $draft->id))
        ->assertUnprocessable()
        ->assertJsonPath('message', trans('marketing_automation::app.flows.not-active'));

    postJson(flowsEndpoint('resume', $draft->id))
        ->assertUnprocessable()
        ->assertJsonPath('message', trans('marketing_automation::app.flows.not-paused'));

    expect($draft->refresh()->status->value)->toBe('draft');
});

it('deletes a flow', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create();

    deleteJson(flowsEndpoint('delete', $flow->id))->assertOk();

    expect(Flow::query()->whereKey($flow->id)->exists())->toBeFalse();
});

it('shows the flows index to a reader but blocks writes without manage', function () {
    // The section key rides along because the admin layout renders the menu
    // from the granted ACL keys; the manage permission stays excluded.
    $role = Role::factory()->create([
        'name'            => 'Flow reader',
        'permission_type' => 'custom',
        'permissions'     => ['marketing', 'marketing.automation', 'marketing.automation.flows'],
    ]);

    $this->loginAsAdmin(Admin::factory()->create(['role_id' => $role->id]));

    get(flowsEndpoint('index'))->assertOk();

    postJson(flowsEndpoint('store'), validFlowPayload())->assertUnauthorized();

    putJson(flowsEndpoint('update', Flow::factory()->create()->id), validFlowPayload())->assertUnauthorized();

    postJson(flowsEndpoint('activate', Flow::factory()->create()->id))->assertUnauthorized();
});

it('updates the draft definition of a flow', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create();

    $definition = [
        'entry' => 's1',
        'steps' => [
            's1' => ['type' => 'send_email', 'subject' => ['en' => 'Hi'], 'blocks' => ['en' => [['type' => 'text', 'text' => 'x']]], 'validity_hours' => 24, 'next' => 's2'],
            's2' => ['type' => 'exit'],
        ],
    ];

    putJson(flowsEndpoint('update', $flow->id), validFlowPayload([
        'draft_definition' => $definition,
    ]))->assertOk();

    // MySQL normalises JSON key order, so compare structurally.
    expect($flow->refresh()->draft_definition)->toEqualCanonicalizing($definition);
});

it('previews an unsaved draft step for the block editor', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create();

    $step = [
        'type'    => 'send_email',
        'subject' => ['en' => 'Draft subject'],
        'blocks'  => ['en' => [['type' => 'text', 'text' => 'Hello editor']]],
    ];

    post(flowsEndpoint('preview_draft', $flow->id), [
        'step'   => $step,
        'locale' => 'en',
    ])
        ->assertOk()
        ->assertSee('Hello editor', false);
});
