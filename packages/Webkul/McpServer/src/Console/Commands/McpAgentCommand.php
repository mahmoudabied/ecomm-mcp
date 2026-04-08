<?php

namespace Webkul\McpServer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Testing\FakeTransporter;
use Webkul\Core\Models\Channel;
use Webkul\Customer\Models\Customer;
use Webkul\McpServer\Mcp\Admin\AdminMcpServer;
use Webkul\McpServer\Mcp\Shop\ShopMcpServer;
use Webkul\User\Models\Admin;

class McpAgentCommand extends Command
{
    protected $signature = 'mcp:agent
                            {--server=both : Which server to test (shop, admin, both)}
                            {--channel= : Channel code to use}';

    protected $description = 'Run an MCP client agent that tests all registered tools via the MCP protocol';

    protected array $results = [];

    protected int $totalPassed = 0;

    protected int $totalFailed = 0;

    public function handle(): int
    {
        $this->setupAuth();
        $this->info('');
        $this->info('=============================================');
        $this->info('   MCP Server Agent - Interactive Tester     ');
        $this->info('=============================================');
        $this->info('');

        $server = $this->option('server');

        if (in_array($server, ['shop', 'both'])) {
            $this->testServer('shop', ShopMcpServer::class, 'customer-jwt');
        }

        if (in_array($server, ['admin', 'both'])) {
            $this->testServer('admin', AdminMcpServer::class, 'admin-jwt');
        }

        $this->printSummary();

        return $this->totalFailed > 0 ? 1 : 0;
    }

    protected function setupAuth(): void
    {
        config([
            'auth.guards.customer-jwt' => [
                'driver'   => 'jwt',
                'provider' => 'customers',
            ],
            'auth.guards.admin-jwt' => [
                'driver'   => 'jwt',
                'provider' => 'admins',
            ],
            'auth.providers.customers' => [
                'driver' => 'eloquent',
                'model'  => Customer::class,
            ],
            'auth.providers.admins' => [
                'driver' => 'eloquent',
                'model'  => Admin::class,
            ],
        ]);
    }

    protected function testServer(string $name, string $serverClass, string $guard): void
    {
        $this->info("┌── Testing MCP Server: <fg=cyan;options=bold>{$name}</>");
        $this->info('│');

        $channel = $this->resolveChannel();
        request()->attributes->set('mcp_channel', $channel);

        $this->authenticateGuard($guard);

        $server = Container::getInstance()->make($serverClass, [
            'transport' => new FakeTransporter,
        ]);
        $server->start();

        $context = $server->createContext();

        $this->info("│ Server: <fg=green>{$context->serverName}</> v{$context->serverVersion}");
        $this->info("│ Instructions: {$context->instructions}");
        $this->info('│');

        $this->testInitialize($server, $context);
        $this->testPing($server, $context);

        $tools = $context->tools();
        $toolNames = $tools->map(fn ($t) => $t->name())->toArray();
        $this->info('│ Registered tools: <fg=yellow>'.implode(', ', $toolNames).'</>');
        $this->info('│');

        foreach ($tools as $tool) {
            $this->testTool($server, $context, $tool);
        }

        $this->info('│');
        $this->info("└── Server <fg=cyan>{$name}</> test complete");
        $this->info('');
    }

    protected function resolveChannel(): Channel
    {
        $channelCode = $this->option('channel');

        if ($channelCode) {
            $channel = Channel::where('code', $channelCode)->first();
            if ($channel) {
                return $channel;
            }
            $this->warn("Channel '{$channelCode}' not found, using default.");
        }

        return core()->getCurrentChannel();
    }

    protected function authenticateGuard(string $guard): void
    {
        if ($guard === 'customer-jwt') {
            $user = Customer::first();
            if (! $user) {
                $user = Customer::factory()->create();
                $this->warn("│ Created test customer: {$user->email}");
            } else {
                $this->info("│ Authenticated as customer: {$user->email}");
            }
            Auth::guard('customer-jwt')->setUser($user);
        } else {
            $user = Admin::first();
            if (! $user) {
                $user = Admin::factory()->create();
                $this->warn("│ Created test admin: {$user->email}");
            } else {
                $this->info("│ Authenticated as admin: {$user->email}");
            }
            Auth::guard('admin-jwt')->setUser($user);
            Auth::guard('admin')->setUser($user);
        }
    }

