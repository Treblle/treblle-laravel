# Treblle - Runtime Intelligence Platform

[Website](http://treblle.com/) • [Documentation](https://docs.treblle.com/) • [Pricing](https://treblle.com/pricing)

Discover, Govern, and Secure APIs, Agents, and AI Across Any Cloud, Gateway or Technology.

---

## Treblle Laravel SDK

[![Latest Version](https://img.shields.io/packagist/v/treblle/treblle-laravel
)](https://packagist.org/packages/treblle/treblle-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/treblle/treblle-laravel)](https://packagist.org/packages/treblle/treblle-laravel)


## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Registering Middleware](#registering-middleware)
  - [Laravel 11 and 12](#laravel-11-and-12)
  - [Laravel 10 and below](#laravel-10-and-below)
- [Applying Middleware to Routes](#applying-middleware-to-routes)
- [Excluding Routes from Monitoring](#excluding-routes-from-monitoring)
- [Configuration Reference](#configuration-reference)
- [Environment Variables](#environment-variables)
- [Advanced Features](#advanced-features)
  - [Multi-Project Setup](#multi-project-setup)
  - [Early Payload Capture](#early-payload-capture)
  - [Custom Metadata](#custom-metadata)
  - [Queue-Based Transmission](#queue-based-transmission)
  - [Sensitive Data Masking](#sensitive-data-masking)
  - [Header Exclusion](#header-exclusion)
  - [Debug Mode](#debug-mode)
- [Verifying Your Setup](#verifying-your-setup)
- [Upgrading from v5.x](#upgrading-from-v5x-to-v60)
- [Available SDKs](#available-sdks)
- [Community](#community)

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.2 |
| Laravel | 10.x, 11.x, 12.x, 13.x |

---

## Installation

**Step 1 — Install via Composer:**

```bash
composer require treblle/treblle-laravel
```

**Step 2 — Add your credentials to `.env`:**

```env
TREBLLE_API_KEY=your_api_key
TREBLLE_SDK_TOKEN=your_sdk_token
```

Get your API Key and SDK Token for free at [platform.treblle.com](https://platform.treblle.com).

**Step 3 — (Optional) Publish the config file:**

```bash
php artisan vendor:publish --provider="Treblle\Laravel\TreblleServiceProvider"
```

This creates `config/treblle.php` where you can customize all settings.

---

## Quick Start

Apply the `treblle` middleware to the routes you want to monitor:

```php
// routes/api.php
Route::middleware(['treblle'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
});
```

That's it. Requests to those routes will appear in your [Treblle Dashboard](https://platform.treblle.com) in real time.

---

## Registering Middleware

The `treblle` and `treblle.early` middleware aliases are registered automatically by the service provider. You do not need to add anything to your kernel or bootstrap file to use them.

If for some reason you need to register them manually, here's how:

### Laravel 11 and 12

In `bootstrap/app.php`:

```php
use Treblle\Laravel\Middlewares\TreblleMiddleware;
use Treblle\Laravel\Middlewares\TreblleEarlyMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'treblle'       => TreblleMiddleware::class,
            'treblle.early' => TreblleEarlyMiddleware::class,
        ]);
    })
    ->create();
```

### Laravel 10 and below

In `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ... other middleware
    'treblle'       => \Treblle\Laravel\Middlewares\TreblleMiddleware::class,
    'treblle.early' => \Treblle\Laravel\Middlewares\TreblleEarlyMiddleware::class,
];
```

---

## Applying Middleware to Routes

### Monitor all routes in a group

```php
Route::middleware(['treblle'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
```

### Monitor a single route

```php
Route::get('/users/{id}', [UserController::class, 'show'])
    ->middleware('treblle');
```

### Monitor only specific routes within a group

```php
Route::prefix('api/v1')->group(function () {

    // These routes are monitored
    Route::middleware(['treblle'])->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/orders', [OrderController::class, 'index']);
    });

    // These routes are not monitored
    Route::post('/internal/cache/clear', [CacheController::class, 'clear']);
    Route::post('/internal/queue/retry', [QueueController::class, 'retry']);

});
```

---

## Excluding Routes from Monitoring

There are two ways to exclude specific routes from Treblle monitoring when the middleware is applied at a group level.

### Option 1 — `withoutMiddleware()` on a route

Use Laravel's built-in `withoutMiddleware()` method directly on the routes you want to exclude:

```php
Route::middleware(['treblle'])->group(function () {

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);

    // This route is excluded from Treblle monitoring
    Route::get('/users/export', [UserController::class, 'export'])
        ->withoutMiddleware('treblle');

    // This route is also excluded
    Route::post('/users/bulk-import', [UserController::class, 'bulkImport'])
        ->withoutMiddleware(\Treblle\Laravel\Middlewares\TreblleMiddleware::class);

});
```

Both the alias (`'treblle'`) and the full class name work.

### Option 2 — Exclude an entire nested group

```php
Route::middleware(['treblle'])->prefix('api')->group(function () {

    // Monitored routes
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);

    // This entire group is excluded
    Route::withoutMiddleware('treblle')->prefix('internal')->group(function () {
        Route::post('/cache/clear', [CacheController::class, 'clear']);
        Route::get('/health', [HealthController::class, 'check']);
        Route::post('/queue/retry', [QueueController::class, 'retry']);
    });

});
```

### Option 3 — Never apply the middleware in the first place

The simplest approach: only apply `treblle` where you want it, not to everything.

```php
// routes/api.php

// Monitored
Route::middleware(['auth:sanctum', 'treblle'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('orders', OrderController::class);
});

// Not monitored — no treblle middleware
Route::middleware(['auth:sanctum'])->prefix('internal')->group(function () {
    Route::get('/health', [HealthController::class, 'check']);
    Route::post('/cache/flush', [CacheController::class, 'flush']);
});
```

---

## Configuration Reference

After publishing the config file, `config/treblle.php` contains all available options:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable Monitoring
    |--------------------------------------------------------------------------
    | Set to false to completely disable Treblle. Useful for maintenance windows,
    | load testing, or when you want to turn it off without removing middleware.
    |
    | Env: TREBLLE_ENABLE
    | Default: true
    */
    'enable' => env('TREBLLE_ENABLE', true),

    /*
    |--------------------------------------------------------------------------
    | Treblle Ingress URL
    |--------------------------------------------------------------------------
    | The endpoint Treblle data is sent to. Only change this if you are running
    | a self-hosted Treblle instance or pointing at a test endpoint.
    |
    | Env: TREBLLE_API_URL
    | Default: https://ingress.treblle.com
    */
    'url' => env('TREBLLE_API_URL', 'https://ingress.treblle.com'),

    /*
    |--------------------------------------------------------------------------
    | SDK Token
    |--------------------------------------------------------------------------
    | Your Treblle SDK Token. Found in your Treblle account settings.
    | Previously called TREBLLE_API_KEY in v5.x.
    |
    | Env: TREBLLE_SDK_TOKEN
    | Required: yes
    */
    'sdk_token' => env('TREBLLE_SDK_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    | Your Treblle project API Key. Identifies which project this data belongs to.
    | Previously called TREBLLE_PROJECT_ID in v5.x.
    |
    | Env: TREBLLE_API_KEY
    | Required: yes
    */
    'api_key' => env('TREBLLE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Ignored Environments
    |--------------------------------------------------------------------------
    | Treblle will not send any data when your app is running in one of these
    | environments. Comma-separated list. Compared against app()->environment().
    |
    | Env: TREBLLE_IGNORED_ENV
    | Default: dev,test,testing
    */
    'ignored_environments' => env('TREBLLE_IGNORED_ENV', 'dev,test,testing'),

    /*
    |--------------------------------------------------------------------------
    | Masked Fields
    |--------------------------------------------------------------------------
    | Field names listed here will have their values replaced with asterisks
    | before the data leaves your server. Matching is case-insensitive and
    | applies to both request body and response body.
    |
    | The fields below are masked by default. Add your own sensitive fields.
    */
    'masked_fields' => [
        'password',
        'pwd',
        'secret',
        'password_confirmation',
        'cc',
        'card_number',
        'ccv',
        'ssn',
        'credit_score',
        'api_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Headers
    |--------------------------------------------------------------------------
    | Headers listed here are completely removed before the data is sent to
    | Treblle. Supports exact match, wildcard patterns, and regex.
    |
    | Examples:
    |   'authorization'       — exact match (case-insensitive)
    |   'x-*'                 — all headers starting with x-
    |   '*-token'             — all headers ending with -token
    |   '/^x-(api|auth)-/i'  — regex pattern
    */
    'excluded_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Custom Metadata
    |--------------------------------------------------------------------------
    | Static key/value pairs included in the metadata object of every request
    | payload. Useful for tagging requests with environment, region, version, etc.
    |
    | Per-request metadata can be added dynamically from a controller or
    | middleware — see the Custom Metadata section in the README.
    | Per-request values are merged over these static values.
    */
    'metadata' => [],

    /*
    |--------------------------------------------------------------------------
    | Ignored HTTP Methods
    |--------------------------------------------------------------------------
    | Requests using these methods are never monitored. HEAD and OPTIONS are
    | excluded by default — they are high-volume noise with no request body.
    | Override entirely if you need a different set.
    */
    'ignored_methods' => ['HEAD', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    | When enabled, Treblle will log warnings and errors to your Laravel log.
    | Only enable this during development to diagnose integration issues.
    | Never enable in production.
    |
    | Env: TREBLLE_DEBUG_MODE
    | Default: false
    */
    'debug' => env('TREBLLE_DEBUG_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    | Enable asynchronous data transmission via Laravel queues. Recommended
    | for production — completely removes any network latency from the
    | request/response cycle.
    */
    'queue' => [

        /*
         | Enable queue-based transmission.
         | Env: TREBLLE_QUEUE_ENABLED
         | Default: false
        */
        'enabled' => env('TREBLLE_QUEUE_ENABLED', false),

        /*
         | Queue connection to use. Must be configured in config/queue.php.
         | Recommended: redis, sqs, beanstalkd
         | Env: TREBLLE_QUEUE_CONNECTION
        */
        'connection' => env('TREBLLE_QUEUE_CONNECTION', 'redis'),

        /*
         | Queue name to dispatch jobs to.
         | Env: TREBLLE_QUEUE_NAME
         | Default: default
        */
        'queue' => env('TREBLLE_QUEUE_NAME', 'default'),

    ],

];
```

---

## Environment Variables

```env
# Required
TREBLLE_API_KEY=your_api_key
TREBLLE_SDK_TOKEN=your_sdk_token

# Optional — Core
TREBLLE_ENABLE=true
TREBLLE_IGNORED_ENV=dev,test,testing
TREBLLE_DEBUG_MODE=false
TREBLLE_API_URL=https://ingress.treblle.com

# Optional — Queue (recommended for production)
TREBLLE_QUEUE_ENABLED=false
TREBLLE_QUEUE_CONNECTION=redis
TREBLLE_QUEUE_NAME=default
```

---

## Advanced Features

### Multi-Project Setup

If you have multiple APIs in the same Laravel application and want to track them as separate projects in Treblle, pass the API key directly as a middleware parameter. This overrides the global `TREBLLE_API_KEY` for those routes.

```php
// Public API — Project A
Route::middleware(['treblle:api_key_project_a'])->prefix('api/public')->group(function () {
    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/categories', [PublicCategoryController::class, 'index']);
});

// Partner API — Project B
Route::middleware(['treblle:api_key_project_b'])->prefix('api/partner')->group(function () {
    Route::get('/orders', [PartnerOrderController::class, 'index']);
    Route::post('/webhooks', [PartnerWebhookController::class, 'handle']);
});

// Admin API — Project C
Route::middleware(['treblle:api_key_project_c'])->prefix('api/admin')->group(function () {
    Route::get('/analytics', [AdminAnalyticsController::class, 'index']);
});
```

The per-route API key always takes precedence over `TREBLLE_API_KEY` in `.env`.

---

### Early Payload Capture

By default, Treblle captures request data after all middleware has run. If you have middleware that transforms the request body (e.g. normalising a legacy format, converting XML to JSON), you may want to capture what the client *actually* sent.

The `treblle.early` middleware solves this. Place it at the **start** of your middleware chain to snapshot the raw payload before anything else touches it.

**Middleware order matters:**

```php
// Correct — treblle.early runs first, before any transformations
Route::middleware(['treblle.early', 'transform-legacy-format', 'treblle'])->group(function () {
    Route::post('/api/v1/orders', [OrderController::class, 'store']);
});

// Wrong — treblle.early after transformation is pointless
Route::middleware(['transform-legacy-format', 'treblle.early', 'treblle'])->group(function () {
    Route::post('/api/v1/orders', [OrderController::class, 'store']);
});
```

**Example — API versioning:**

```php
// Clients send v1 format, your app works with v2 format internally
Route::middleware(['treblle.early', 'normalize-to-v2', 'treblle'])
    ->prefix('api/v1')
    ->group(function () {
        Route::post('/users', [UserController::class, 'store']);
    });
```

With `treblle.early`, Treblle captures the original v1 payload the client sent. Without it, you'd only see the normalised v2 data.

---

### Custom Metadata

Every Treblle payload includes a `metadata` object. By default it is empty. You can populate it with any key/value data you want to attach to requests — tenant IDs, feature flags, deployment versions, trace IDs, etc.

There are two ways to set metadata:

#### 1. Static metadata (applies to every request)

Set it once in `config/treblle.php`:

```php
'metadata' => [
    'environment' => env('APP_ENV'),
    'region'      => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'version'     => '2.4.1',
],
```

#### 2. Per-request metadata via the `Treblle` facade

The `Treblle` facade is the cleanest way to attach metadata at runtime. Call it from anywhere during the request lifecycle — controllers, service classes, middleware, event listeners.

```php
use Treblle\Laravel\Facades\Treblle;

// Single key/value
Treblle::meta('tenant_id', auth()->user()->tenant_id);

// Multiple key/values at once
Treblle::meta([
    'tenant_id' => auth()->user()->tenant_id,
    'plan'      => auth()->user()->plan,
    'trace_id'  => $request->header('X-Trace-Id'),
]);
```

Calls to `Treblle::meta()` always **merge** — calling it multiple times is safe and additive.

**In a controller:**

```php
use Treblle\Laravel\Facades\Treblle;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Treblle::meta([
            'tenant_id'    => auth()->user()->tenant_id,
            'plan'         => auth()->user()->plan,
            'order_source' => 'api',
        ]);

        // ... create the order
    }
}
```

**In a middleware (applies to all routes the middleware is on):**

```php
use Treblle\Laravel\Facades\Treblle;

class AttachTreblleContext
{
    public function handle(Request $request, Closure $next)
    {
        Treblle::meta([
            'trace_id'  => $request->header('X-Trace-Id', (string) Str::uuid()),
            'tenant_id' => $request->header('X-Tenant-Id'),
        ]);

        return $next($request);
    }
}
```

**In a service class (no Request injection needed):**

```php
use Treblle\Laravel\Facades\Treblle;

class PaymentService
{
    public function charge(array $data): void
    {
        Treblle::meta('payment_gateway', 'stripe');

        // ... process payment
    }
}
```

**Merging behaviour:** Per-request metadata is merged over static config metadata. If both define the same key, the runtime value wins.

**What ends up in the payload:**

```json
{
  "request": { ... },
  "response": { ... },
  "server": { ... },
  "language": { ... },
  "errors": [],
  "metadata": {
    "environment": "production",
    "region": "us-east-1",
    "tenant_id": "tenant_abc123",
    "plan": "enterprise"
  }
}
```

---

### Queue-Based Transmission

By default, Treblle sends data synchronously using Laravel's terminable middleware pattern — after the response is sent to the client, before the PHP process ends. This is non-blocking and has no impact on response time for most applications.

For high-throughput APIs or when you want to fully decouple transmission from the web process, enable queue mode:

```env
TREBLLE_QUEUE_ENABLED=true
TREBLLE_QUEUE_CONNECTION=redis
TREBLLE_QUEUE_NAME=treblle
```

```php
// config/treblle.php
'queue' => [
    'enabled'    => env('TREBLLE_QUEUE_ENABLED', false),
    'connection' => env('TREBLLE_QUEUE_CONNECTION', 'redis'),
    'queue'      => env('TREBLLE_QUEUE_NAME', 'default'),
],
```

Make sure your queue worker is running:

```bash
php artisan queue:work redis --queue=treblle
```

**Supported connections:**

| Connection | Recommended |
|------------|-------------|
| `redis` | Yes — fast, reliable |
| `sqs` | Yes — AWS deployments |
| `beanstalkd` | Yes |
| `database` | Only with proper indexes |
| `sync` | No — defeats the purpose |
| `null` | No |

---

### Sensitive Data Masking

Fields listed in `masked_fields` have their values replaced with `*****` before any data leaves your server. This happens at the PHP level — the values never reach Treblle.

```php
// config/treblle.php
'masked_fields' => [
    'password',
    'pwd',
    'secret',
    'password_confirmation',
    'cc',
    'card_number',
    'ccv',
    'ssn',
    'credit_score',
    'api_key',
    // Add your own
    'access_token',
    'refresh_token',
    'private_key',
    'stripe_secret',
],
```

Masking is case-insensitive and applies recursively to nested objects and arrays in both request and response bodies.

**Example:**

```json
// What the client sends
{ "email": "user@example.com", "password": "hunter2", "card_number": "4111111111111111" }

// What Treblle receives
{ "email": "user@example.com", "password": "*****", "card_number": "*****" }
```

---

### Header Exclusion

Headers listed in `excluded_headers` are completely removed from the data sent to Treblle. Unlike field masking, excluded headers do not appear at all — not even as `*****`.

```php
// config/treblle.php
'excluded_headers' => [
    // Exact match (case-insensitive)
    'authorization',
    'cookie',
    'x-api-key',

    // Wildcard — all headers starting with x-internal-
    'x-internal-*',

    // Wildcard — all headers ending with -token
    '*-token',

    // Wildcard — headers containing -secret-
    '*-secret-*',

    // Regex — headers matching x-api- or x-auth-
    '/^x-(api|auth)-/i',
],
```

**Pattern types:**

| Pattern | Example match |
|---------|---------------|
| `'authorization'` | `Authorization`, `AUTHORIZATION` |
| `'x-*'` | `X-Request-Id`, `X-Custom-Header` |
| `'*-token'` | `Auth-Token`, `Refresh-Token` |
| `'*-secret-*'` | `X-Secret-Key`, `My-Secret-Value` |
| `'/^x-(api|auth)-/i'` | `X-API-Key`, `X-Auth-Token` |

---

### Debug Mode

Enable debug mode to log Treblle warnings and errors to your Laravel log file:

```env
TREBLLE_DEBUG_MODE=true
```

With debug enabled, you'll see log entries when:
- Configuration is missing (`TREBLLE_SDK_TOKEN`, `TREBLLE_API_KEY`)
- Data transmission fails
- The response exceeds the 2MB limit

**Only use this during development.** Disable it in production.

```bash
# After enabling debug mode, watch your logs
tail -f storage/logs/laravel.log
```

---

## Verifying Your Setup

**1. Run the built-in test command:**

```bash
php artisan treblle:test
```

This checks your credentials, validates your environment, sends a real test payload to Treblle's ingress, and tells you exactly what's wrong if anything fails:

```
Treblle — Configuration Check
─────────────────────────────────────────────

  SDK Token    ✓ Set (****a1b2)
  API Key      ✓ Set (****c3d4)
  Monitoring   ✓ Enabled
  Environment  ✓ production (not ignored)

  Sending test payload to https://ingress.treblle.com ...

  ✓ Payload accepted — HTTP 200 in 94ms

  Treblle is configured correctly.
```

**2. Check the `about` command:**

```bash
php artisan about
```

Look for the `Treblle` section — shows your SDK version, URL, and masked credentials.

**3. Make a real request and check your dashboard:**

```bash
curl -s http://your-app.test/api/users | jq
```

Log in to [platform.treblle.com](https://platform.treblle.com) and confirm the request appears in real time.

**4. Common issues:**

| Symptom | Cause | Fix |
|---------|-------|-----|
| No requests in dashboard | Wrong environment ignored | Check `TREBLLE_IGNORED_ENV` — `local` and `testing` are ignored by default |
| No requests in dashboard | Missing credentials | Run `php artisan about` and verify keys are set |
| No requests in dashboard | Monitoring disabled | Check `TREBLLE_ENABLE=true` |
| Config changes not applying | Config cache | Run `php artisan config:clear` |

