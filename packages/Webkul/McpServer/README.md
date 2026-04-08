# Bagisto MCP Server

Expose your Bagisto store to AI agents via the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/). This package provides two authenticated MCP endpoints — one for customers (shop) and one for admins — plus a Stdio CLI transport for local development.

## Requirements

- PHP 8.4+
- Laravel 13.x
- Bagisto 2.x
- `laravel/mcp` ^0.6

## Installation

```bash
composer require webkul/mcp-server
```

The package is auto-discovered via Laravel's package discovery.

## Setup

### 1. Publish the config

```bash
php artisan vendor:publish --tag=mcp-config
```

### 2. Publish and configure JWT

```bash
php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

### 3. Add `JWTSubject` to your models

Both `Customer` and `Admin` models must implement `PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject`.

**Customer** (`packages/Webkul/Customer/src/Models/Customer.php`):

```php
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Customer extends Authenticatable implements JWTSubject
{
    // ... existing code ...

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
```

**Admin** (`packages/Webkul/User/src/Models/Admin.php`):

```php
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Admin extends Authenticatable implements JWTSubject
{
    // ... existing code ...

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
```

### 4. Generate JWT tokens

**Customer token** (for shop endpoint):

```bash
php artisan tinker
```

```php
$token = auth()->guard('customer-jwt')->attempt([
    'email'    => 'customer@example.com',
    'password' => 'password',
]);
echo $token;
```

**Admin token** (for admin endpoint):

```php
$token = auth()->guard('admin-jwt')->attempt([
    'email'    => 'admin@example.com',
    'password' => 'password',
]);
echo $token;
```

## Endpoints

| Endpoint | URL | Auth | Description |
|----------|-----|------|-------------|
| Shop | `POST /api/mcp/shop` | `customer-jwt` | Customer-facing tools |
| Admin | `POST /api/mcp/admin` | `admin-jwt` | Admin tools (ACL-gated) |
| Stdio (shop) | `php artisan mcp:serve shop` | None | Local CLI transport |
| Stdio (admin) | `php artisan mcp:serve admin` | None | Local CLI transport |

## Available Tools

### Shop Tools (customer-scoped)

| Tool | Description |
|------|-------------|
| `hello_world` | Diagnostic — verify connectivity |
| `search_products` | Search the product catalog |
| `get_cart_details` | Retrieve the current cart |
| `check_order_status` | Check order status and tracking |

### Admin Tools (ACL-gated)

| Tool | ACL Permission | Description |
|------|----------------|-------------|
| `hello_world` | — | Diagnostic — verify connectivity |
| `update_inventory` | `mcp.admin.inventory.update` | Update stock for a product SKU |
| `get_sales_analytics` | `mcp.admin.sales.analytics` | Aggregated sales data for a date range |
| `manage_customers` | `mcp.admin.customers.manage` | Activate, deactivate, or get customer details |

## Connecting AI Clients

### Claude Desktop — Shop Endpoint

Add to your Claude Desktop config (`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS):

```json
{
  "mcpServers": {
    "bagisto-shop": {
      "url": "http://localhost:8000/api/mcp/shop",
      "headers": {
        "Authorization": "Bearer <CUSTOMER_JWT_TOKEN>"
      }
    }
  }
}
```

### Claude Desktop — Admin Endpoint

```json
{
  "mcpServers": {
    "bagisto-admin": {
      "url": "http://localhost:8000/api/mcp/admin",
      "headers": {
        "Authorization": "Bearer <ADMIN_JWT_TOKEN>"
      }
    }
  }
}
```

### Claude Desktop — Stdio Transport

```json
{
  "mcpServers": {
    "bagisto-shop": {
      "command": "php",
      "args": ["artisan", "mcp:serve", "shop"],
      "cwd": "/path/to/your/bagisto-project"
    }
  }
}
```

## Multi-Channel Support

To target a specific channel, include the `X-Channel-Code` header:

```bash
curl -X POST http://localhost:8000/api/mcp/shop \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Channel-Code: uk-store" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"hello_world","arguments":{}}}'
```

If omitted, the default channel is used. Invalid channel codes return a `CHANNEL_NOT_FOUND` error.

## Configuration

Published to `config/mcp.php`. Toggle individual tools on or off:

```php
return [
    'tools' => [
        'shop' => [
            'search_products'     => true,
            'get_cart_details'    => true,
            'check_order_status'  => true,
        ],
        'admin' => [
            'update_inventory'    => true,
            'get_sales_analytics' => true,
            'manage_customers'    => true,
        ],
    ],
    'diagnostic' => [
        'hello_world_enabled' => env('MCP_HELLO_WORLD_ENABLED', env('APP_DEBUG', false)),
    ],
];
```

After changing config, clear the cache:

```bash
php artisan config:clear
```

## Admin ACL Permissions

The package registers three ACL keys in Bagisto's role management. Assign them to admin roles via the Bagisto admin panel under **Settings > Roles**:

| Permission Key | Label |
|----------------|-------|
| `mcp.admin.inventory.update` | MCP - Update Inventory |
| `mcp.admin.sales.analytics` | MCP - Sales Analytics |
| `mcp.admin.customers.manage` | MCP - Manage Customers |

## Troubleshooting

**"Call to undefined method" when generating tokens**
: The `Customer` or `Admin` model does not implement `JWTSubject`. See [step 3](#3-add-jwtsubject-to-your-models) above.

**"webkul/mcp-server requires laravel/mcp"**
: Run `composer require laravel/mcp`.

**Routes not appearing in `route:list`**
: Run `composer dump-autoload` to trigger package auto-discovery.

**Unauthenticated requests return HTML 401 page**
: Ensure `McpServiceProvider` is registered. The package renders a structured JSON error for `/api/mcp/*` paths.

**Tools missing from manifest after config change**
: Run `php artisan config:clear`.

**Admin tool returns "Permission denied"**
: Ensure the admin user's role has the corresponding ACL permission assigned.

## License

MIT
