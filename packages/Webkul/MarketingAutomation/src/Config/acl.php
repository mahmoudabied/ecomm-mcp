<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketing Automation
    |--------------------------------------------------------------------------
    |
    | All ACLs related to the marketing automation module will be placed here.
    |
    */
    [
        'key'   => 'marketing.automation',
        'name'  => 'marketing_automation::app.acl.automation',
        'route' => 'admin.marketing.automation.flows.index',
        'sort'  => 3,
    ], [
        'key'   => 'marketing.automation.flows',
        'name'  => 'marketing_automation::app.acl.flows',
        'route' => 'admin.marketing.automation.flows.index',
        'sort'  => 1,
    ], [
        'key'   => 'marketing.automation.flows.manage',
        'name'  => 'marketing_automation::app.acl.flows-manage',
        'route' => 'admin.marketing.automation.flows.index',
        'sort'  => 2,
    ], [
        'key'   => 'marketing.automation.reports',
        'name'  => 'marketing_automation::app.acl.reports',
        'route' => 'admin.marketing.automation.flows.report',
        'sort'  => 3,
    ], [
        'key'   => 'marketing.automation.settings',
        'name'  => 'marketing_automation::app.acl.settings',
        'route' => 'admin.marketing.automation.providers.index',
        'sort'  => 4,
    ], [
        'key'   => 'marketing.automation.klaviyo',
        'name'  => 'marketing_automation::app.acl.klaviyo',
        'route' => 'admin.marketing.klaviyo.index',
        'sort'  => 5,
    ],
];
