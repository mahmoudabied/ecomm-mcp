<?php

namespace Webkul\MarketingAutomation\Http\Controllers\Admin;

use Webkul\Admin\Http\Controllers\Controller;

class PlaceholderController extends Controller
{
    /**
     * Page titles per route, all shown until the real screens ship.
     */
    protected const TITLES = [
        'admin.marketing.automation.flows.index'     => 'marketing_automation::app.menu.flows',
        'admin.marketing.automation.providers.index' => 'marketing_automation::app.menu.providers',
    ];

    /**
     * Render the placeholder page for an upcoming admin screen.
     */
    public function index()
    {
        return view('marketing_automation::admin.placeholder', [
            'title' => trans(self::TITLES[request()->route()->getName()] ?? 'marketing_automation::app.menu.automation'),
        ]);
    }
}
