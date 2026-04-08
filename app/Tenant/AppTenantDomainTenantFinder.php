<?php

namespace App\Tenant;

use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;
use Illuminate\Http\Request;

class AppTenantDomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        $host = $request->getHost();

        return Tenant::query()
            ->where('domain', $host)
            ->first();
    }
}
