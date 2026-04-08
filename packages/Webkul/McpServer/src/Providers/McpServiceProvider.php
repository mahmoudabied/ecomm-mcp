<?php

namespace Webkul\McpServer\Providers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
            throw new \RuntimeException(
                'webkul/mcp-server requires laravel/mcp. Run: composer require laravel/mcp'
            );
        }

        $this->mergeConfigFrom(__DIR__.'/../../config/mcp.php', 'mcp');

        $this->commands([
            \Webkul\McpServer\Console\Commands\McpAgentCommand::class,
        ]);

        config([
            'auth.guards.customer-jwt' => [
                'driver'   => 'jwt',
                'provider' => 'customers',
            ],
            'auth.guards.admin-jwt' => [
                'driver'   => 'jwt',
                'provider' => 'admins',
            ],
            'auth.providers.admins' => [
                'driver' => 'eloquent',
                'model'  => \Webkul\User\Models\Admin::class,
            ],
        ]);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/mcp.php');

        $this->mergeConfigFrom(__DIR__.'/../../config/acl.php', 'acl');

        Route::aliasMiddleware(
            'mcp.resolve-channel',
            \Webkul\McpServer\Http\Middleware\ResolveChannel::class,
        );

        $this->publishes([
            __DIR__.'/../../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');

        $jwtContract = \PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject::class;

        foreach ([\Webkul\Customer\Models\Customer::class, \Webkul\User\Models\Admin::class] as $model) {
            if (! in_array($jwtContract, class_implements($model) ?: [])) {
                Log::warning(
                    "[webkul/mcp-server] {$model} must implement JWTSubject for JWT auth to work."
                );
            }
        }

        $this->registerAuthenticationExceptionHandler();
    }

    protected function registerAuthenticationExceptionHandler(): void
    {
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->renderable(
            function (AuthenticationException $e, Request $request) {
                if ($request->is('api/mcp/*')) {
                    return response()->json([
                        'error'   => true,
                        'code'    => 'UNAUTHENTICATED',
                        'message' => 'Authentication required.',
                    ], 401);
                }
            }
        );
    }
}
