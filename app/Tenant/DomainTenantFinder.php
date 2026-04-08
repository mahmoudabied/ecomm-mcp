<?php

namespace App\Tenant;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class DomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        $host = $request->getHost();

        return \Spatie\Multitenancy\Models\Tenant::where(domain, $host)->first();
    }
}


