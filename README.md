<div align="center">
  <img src="https://github.com/user-attachments/assets/54f0c084-65bb-4431-b80d-cceab6c63dc3"/>
</div>
<div align="center">

# Treblle

<a href="https://docs.treblle.com/en/integrations" target="_blank">Integrations</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="http://treblle.com/" target="_blank">Website</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="https://docs.treblle.com" target="_blank">Docs</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="https://blog.treblle.com" target="_blank">Blog</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="https://twitter.com/treblleapi" target="_blank">Twitter</a>
<br />

  <hr />
</div>

API Intelligence Platform. 🚀

Treblle is a lightweight SDK that helps Engineering and Product teams build, ship & maintain REST-based APIs faster.

## Features

<div align="center">
  <br />
  <img src="https://github.com/user-attachments/assets/9b5f40ba-bec9-414b-af88-f1c1cc80781b"/>
  <br />
  <br />
</div>

- [API Monitoring & Observability](https://www.treblle.com/features/api-monitoring-observability)
- [Auto-generated API Docs](https://treblle.com/product/api-documentation)
- [API analytics](https://www.treblle.com/features/api-analytics)
- [Treblle API Score](https://www.treblle.com/features/api-quality-score)
- [API Lifecycle Collaboration](https://www.treblle.com/features/api-lifecycle)
- [Native Treblle Apps](https://www.treblle.com/features/native-apps)


## How Treblle Works
Once you’ve integrated the Treblle SDK in your codebase, this SDK will send requests and response data to your Treblle Dashboard.

In your Treblle Dashboard, you get to see real-time requests to your API, auto-generated API docs, API analytics like how fast the response was for an endpoint, the load size of the response, etc.

Treblle also uses the requests sent to your Dashboard to calculate your API score, which is a quality score that’s calculated based on the performance, quality, and security best practices for your API.

> Visit [https://docs.treblle.com](http://docs.treblle.com) for the complete documentation.

## Security

### Masking fields
Masking fields ensures certain sensitive data is removed before being sent to Treblle.

To make sure masking is done before any data leaves your server [we built it into all our SDKs](https://docs.treblle.com/treblle/data-masking/).

This means data masking is super fast and happens on a programming level before the API request is sent to Treblle. You can customize exactly which fields are masked when you’re integrating the SDK.


## Get Started

Sign in to [Treblle](https://platform.treblle.com) and create a new workspace

### Install the SDK

Install Treblle for Laravel via Composer by running the following command in your terminal:

```sh
composer require treblle/treblle-laravel
```

You can visit our website [https://app.treblle.com](https://app.treblle.com) and create a FREE account to get your API key and SDK Token. Once
you have them, simply add them to your `.ENV` file:

```shell
TREBLLE_API_KEY=YOUR_API_KEY
TREBLLE_SDK_TOKEN=YOUR_SDK_TOKEN
```

## Features & Configuration

Treblle Laravel SDK provides powerful features with flexible configuration options.

### Quick Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Treblle\Laravel\TreblleServiceProvider"
```

This creates a `config/treblle.php` file where you can customize all settings.

### Core Settings

#### 1. API Credentials

Configure your Treblle credentials in `.env`:

```shell
TREBLLE_API_KEY=your_api_key
TREBLLE_SDK_TOKEN=your_sdk_token
```

Or in `config/treblle.php`:

```php
return [
    'api_key' => env('TREBLLE_API_KEY'),
    'sdk_token' => env('TREBLLE_SDK_TOKEN'),
];
```

#### 2. Enable/Disable Monitoring

Easily toggle Treblle monitoring:

```shell
# .env
TREBLLE_ENABLE=true  # or false to disable
```

```php
// config/treblle.php
'enable' => env('TREBLLE_ENABLE', true),
```

**Example use case:** Disable during maintenance or testing.

#### 3. Environment Control

Automatically disable Treblle in specific environments:

```shell
# .env - Comma-separated list
TREBLLE_IGNORED_ENV=local,testing,development
```

```php
// config/treblle.php
'ignored_environments' => env('TREBLLE_IGNORED_ENV', 'dev,test,testing'),
```

**Example:** Treblle will automatically skip monitoring in your local development environment.

### Security Features

#### 4. Sensitive Data Masking

Protect sensitive information before it leaves your server:

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
    'authorization',
    'token',
    // Add your custom sensitive fields
    'custom_secret_field',
],
```

**Example request:**
```json
{
  "email": "user@example.com",
  "password": "secret123",
  "cc": "4111111111111111"
}
```

**What Treblle receives:**
```json
{
  "email": "user@example.com",
  "password": "*********",
  "cc": "*********"
}
```

#### 5. Header Exclusion

Exclude specific headers from logging with powerful pattern matching:

```php
// config/treblle.php
'excluded_headers' => [
    // Exact match (case-insensitive)
    'authorization',
    'x-api-key',
    'cookie',

    // Wildcard patterns
    'x-*',              // All headers starting with 'x-'
    '*-token',          // All headers ending with '-token'
    '*-secret-*',       // All headers containing '-secret-'

    // Regex patterns for advanced matching
    '/^x-(api|auth)-/i',  // Headers starting with 'x-api-' or 'x-auth-'
],
```

**Pattern Support:**
- **Exact match**: `'authorization'` → matches "Authorization", "AUTHORIZATION", etc.
- **Prefix wildcard**: `'x-*'` → matches "X-Custom", "X-API-Key", etc.
- **Suffix wildcard**: `'*-token'` → matches "Auth-Token", "API-Token", etc.
- **Contains wildcard**: `'*-secret-*'` → matches "X-Secret-Key", "My-Secret-Token", etc.
- **Regex pattern**: `'/^x-(api|auth)-/i'` → matches "X-API-Key", "X-Auth-Token", etc.

**Example:**
```php
// Request headers
[
    'X-API-Key' => 'secret123',
    'Authorization' => 'Bearer token',
    'Content-Type' => 'application/json',
    'Custom-Secret-Key' => 'mysecret',
]

// With excluded_headers: ['authorization', 'x-*', '*-secret-*']
// Treblle receives only:
[
    'Content-Type' => 'application/json',
]
```

### Advanced Features

#### 6. Debug Mode

Enable detailed error reporting during development:

```shell
# .env
TREBLLE_DEBUG_MODE=true
```

```php
// config/treblle.php
'debug' => env('TREBLLE_DEBUG_MODE', false),
```

**Warning:** Only enable in development. Never use in production.

#### 7. Custom API URL (Testing/Development)

Override the Treblle API endpoint for testing:

```php
// config/treblle.php
'url' => 'https://your-test-endpoint.com',
```

### Complete Configuration Example

Here's a complete `config/treblle.php` file with all options:

```php
<?php

return [
    // Enable/disable monitoring
    'enable' => env('TREBLLE_ENABLE', true),

    // API credentials
    'api_key' => env('TREBLLE_API_KEY'),
    'sdk_token' => env('TREBLLE_SDK_TOKEN'),

    // Custom API URL (optional, for testing)
    'url' => env('TREBLLE_URL', null),

    // Skip monitoring in these environments
    'ignored_environments' => env('TREBLLE_IGNORED_ENV', 'local,testing'),

    // Sensitive fields to mask
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
        'access_token',
        'refresh_token',
    ],

    // Headers to exclude from logging
    'excluded_headers' => [
        'authorization',
        'cookie',
        'x-*',
        '*-token',
        '*-secret-*',
    ],

    // Debug mode (development only)
    'debug' => env('TREBBLE_DEBUG_MODE', false),
];
```

### Environment Variables Reference

```shell
# Required
TREBLLE_API_KEY=your_api_key_here
TREBLLE_SDK_TOKEN=your_sdk_token_here

# Optional
TREBLLE_ENABLE=true
TREBLLE_IGNORED_ENV=local,testing,development
TREBLLE_DEBUG_MODE=false
TREBLLE_URL=null
```

## Usage

### Basic Setup

#### Step 1: Register Middleware

Add Treblle to your middleware aliases in `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ... other middleware
    'treblle' => \Treblle\Laravel\Middlewares\TreblleMiddleware::class,
];
```

#### Step 2: Apply to Routes

**Option A: Monitor All API Routes**

Add the middleware to a route group in `routes/api.php`:

```php
Route::middleware(['treblle'])->group(function () {
    // All routes in this group will be monitored
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
```

**Option B: Monitor Specific Routes**

Apply middleware to individual routes:

```php
// This route is monitored by Treblle
Route::get('/users/{id}', [UserController::class, 'show'])
    ->middleware('treblle');

// This route is NOT monitored
Route::post('/internal/sync', [InternalController::class, 'sync']);
```

**Option C: Mixed Approach**

```php
Route::prefix('api/v1')->group(function () {
    // Public API - monitored
    Route::middleware(['treblle'])->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
    });

    // Internal API - not monitored
    Route::prefix('internal')->group(function () {
        Route::post('/cache/clear', [CacheController::class, 'clear']);
        Route::post('/queue/retry', [QueueController::class, 'retry']);
    });
});
```

### Advanced Usage

#### Multi-Project Setup

If you have multiple Treblle projects in the same Laravel application, you can set different API keys per route group:

```php
// Project 1: Public API
Route::middleware(['treblle:proj_abc123'])->prefix('api/public')->group(function () {
    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/categories', [PublicCategoryController::class, 'index']);
});

// Project 2: Partner API
Route::middleware(['treblle:proj_xyz789'])->prefix('api/partner')->group(function () {
    Route::get('/orders', [PartnerOrderController::class, 'index']);
    Route::post('/webhooks', [PartnerWebhookController::class, 'handle']);
});

// Project 3: Admin API
Route::middleware(['treblle:proj_def456'])->prefix('api/admin')->group(function () {
    Route::get('/analytics', [AdminAnalyticsController::class, 'index']);
    Route::post('/settings', [AdminSettingsController::class, 'update']);
});
```

**Note:** The dynamic API key parameter takes precedence over the `.env` configuration.

#### Temporarily Disable Monitoring

Disable Treblle monitoring without removing the middleware:

```shell
# .env
TREBLLE_ENABLE=false
```

This is useful for:
- Maintenance windows
- Load testing
- Debugging
- Temporary troubleshooting

#### Laravel 11+ Bootstrap Setup

For Laravel 11+, register middleware in `bootstrap/app.php`:

```php
<?php

use Illuminate\Foundation\Application;
use Treblle\Laravel\Middlewares\TreblleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'treblle' => TreblleMiddleware::class,
        ]);
    })
    ->create();
```

### Verification

After setup, verify Treblle is working:

1. **Check configuration:**
   ```bash
   php artisan about
   ```
   Look for the "Treblle" section in the output.

2. **Make a test request:**
   ```bash
   curl http://your-app.test/api/users
   ```

3. **View in Dashboard:**
   Visit your [Treblle Dashboard](https://platform.treblle.com) to see the request in real-time.

> See the [official documentation](https://docs.treblle.com/en/integrations/laravel) for more details.

## Special Features

### Capturing Original Request Payloads

**Problem:** Some applications use middleware to transform incoming request data before processing. By default, Treblle captures the request data *after* all middleware has processed it.

**Solution:** Use the `treblle.early` middleware to capture the original payload before transformations.

#### When to Use This

- ✅ Your API has middleware that modifies incoming request data
- ✅ You want to see what clients actually sent vs. what your application processed
- ✅ You're debugging issues related to request transformations
- ✅ You need complete visibility into your API's request lifecycle

#### How It Works

The `treblle.early` middleware captures the raw request payload at the beginning of the middleware chain, while the regular `treblle` middleware still runs at the end to capture the final response.

#### Usage Example

**Scenario:** Legacy API v1 to v2 transformation

```php
// You have a middleware that transforms old API format to new format
class TransformLegacyRequestMiddleware
{
    public function handle($request, $next)
    {
        // Transform old format to new format
        $transformed = [
            'email' => $request->input('user_email'),
            'name' => $request->input('full_name'),
            'phone' => $request->input('phone_number'),
        ];

        $request->merge($transformed);
        return $next($request);
    }
}
```

**Without `treblle.early`:**
```php
Route::middleware(['transform-legacy', 'treblle'])->group(function () {
    Route::post('/api/users', [UserController::class, 'store']);
});

// Treblle only sees the transformed data:
// { "email": "...", "name": "...", "phone": "..." }
// You don't see what the client actually sent!
```

**With `treblle.early`:**
```php
Route::middleware(['treblle.early', 'transform-legacy', 'treblle'])->group(function () {
    Route::post('/api/users', [UserController::class, 'store']);
});

// Treblle captures BOTH:
// 1. Original: { "user_email": "...", "full_name": "...", "phone_number": "..." }
// 2. Transformed: { "email": "...", "name": "...", "phone": "..." }
```

#### Real-World Examples

**Example 1: API Versioning**

```php
// Support both v1 and v2 formats
Route::prefix('api/v1')->middleware(['treblle.early', 'transform-v1-to-v2', 'treblle'])->group(function () {
    Route::post('/orders', [OrderController::class, 'create']);
});
```

**Example 2: Multi-Format Support**

```php
// Accept JSON, XML, and Form Data
Route::middleware(['treblle.early', 'normalize-request-format', 'treblle'])->group(function () {
    Route::post('/webhooks/{provider}', [WebhookController::class, 'handle']);
});
```

**Example 3: Legacy System Integration**

```php
// Transform SOAP-like payloads to REST
Route::post('/api/legacy/soap', [LegacyController::class, 'handle'])
    ->middleware(['treblle.early', 'soap-to-rest-transformer', 'treblle']);
```

#### Middleware Order Matters

**Correct Order:**
```php
// ✅ GOOD - Captures original before transformation
['treblle.early', 'transform', 'auth', 'treblle']
```

**Incorrect Order:**
```php
// ❌ BAD - treblle.early after transformation defeats the purpose
['transform', 'treblle.early', 'auth', 'treblle']
```

#### Tips

- The `treblle.early` middleware is lightweight and has minimal performance impact
- You can use it on all routes or just specific ones that need it
- It's completely optional - if you don't need it, just use the regular `treblle` middleware
- The feature is backward compatible - existing setups work without changes

## Upgrading from v5.x to v6.0

Version 6.0 brings compatibility with treblle-php v5 and includes breaking changes to configuration keys.

### What Changed?

The configuration keys have been renamed to align with treblle-php v5:

| Old Key (v5.x) | New Key (v6.0) | Description |
|----------------|----------------|-------------|
| `TREBLLE_API_KEY` | `TREBLLE_SDK_TOKEN` | Your SDK authentication token |
| `TREBLLE_PROJECT_ID` | `TREBLLE_API_KEY` | Your project/API identifier |

### Step-by-Step Migration

#### 1. Update Composer Dependencies

```bash
composer require treblle/treblle-laravel:^6.0
```

Or update your `composer.json`:

```json
{
    "require": {
        "treblle/treblle-laravel": "^6.0"
    }
}
```

Then run:

```bash
composer update treblle/treblle-laravel
```

#### 2. Update Environment Variables

Update your `.env` file by swapping the values:

```shell
# Before (v5.x)
TREBLLE_API_KEY=abc123xyz
TREBLLE_PROJECT_ID=proj_456def

# After (v6.0)
TREBLLE_SDK_TOKEN=abc123xyz
TREBLLE_API_KEY=proj_456def
```

**Important**: The *values* are swapped - what was your API key is now your SDK token, and what was your project ID is now your API key.

#### 3. Update Dynamic Middleware Parameters (If Applicable)

If you're using dynamic project/API key parameters in your routes:

```php
// Before (v5.x)
Route::middleware(['treblle:project-id-1'])->group(function () {
    // routes
});

// After (v6.0)
Route::middleware(['treblle:api-key-1'])->group(function () {
    // routes
});
```

#### 4. Republish Configuration (Optional but Recommended)

To get the latest config file with updated comments:

```bash
php artisan vendor:publish --provider="Treblle\Laravel\TreblleServiceProvider" --force
```

Then re-apply any custom configurations you had.

#### 5. Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
```

#### 6. Verify Everything is Working

Check your configuration:
```bash
php artisan about
```

Look for the Treblle section to confirm your keys are loaded correctly.

Make a test API request and verify it appears in your Treblle dashboard.

### Code Changes (If You Extended the SDK)

If you've extended or customized the Treblle SDK in your application:

#### Exception Handling

- **Removed**: `TreblleException::missingProjectId()`
- **Changed**: `TreblleException::missingApiKey()` (now validates API key instead of project ID)
- **Added**: `TreblleException::missingSdkToken()` for SDK token validation

#### Config Access

Update any code that reads configuration:

```php
// Old (v5.x)
$projectId = config('treblle.project_id');
$apiKey = config('treblle.api_key');

// New (v6.0)
$apiKey = config('treblle.api_key');
$sdkToken = config('treblle.sdk_token');
```

#### Helper Classes

If you were importing helper classes:

```php
// Old (v5.x)
use Treblle\Laravel\Helpers\HeaderProcessor;

// New (v6.0)
use Treblle\Php\Helpers\HeaderFilter;
use Treblle\Php\Helpers\SensitiveDataMasker;
```

The Laravel SDK no longer has custom helper classes - it uses the core SDK helpers directly.

### Laravel Compatibility

Version 6.0 adds support for Laravel 11 and 12 while maintaining backward compatibility:

| Laravel Version | Status |
|-----------------|--------|
| Laravel 10.x | ✅ Supported |
| Laravel 11.x | ✅ Supported |
| Laravel 12.x | ✅ Supported |
| PHP 8.2+ | ✅ Required |

### Troubleshooting

#### Issue: Missing configuration keys

**Error**: `TreblleException: No SDK Token configured for Treblle`

**Solution**: Ensure you've updated your `.env` file with the new key names:
```shell
TREBLLE_SDK_TOKEN=your_token
TREBLLE_API_KEY=your_key
```

#### Issue: Old middleware parameters not working

**Error**: Routes with dynamic project IDs not being monitored

**Solution**: Update middleware parameters from `project-id` to `api-key`:
```php
Route::middleware(['treblle:your-api-key'])->group(function () {
    // routes
});
```

#### Issue: Config cached with old values

**Error**: Still seeing old configuration after update

**Solution**: Clear all caches:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Rollback (If Needed)

If you need to rollback to v5.x:

```bash
composer require treblle/treblle-laravel:^5.0
```

Then restore your original `.env` configuration:

```shell
TREBLLE_API_KEY=your_original_api_key
TREBLLE_PROJECT_ID=your_original_project_id
```

### Need Help?

If you encounter any issues during the upgrade:

1. Check the [documentation](https://docs.treblle.com/en/integrations/laravel)
2. Review the [CHANGELOG.md](CHANGELOG.md) for all changes
3. Open an issue on [GitHub](https://github.com/Treblle/treblle-laravel/issues)
4. Join our [Discord community](https://treblle.com/chat)

## Available SDKs

Treblle provides [open-source SDKs](https://docs.treblle.com/en/integrations) that let you seamlessly integrate Treblle with your REST-based APIs.

- [`treblle-laravel`](https://github.com/Treblle/treblle-laravel): SDK for Laravel
- [`treblle-php`](https://github.com/Treblle/treblle-php): SDK for PHP
- [`treblle-symfony`](https://github.com/Treblle/treblle-symfony): SDK for Symfony
- [`treblle-lumen`](https://github.com/Treblle/treblle-lumen): SDK for Lumen
- [`treblle-sails`](https://github.com/Treblle/treblle-sails): SDK for Sails
- [`treblle-node`](https://github.com/Treblle/treblle-node): SDK for Express, NestJS, Koa, Hono, Cloudflare Workers and Strapi
- [`treblle-nextjs`](https://github.com/Treblle/treblle-nextjs): SDK for Next.js
- [`treblle-fastify`](https://github.com/Treblle/treblle-fastify): SDK for Fastify
- [`treblle-directus`](https://github.com/Treblle/treblle-directus): SDK for Directus
- [`treblle-go`](https://github.com/Treblle/treblle-go): SDK for Go
- [`treblle-ruby`](https://github.com/Treblle/treblle-ruby): SDK for Ruby on Rails
- [`treblle-python`](https://github.com/Treblle/treblle-python): SDK for Python/Django

> See the [docs](https://docs.treblle.com/en/integrations) for more on SDKs and Integrations.
 
## Community 💙

First and foremost: **Star and watch this repository** to stay up-to-date.

Also, follow our [Blog](https://blog.treblle.com), and on [Twitter](https://twitter.com/treblleapi).

Check out our tutorials and other video material at [YouTube](https://youtube.com/@treblle).

[![Treblle YouTube](https://img.shields.io/badge/Treblle%20YouTube-Subscribe%20on%20YouTube-F3F5FC?labelColor=c4302b&style=for-the-badge&logo=YouTube&logoColor=F3F5FC&link=https://youtube.com/@treblle)](https://youtube.com/@treblle)

[![Treblle on Twitter](https://img.shields.io/badge/Treblle%20on%20Twitter-Follow%20Us-F3F5FC?labelColor=1DA1F2&style=for-the-badge&logo=Twitter&logoColor=F3F5FC&link=https://twitter.com/treblleapi)](https://twitter.com/treblleapi)

### How to contribute

Here are some ways of contributing to making Treblle better:

- **[Try out Treblle](https://docs.treblle.com/en/introduction#getting-started)**, and let us know ways to make Treblle better for you. 
- Send a pull request to any of our [open source repositories](https://github.com/Treblle) on Github. Check the contribution guide on the repo you want to contribute to for more details about how to contribute. We're looking forward to your contribution!

### Contributors
<a href="https://github.com/Treblle/treblle-laravel/graphs/contributors">
  <p align="center">
    <img  src="https://contrib.rocks/image?repo=Treblle/treblle-laravel" alt="A table of avatars from the project's contributors" />
  </p>
</a>
