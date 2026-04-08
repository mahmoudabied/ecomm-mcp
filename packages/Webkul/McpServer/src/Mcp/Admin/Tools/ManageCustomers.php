<?php

namespace Webkul\McpServer\Mcp\Admin\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\McpServer\Concerns\ResolvesChannel;
use Webkul\McpServer\Enums\McpErrorCode;
use Webkul\McpServer\Traits\FormatsResponse;

#[Description('Activate, deactivate, or retrieve details for a customer. Requires customer management permission.')]
class ManageCustomers extends Tool
{
    use FormatsResponse;
    use ResolvesChannel;

    public function __construct(
        protected CustomerRepository $customerRepository,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('Bagisto customer ID')->required(),
            'action'      => $schema->string()
                ->enum(['activate', 'deactivate', 'get_details'])
                ->description('Action to perform')
                ->required(),
        ];
    }

    public function handle(McpRequest $request): Response
    {
        $admin = auth()->guard('admin-jwt')->user();
        if (! $admin) {
            return Response::error('Unauthorized', McpErrorCode::UNAUTHORIZED);
        }

        if (! bouncer()->hasPermission('mcp.admin.customers.manage')) {
            return Response::error('Permission denied', McpErrorCode::PERMISSION_DENIED);
        }

        $customerId = (int) $request->get('customer_id');
        $action = $request->get('action');

        $customer = $this->customerRepository->find($customerId);

        if (! $customer) {
            return Response::error("Customer not found: {$customerId}", McpErrorCode::NOT_FOUND);
        }

        if ($action === 'activate') {
            $this->customerRepository->update(['status' => 1], $customer->id);
            $customer->refresh();

            Log::info('[MCP] ManageCustomers activated', [
                'admin_id'    => $admin->id,
                'customer_id' => $customer->id,
                'action'      => 'activate',
            ]);
        } elseif ($action === 'deactivate') {
            $this->customerRepository->update(['status' => 0], $customer->id);
            $customer->refresh();

            Log::info('[MCP] ManageCustomers deactivated', [
                'admin_id'    => $admin->id,
                'customer_id' => $customer->id,
                'action'      => 'deactivate',
            ]);
        }

        return $this->jsonResponse([
            'customer_id'      => $customer->id,
            'name'             => trim($customer->first_name . ' ' . $customer->last_name),
            'email'            => $customer->email,
            'status'           => (bool) $customer->status,
            'customer_group'   => $customer->group?->name,
            'action_performed' => $action === 'get_details' ? 'none' : $action,
        ]);
    }
}
