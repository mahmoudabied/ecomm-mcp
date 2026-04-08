<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;
use App\Tenant\TenantDatabaseManager;
use App\Tenant\TenantDNSManager;

class TenantDashboardController extends Controller
{
    protected $databaseManager;
    protected $dnsManager;

    public function __construct(TenantDatabaseManager $databaseManager, TenantDNSManager $dnsManager)
    {
        $this->databaseManager = $databaseManager;
        $this->dnsManager = $dnsManager;
    }

    public function dashboard()
    {
        $statistics = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'storage_usage' => $this->calculateStorageUsage(),
            'database_usage' => $this->calculateDatabaseUsage(),
        ];

        $recentTenants = Tenant::latest()->take(5)->get();

        return view('admin.tenants.dashboard', compact('statistics', 'recentTenants'));
    }

    public function status()
    {
        $tenants = Tenant::with(['domains'])
            ->withCount(['users', 'orders'])
            ->paginate(10);

        return view('admin.tenants.status', compact('tenants'));
    }

    protected function calculateStorageUsage()
    {
        // Implementation to calculate storage usage across all tenants
        return [
            'total' => '100GB',
            'used' => '45GB',
            'available' => '55GB'
        ];
    }

    protected function calculateDatabaseUsage()
    {
        // Implementation to calculate database usage across all tenants
        return [
            'total_size' => '50GB',
            'total_tables' => Tenant::count() * 50, // Approximate tables per tenant
            'backup_size' => '30GB'
        ];
    }
}
