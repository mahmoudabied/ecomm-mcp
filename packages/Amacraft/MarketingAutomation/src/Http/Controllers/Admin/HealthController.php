<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Amacraft\MarketingAutomation\Services\HealthCheck;

class HealthController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected HealthCheck $healthCheck) {}

    /**
     * Display the setup health page.
     *
     * @return View|RedirectResponse
     */
    public function index()
    {
        if (! bouncer()->hasPermission('marketing.automation.settings')) {
            abort(401, 'This action is unauthorized.');
        }

        return view('marketing_automation::admin.health.index', [
            'report' => $this->healthCheck->report(),
        ]);
    }
}
