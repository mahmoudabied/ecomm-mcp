<?php

namespace Amacraft\MarketingAutomation\Http;

class CsrfExemption
{
    /**
     * Every CSRF middleware a host's web group may run, so token-signed public routes skip it on any Laravel version.
     * Laravel 13 made PreventRequestForgery the base class; excluding only an older child would leave it in place.
     *
     * @var array<int, string>
     */
    public const MIDDLEWARE = [
        'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
    ];
}
