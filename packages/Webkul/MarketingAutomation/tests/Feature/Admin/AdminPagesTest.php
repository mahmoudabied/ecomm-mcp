<?php

use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\User\Models\Admin;
use Webkul\User\Models\Role;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

it('renders the flows index page without errors', function () {
    $this->loginAsAdmin();

    get(route('admin.marketing.automation.flows.index'))
        ->assertOk()
        ->assertSee(trans('marketing_automation::app.flows.index.title'));
});

it('renders the flow builder page without errors', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create(['name' => 'Builder test']);

    get(route('admin.marketing.automation.flows.edit', $flow->id))
        ->assertOk()
        ->assertSee('Builder test');
});

it('renders builder props that survive inside single-quoted attributes', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create();

    $html = get(route('admin.marketing.automation.flows.edit', $flow->id))->assertOk()->getContent();

    // A raw single quote inside a :prop='...' attribute ends it early and breaks the whole Vue app.
    preg_match('/<v-flow-builder\b[^>]*>/', $html, $tag);

    preg_match_all("/\s:[\w-]+='([^']*)'/", $tag[0], $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $value) {
        expect(json_decode(html_entity_decode($value), true))->not->toBeNull();
    }
});

it('renders the drawer and modal content of the flow pages', function () {
    $this->loginAsAdmin();

    $flow = Flow::factory()->create();

    // Vue <template #content> inside the Blade drawer is dropped; only <x-slot:content> renders.
    get(route('admin.marketing.automation.flows.index'))
        ->assertOk()
        ->assertSee('v-model="form.template_key"', false);

    get(route('admin.marketing.automation.flows.edit', $flow->id))
        ->assertOk()
        ->assertSee('v-model="editing.mode"', false)
        ->assertSee('v-model="testForm.recipient"', false);
});

it('renders the providers page without errors', function () {
    $this->loginAsAdmin();

    get(route('admin.marketing.automation.providers.index'))
        ->assertOk()
        ->assertSee(trans('marketing_automation::app.providers.index.title'));
});

it('renders the health page without errors', function () {
    $this->loginAsAdmin();

    get(route('admin.marketing.automation.health.index'))
        ->assertOk();
});

it('feeds the flows datagrid over AJAX', function () {
    $this->loginAsAdmin();

    Flow::factory()->create(['name' => 'Datagrid flow']);

    $response = getJson(
        route('admin.marketing.automation.flows.index'),
        ['X-Requested-With' => 'XMLHttpRequest'],
    );

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Datagrid flow']);
});

it('feeds the providers datagrid over AJAX', function () {
    $this->loginAsAdmin();

    ProviderConnection::factory()->sms()->create(['name' => 'Datagrid provider']);

    $response = getJson(
        route('admin.marketing.automation.providers.index'),
        ['X-Requested-With' => 'XMLHttpRequest'],
    );

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Datagrid provider']);
});

it('hides the flows pages from an admin without the ACL permission', function () {
    $role = Role::factory()->create([
        'name'            => 'No automation',
        'permission_type' => 'custom',
        'permissions'     => ['dashboard'],
    ]);

    $this->loginAsAdmin(Admin::factory()->create(['role_id' => $role->id]));

    get(route('admin.marketing.automation.flows.index'))->assertUnauthorized();

    get(route('admin.marketing.automation.providers.index'))->assertUnauthorized();
});