    protected function sendJsonRpc(Server $server, string $method, array $params = [], int|string $id = 1): ?array
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'method'  => $method,
            'params'  => $params,
        ]);

        $capturedResponse = null;

        $transport = new class($capturedResponse) extends FakeTransporter
        {
            public ?string $lastMessage = null;

            public function send(string $message, ?string $sessionId = null): void
            {
                $this->lastMessage = $message;
            }
        };

        $originalTransport = (fn () => $this->transport)->call($server);

        (fn () => $this->transport = $transport)->call($server);

        try {
            $server->handle($payload);
        } catch (\Throwable $e) {
            (fn () => $this->transport = $originalTransport)->call($server);

            return [
                'error' => [
                    'code'    => -32603,
                    'message' => $e->getMessage(),
                ],
            ];
        }

        (fn () => $this->transport = $originalTransport)->call($server);

        if ($transport->lastMessage === null) {
            return null;
        }

        return json_decode($transport->lastMessage, true);
    }

    protected function testInitialize(Server $server, $context): void
    {
        $response = $this->sendJsonRpc($server, 'initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => new \stdClass,
            'clientInfo'      => [
                'name'    => 'mcp-test-agent',
                'version' => '1.0.0',
            ],
        ]);

        $status = $this->checkPassFail(
            'initialize',
            $response !== null
                && isset($response['result']['serverInfo']['name'])
                && $response['result']['serverInfo']['name'] === $context->serverName,
            'Server initialization via MCP protocol'
        );
    }

    protected function testPing(Server $server, $context): void
    {
        $response = $this->sendJsonRpc($server, 'ping');
        $this->checkPassFail(
            'ping',
            $response !== null && isset($response['result']),
            'Ping server via MCP protocol'
        );
    }

    protected function testTool(Server $server, $context, $tool): void
    {
        $toolName = $tool->name();
        $description = $tool->description();

        $args = $this->getToolArguments($toolName);

        $response = $this->sendJsonRpc($server, 'tools/call', [
            'name'      => $toolName,
            'arguments' => $args,
        ]);

        $passed = false;
        $detail = '';

        if ($response === null) {
            $detail = 'No response received';
        } elseif (isset($response['error'])) {
            $errorMsg = $response['error']['message'] ?? 'Unknown error';
            if ($this->isExpectedError($toolName, $errorMsg)) {
                $passed = true;
                $detail = "Expected error: {$errorMsg}";
            } else {
                $detail = "Error: {$errorMsg}";
            }
        } elseif (isset($response['result'])) {
            $isError = $response['result']['isError'] ?? false;
            $content = $response['result']['content'] ?? [];

            if ($isError) {
                $errorText = collect($content)
                    ->filter(fn ($c) => ($c['type'] ?? '') === 'text')
                    ->map(fn ($c) => $c['text'] ?? '')
                    ->implode(' ');

                if ($this->isExpectedError($toolName, $errorText)) {
                    $passed = true;
                    $detail = "Expected: {$errorText}";
                } else {
                    $passed = false;
                    $detail = "Tool error: {$errorText}";
                }
            } else {
                $passed = true;
                $textPreview = collect($content)
                    ->filter(fn ($c) => ($c['type'] ?? '') === 'text')
                    ->map(fn ($c) => mb_substr($c['text'] ?? '', 0, 120))
                    ->implode(' ');
                $detail = mb_substr($textPreview, 0, 150);
            }
        }

        $this->checkPassFail("tools/call::{$toolName}", $passed, $description, $detail);
    }

    protected function getToolArguments(string $toolName): array
    {
        return match ($toolName) {
            'hello-world-tool' => [],
            'search-products'  => [
                'query'    => 'test',
                'per_page' => 5,
            ],
            'get-cart-details'   => [],
            'check-order-status' => [
                'order_id' => '999999999',
            ],
            'update-inventory' => [
                'sku' => 'NON-EXISTENT-TEST-SKU',
                'qty' => 100,
            ],
            'get-sales-analytics' => [
                'date_from' => now()->subMonth()->format('Y-m-d'),
                'date_to'   => now()->format('Y-m-d'),
            ],
            'manage-customers' => [
                'customer_id' => 999999,
                'action'      => 'get_details',
            ],
            default => [],
        };
    }

    protected function isExpectedError(string $toolName, string $errorMsg): bool
    {
        return match ($toolName) {
            'check-order-status' => str_contains($errorMsg, 'not found'),
            'update-inventory'   => str_contains($errorMsg, 'not found') || str_contains($errorMsg, 'Permission'),
            'manage-customers'   => str_contains($errorMsg, 'not found') || str_contains($errorMsg, 'Permission'),
            default              => false,
        };
    }

    protected function checkPassFail(string $testName, bool $passed, string $description, string $detail = ''): bool
    {
        $this->results[$testName] = [
            'passed'      => $passed,
            'description' => $description,
            'detail'      => $detail,
        ];

        if ($passed) {
            $this->totalPassed++;
            $icon = '<fg=green>✓</>';
            $label = '<fg=green>PASS</>';
        } else {
            $this->totalFailed++;
            $icon = '<fg=red>✗</>';
            $label = '<fg=red;options=bold>FAIL</>';
        }

        $line = "│ {$icon} {$label} {$testName}";

        if ($detail) {
            $line .= " <fg=gray>{$detail}</>";
        }

        $this->info($line);

        return $passed;
    }

    protected function printSummary(): void
    {
        $total = $this->totalPassed + $this->totalFailed;
        $this->info('┌── Summary');
        $this->info('│');
        $this->info("│ Total tests:  <fg=white;options=bold>{$total}</>");
        $this->info("│ Passed:       <fg=green;options=bold>{$this->totalPassed}</>");
        $this->info('│ Failed:       <fg='.($this->totalFailed > 0 ? 'red' : 'green').";options=bold>{$this->totalFailed}</>");
        $this->info('│');

        if ($this->totalFailed === 0) {
            $this->info('│ <fg=green;options=bold>ALL MCP TOOLS ARE WORKING CORRECTLY!</>');
        } else {
            $this->info('│ <fg=red;options=bold>SOME TOOLS HAVE ISSUES - SEE ABOVE</>');
            $this->info('│');
            $this->info('│ Failed tests:');
            foreach ($this->results as $name => $result) {
                if (! $result['passed']) {
                    $this->info("│   <fg=red>✗</> {$name}: {$result['detail']}");
                }
            }
        }

        $this->info('│');
        $this->info('└── Done');
        $this->info('');
    }
}
