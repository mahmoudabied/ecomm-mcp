<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\User\Models\Admin;
use Webkul\User\Models\Role;

function automationAdminRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.marketing.automation.')
            || str_starts_with((string) $route->getName(), 'admin.marketing.klaviyo.'))
        ->all();
}

it('shows the flows menu but not providers to an admin with only the flows permission', function () {
    $role = Role::factory()->create([
        'name'            => 'Flows Only',
        'permission_type' => 'custom',
        'permissions'     => ['dashboard', 'marketing', 'marketing.automation', 'marketing.automation.flows'],
    ]);

    $this->loginAsAdmin(Admin::factory()->create(['role_id' => $role->id]));

    $this->get(route('admin.marketing.automation.flows.index'))
        ->assertOk()
        ->assertSee(route('admin.marketing.automation.flows.index'), false)
        ->assertDontSee(route('admin.marketing.automation.providers.index'), false)
        ->assertDontSee(route('admin.marketing.klaviyo.index'), false);
});

it('rejects every automation admin route for an admin without permissions', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $role = Role::factory()->create([
        'name'            => 'No Automation',
        'permission_type' => 'custom',
        'permissions'     => ['dashboard'],
    ]);

    $this->loginAsAdmin(Admin::factory()->create(['role_id' => $role->id]));

    // Real rows for every route binding, so model resolution never 404s
    // before the controller's permission check can answer.
    $bindings = [
        'flow'      => Flow::factory()->create()->id,
        'provider'  => ProviderConnection::factory()->create()->id,
        'enrolment' => Enrolment::factory()->create()->id,
        'stepKey'   => 's1',
    ];

    foreach (automationAdminRoutes() as $route) {
        $methods = collect($route->methods())
            ->reject(fn ($method) => in_array($method, ['HEAD', 'OPTIONS']))
            ->values();

        $parameters = collect($route->parameterNames())
            ->mapWithKeys(fn (string $parameter) => [$parameter => $bindings[$parameter] ?? null])
            ->filter()
            ->all();

        $response = $this->call($methods->first(), route($route->getName(), $parameters));

        expect($response->status())
            ->toBe(401, "Route [{$route->getName()}] returned {$response->status()} for an admin without permissions.");
    }
});
