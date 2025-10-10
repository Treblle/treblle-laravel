# Treblle Laravel SDK

## Project Overview

This is the official Treblle Laravel SDK - a Laravel-specific integration that wraps the treblle-php SDK to provide seamless API monitoring, analytics, and auto-generated documentation for Laravel applications.

**Version**: 6.0
**Laravel Support**: 10.x, 11.x, 12.x
**PHP Requirements**: ^8.2
**License**: MIT

## Architecture

### Core Components

1. **TreblleServiceProvider** (`src/TreblleServiceProvider.php`):
   - Registers middleware aliases (`treblle`, `treblle.early`)
   - Publishes configuration file
   - Integrates with Laravel Octane for request timing
   - Provides Laravel `about` command integration
   - Sets middleware priority for early capture

2. **TreblleMiddleware** (`src/Middlewares/TreblleMiddleware.php`):
   - Main middleware for API monitoring
   - Validates configuration (API key, SDK token)
   - Respects environment-based toggling
   - Supports dynamic API key per route
   - Uses terminable middleware pattern for async transmission
   - Integrates with treblle-php v5 core SDK

3. **TreblleEarlyMiddleware** (`src/Middlewares/TreblleEarlyMiddleware.php`):
   - Captures original request payload before transformations
   - Stores raw payload in request attributes
   - Lightweight, minimal overhead
   - Optional feature for debugging transformed requests

4. **Laravel-Specific Data Providers**:
   - **LaravelRequestDataProvider** (`src/DataProviders/LaravelRequestDataProvider.php`)
     - Uses Laravel's Request facade
     - Extracts route paths from Laravel router
     - Handles both Illuminate and Symfony Request types
     - Supports early captured payloads
     - Integrates HeaderFilter for excluded headers

   - **LaravelResponseDataProvider** (`src/DataProviders/LaravelResponseDataProvider.php`)
     - Calculates load time from request start
     - Handles Laravel Octane timing via request attributes
     - Falls back to LARAVEL_START constant
     - Validates response size (2MB limit)
     - Integrates HeaderFilter for excluded headers

5. **Exception Handling** (`src/Exceptions/TreblleException.php`):
   - `missingSdkToken()` - Missing TREBLLE_SDK_TOKEN
   - `missingApiKey()` - Missing TREBLLE_API_KEY
   - Clear, actionable error messages for configuration issues

### Integration with treblle-php v5

The Laravel SDK delegates to treblle-php v5 core library:
- Uses `TreblleFactory::create()` for initialization
- Provides Laravel-specific data providers
- Leverages core SDK's `SensitiveDataMasker` (`Treblle\Php\Helpers\SensitiveDataMasker`)
- Leverages core SDK's `HeaderFilter` (`Treblle\Php\Helpers\HeaderFilter`)
- Utilizes `InMemoryErrorDataProvider` for error tracking
- Benefits from core SDK's background processing
- No custom helper classes needed - all utilities from core SDK

### Key Features

- **Middleware-based integration**: Easy route-level control
- **Environment awareness**: Auto-disable in dev/test environments
- **Dynamic multi-project support**: Different API keys per route group
- **Early payload capture**: See original vs transformed data
- **Laravel Octane compatible**: Accurate request timing
- **Terminable middleware**: Non-blocking data transmission
- **Configuration publishing**: Standard Laravel workflow
- **Artisan `about` integration**: View status in `php artisan about`

### Data Flow

```
1. Request hits Laravel application
2. TreblleEarlyMiddleware (optional):
   - Captures original request payload
   - Stores in request attributes
3. Application middleware executes (auth, transforms, etc.)
4. TreblleMiddleware::handle():
   - Validates configuration
   - Checks environment exclusions
   - Allows request to proceed
5. Controller executes, generates response
6. TreblleMiddleware::terminate():
   - Creates Laravel-specific data providers
   - Initializes treblle-php SDK
   - Builds complete payload
   - Sends to Treblle (non-blocking)
```

### Configuration

#### Config File Structure (`config/treblle.php`)

```php
return [
    'enable' => env('TREBLLE_ENABLE', true),
    'url' => null,  // Override Treblle endpoint
    'sdk_token' => env('TREBLLE_SDK_TOKEN'),
    'api_key' => env('TREBLLE_API_KEY'),
    'ignored_environments' => env('TREBLLE_IGNORED_ENV', 'dev,test,testing'),
    'masked_fields' => [...],  // Sensitive fields to mask
    'excluded_headers' => [], // Headers to exclude (supports wildcards/regex)
    'debug' => env('TREBLLE_DEBUG_MODE', false),
];
```

#### Environment Variables

**Required**:
- `TREBLLE_API_KEY` - Your Treblle project API key (was PROJECT_ID in v5.x)
- `TREBLLE_SDK_TOKEN` - Your Treblle SDK token (was API_KEY in v5.x)

**Optional**:
- `TREBLLE_ENABLE` - Enable/disable monitoring (default: `true`)
- `TREBLLE_IGNORED_ENV` - Comma-separated environments to ignore (default: `dev,test,testing`)
- `TREBLLE_DEBUG_MODE` - Enable debug mode (default: `false`)

### Middleware Registration

#### Laravel 10 and below (`app/Http/Kernel.php`):

```php
protected $middlewareAliases = [
    'treblle' => \Treblle\Laravel\Middlewares\TreblleMiddleware::class,
    'treblle.early' => \Treblle\Laravel\Middlewares\TreblleEarlyMiddleware::class,
];
```

#### Laravel 11+ (`bootstrap/app.php`):

```php
use Treblle\Laravel\Middlewares\TreblleMiddleware;
use Treblle\Laravel\Middlewares\TreblleEarlyMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'treblle' => TreblleMiddleware::class,
            'treblle.early' => TreblleEarlyMiddleware::class,
        ]);
    })
    ->create();
```

### Route Usage Patterns

#### Basic Monitoring

```php
// Monitor all routes in group
Route::middleware(['treblle'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
});

// Monitor specific route
Route::get('/users/{id}', [UserController::class, 'show'])
    ->middleware('treblle');
```

#### Multi-Project Setup

```php
// Public API - Project 1
Route::middleware(['treblle:proj_abc123'])->prefix('api/public')->group(function () {
    Route::get('/products', [PublicProductController::class, 'index']);
});

// Partner API - Project 2
Route::middleware(['treblle:proj_xyz789'])->prefix('api/partner')->group(function () {
    Route::post('/webhooks', [PartnerWebhookController::class, 'handle']);
});
```

**Note**: Dynamic API key parameter takes precedence over `.env` configuration.

#### Early Payload Capture

```php
// Capture original payload before transformation
Route::middleware(['treblle.early', 'transform-legacy', 'treblle'])
    ->group(function () {
        Route::post('/api/v1/users', [UserController::class, 'store']);
    });
```

**Middleware order is critical**: `treblle.early` must come BEFORE transformation middleware.

## Coding Standards

### Style Guide

- **PSR-12** coding standard (enforced via Laravel Pint)
- **Strict types** declaration in all files: `declare(strict_types=1);`
- **Type hints** for all parameters and return types
- **Final classes** by default
- **Readonly properties** where applicable (PHP 8.1+)
- **Named arguments** for clarity

### File Organization

```
src/
├── DataProviders/
│   ├── LaravelRequestDataProvider.php    # Laravel-specific request data
│   └── LaravelResponseDataProvider.php   # Laravel-specific response data
├── Exceptions/
│   └── TreblleException.php              # Configuration exceptions
├── Middlewares/
│   ├── TreblleMiddleware.php             # Main monitoring middleware
│   └── TreblleEarlyMiddleware.php        # Early payload capture
└── TreblleServiceProvider.php            # Laravel service provider

config/
└── treblle.php                            # Published configuration

tests/
└── TestCase.php                           # Base test class
```

**Note**: No `Helpers/` directory - all helper utilities (`SensitiveDataMasker`, `HeaderFilter`) come from `treblle-php` core package.

### Running Code Quality Tools

```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Test formatting without changes
./vendor/bin/pint --test

# Validate composer.json
composer validate
```

## Important Implementation Details

### Terminable Middleware Pattern

The `TreblleMiddleware` uses Laravel's terminable middleware pattern:

```php
public function handle($request, $next) {
    // Validation only - fast path
    return $next($request);
}

public function terminate($request, $response) {
    // Heavy lifting happens after response sent
    // Non-blocking for user
}
```

**Benefits**:
- Response sent to user immediately
- Data transmission happens after response
- No impact on user-perceived latency

### Laravel Octane Integration

Special handling for Laravel Octane:

```php
// In TreblleServiceProvider
$events->listen('Laravel\Octane\Events\RequestReceived', function ($event) {
    $event->request->attributes->set('treblle_request_started_at', microtime(true));
});
```

**Why**: Octane reuses workers, so `LARAVEL_START` isn't reset per request. We capture per-request start time in request attributes.

### Load Time Calculation Priority

1. **First priority**: `treblle_request_started_at` attribute (Octane + early middleware)
2. **Second priority**: `REQUEST_TIME_FLOAT` server variable
3. **Third priority**: `LARAVEL_START` constant

### Sensitive Data Masking

Leverages treblle-php v5 `SensitiveDataMasker` (`Treblle\Php\Helpers\SensitiveDataMasker`):

**Default masked fields** (from core SDK):
- `password`, `pwd`, `secret`, `password_confirmation`
- `cc`, `card_number`, `ccv`
- `ssn`, `credit_score`, `api_key`

**How it's used**:
```php
// In LaravelRequestDataProvider and LaravelResponseDataProvider
$masker = new SensitiveDataMasker($maskedFields);
$maskedData = $masker->mask($data);
```

**Additional Laravel-specific considerations**:
- CSRF tokens (not masked - framework handles)
- Session data (not captured by default)
- Authorization headers (masked by core SDK)
- Custom fields configured in `config/treblle.php`

### Header Filtering

Leverages treblle-php v5 `HeaderFilter` (`Treblle\Php\Helpers\HeaderFilter`):

**Configuration** (`config/treblle.php`):

```php
'excluded_headers' => [
    'authorization',        // Exact match
    'cookie',               // Exact match
    'x-*',                  // Wildcard prefix
    '*-token',              // Wildcard suffix
    '/^x-(api|auth)-/i',    // Regex pattern
],
```

**How it's used**:
```php
// In LaravelRequestDataProvider
use Treblle\Php\Helpers\HeaderFilter;

$filteredHeaders = HeaderFilter::filter(
    $this->request->headers->all(),
    config('treblle.excluded_headers', [])
);
```

**Applied in**:
- `LaravelRequestDataProvider` (request headers)
- `LaravelResponseDataProvider` (response headers)

**Pattern support** (from core SDK):
- Exact matching: `"Authorization"`
- Wildcard patterns: `"X-Internal-*"`, `"*-token"`
- Regex patterns: `"/^Authorization$/i"`

### Exception Handling

**Configuration errors** (thrown immediately):
- Missing `TREBLLE_SDK_TOKEN`: `TreblleException::missingSdkToken()`
- Missing `TREBLLE_API_KEY`: `TreblleException::missingApiKey()`

**Runtime errors** (handled by core SDK):
- Transmission failures: Silent (logged in debug mode)
- Invalid JSON: Silent (logged in debug mode)
- Large responses: Logged as error, empty body sent

### Multi-Project Support

Dynamic API key via middleware parameter:

```php
Route::middleware(['treblle:custom-api-key'])->group(function () {
    // Routes use custom-api-key instead of .env
});
```

**Implementation**:

```php
public function handle($request, $next, ?string $apiKey = null) {
    if (null !== $apiKey) {
        config(['treblle.api_key' => $apiKey]);
    }
    // ...
}
```

**Use case**: Single Laravel app serving multiple Treblle projects (e.g., public API, partner API, admin API).

## Testing

### Current Test Structure

- Base `TestCase` class using Orchestra Testbench
- Orchestra Testbench versions: ^9.0, ^10.0, ^11.0 (Laravel 10-12 support)
- No comprehensive test suite yet (opportunity for contribution)

### Testing Guidelines (When Adding Tests)

**Test categories**:
1. **Configuration tests**: Validate config merging, environment variables
2. **Middleware tests**: Route-level behavior, validation, termination
3. **Data provider tests**: Request/response data extraction
4. **Integration tests**: Full request lifecycle with Treblle

**Mock requirements**:
- Mock Guzzle client for transmission tests
- Mock Laravel request/response objects
- Mock treblle-php core components when testing Laravel-specific logic

### Manual Testing Checklist

- [ ] Publish config: `php artisan vendor:publish --provider="Treblle\Laravel\TreblleServiceProvider"`
- [ ] Check `php artisan about` output
- [ ] Test with `TREBLLE_ENABLE=false`
- [ ] Test ignored environments
- [ ] Test dynamic API key middleware parameter
- [ ] Test early payload capture
- [ ] Test with Laravel Octane
- [ ] Verify data in Treblle dashboard

## Migration Notes

### v5.x to v6.0

**Breaking Changes**:

1. **Configuration key rename**:
   ```php
   // Old (v5.x)
   'api_key' => env('TREBLLE_API_KEY'),
   'project_id' => env('TREBLLE_PROJECT_ID'),

   // New (v6.0)
   'sdk_token' => env('TREBLLE_SDK_TOKEN'),
   'api_key' => env('TREBLLE_API_KEY'),
   ```

2. **Environment variable rename**:
   ```shell
   # Old (v5.x)
   TREBLLE_API_KEY=your_old_api_key
   TREBLLE_PROJECT_ID=your_old_project_id

   # New (v6.0)
   TREBLLE_SDK_TOKEN=your_old_api_key
   TREBLLE_API_KEY=your_old_project_id
   ```

3. **Middleware parameter rename**:
   ```php
   // Old (v5.x)
   Route::middleware(['treblle:project-id-1'])

   // New (v6.0)
   Route::middleware(['treblle:api-key-1'])
   ```

4. **Exception methods**:
   - Removed: `TreblleException::missingProjectId()`
   - Added: `TreblleException::missingSdkToken()`
   - Changed: `TreblleException::missingApiKey()` (different meaning)

**Migration steps**:
1. Update `.env` file (swap values)
2. Update dynamic middleware parameters
3. Republish config (optional): `php artisan vendor:publish --provider="Treblle\Laravel\TreblleServiceProvider" --force`
4. Clear caches: `php artisan config:clear && php artisan cache:clear`

### Upgrading treblle-php Core Dependency

When treblle-php releases new versions:

1. Update `composer.json`: `"treblle/treblle-php": "^6.0"` (example)
2. Review treblle-php CHANGELOG for breaking changes
3. Update data providers if provider interfaces changed
4. Update middleware if TreblleFactory signature changed
5. Test with `composer update treblle/treblle-php`
6. Run `./vendor/bin/pint` to ensure code style
7. Update SDK version in `TreblleServiceProvider::SDK_VERSION`

## Common Tasks

### Adding a New Configuration Option

1. Add to `config/treblle.php`:
   ```php
   'new_option' => env('TREBLLE_NEW_OPTION', 'default'),
   ```

2. Document in README.md under "Features & Configuration"

3. Use in middleware or data providers:
   ```php
   $value = config('treblle.new_option');
   ```

4. Update `.env.example` (if repository has one)

### Adding Custom Data to Payload

**Option 1**: Extend data providers

```php
namespace App\Treblle;

use Treblle\Laravel\DataProviders\LaravelRequestDataProvider as BaseProvider;

class CustomRequestDataProvider extends BaseProvider {
    public function getRequest(): Request {
        $request = parent::getRequest();
        // Modify $request as needed
        return $request;
    }
}
```

Then pass via config:

```php
TreblleFactory::create(
    // ...
    config: [
        'request_provider' => new CustomRequestDataProvider($masker, $request),
    ]
);
```

**Option 2**: Use request attributes

```php
// In a middleware before TreblleMiddleware
$request->attributes->set('custom_data', ['foo' => 'bar']);

// Custom data provider reads it
$customData = $request->attributes->get('custom_data');
```

### Debugging SDK Issues

1. **Enable debug mode**:
   ```shell
   TREBLLE_DEBUG_MODE=true
   ```

2. **Check Laravel logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Verify configuration**:
   ```bash
   php artisan about
   # Look for "Treblle" section
   ```

4. **Test data transmission**:
   ```php
   // In tinker or controller
   config(['treblle.url' => 'https://your-test-endpoint.com']);
   ```

5. **Check middleware registration**:
   ```bash
   php artisan route:list
   # Verify treblle middleware appears on routes
   ```

6. **Validate headers excluded correctly**:
   - Add `dd()` in `LaravelRequestDataProvider::getRequest()`
   - Check which headers are present after `HeaderFilter::filter()`
   - Verify excluded patterns from config work correctly

### Supporting New Laravel Versions

1. **Update Orchestra Testbench**:
   ```json
   "require-dev": {
       "orchestra/testbench": "^13.0"
   }
   ```

2. **Test middleware registration** (may change in major versions)

3. **Test `about` command integration** (new in Laravel 9+)

4. **Update README Laravel version badge**

5. **Update `composer.json` Laravel constraint** if needed (currently implicit via Orchestra)

## Dependencies

### Production Dependencies

- **treblle/treblle-php**: `^5.0` - Core Treblle SDK (REQUIRED)
- **ext-json**: Required for JSON encoding
- **ext-mbstring**: Required for string operations
- **php**: `^8.2` - Minimum PHP version

### Development Dependencies

- **orchestra/testbench**: `^9.0 || ^10.0 || ^11.0` - Laravel testing framework
- **laravel/pint**: `^1.15` - Code formatter (PSR-12)

### Implicit Dependencies (via treblle-php)

- **guzzlehttp/guzzle**: `^7.4.5 || ^8.0 || ^9.0` - HTTP client
- **ext-pcntl**: Optional, for background processing

## Laravel Compatibility Matrix

| Laravel Version | PHP Version | Orchestra Testbench | Status |
|-----------------|-------------|---------------------|--------|
| 10.x | ^8.1 | ^8.0, ^9.0 | ✅ Tested |
| 11.x | ^8.2 | ^9.0, ^10.0 | ✅ Tested |
| 12.x | ^8.2 | ^10.0, ^11.0 | ✅ Tested |

**Note**: This SDK requires PHP 8.2+, which aligns with treblle-php v5 requirements.

## Links

- **Platform**: https://platform.treblle.com
- **Documentation**: https://docs.treblle.com
- **Laravel Integration Docs**: https://docs.treblle.com/en/integrations/laravel
- **Support**: https://treblle.com/chat (Discord)
- **GitHub**: https://github.com/Treblle/treblle-laravel
- **Core SDK**: https://github.com/Treblle/treblle-php

## Documentation Standards

### Code Documentation

All classes follow comprehensive PHPDoc standards:

```php
/**
 * Treblle middleware for monitoring API requests and responses.
 *
 * This middleware validates Treblle configuration, respects environment
 * exclusions, and uses the terminable pattern to send data to Treblle
 * after the response has been sent to the user.
 *
 * @package Treblle\Laravel\Middlewares
 */
final class TreblleMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $apiKey Optional dynamic API key override
     * @return mixed
     * @throws TreblleException
     */
    public function handle($request, $next, ?string $apiKey = null) {
        // ...
    }
}
```

### README Documentation

The README.md includes:
- Comprehensive configuration examples
- Multiple usage patterns (basic, advanced, multi-project)
- Real-world examples with explanations
- Upgrade guides with before/after comparisons
- Security features with visual examples

Keep README:
- Beginner-friendly
- Example-heavy
- Visual (JSON examples, before/after)
- Up-to-date with latest Laravel conventions

### CHANGELOG Documentation

Follow [Keep a Changelog](https://keepachangelog.com) format:
- Version and date
- Categorized changes (Added, Changed, Deprecated, Removed, Fixed, Security)
- Breaking changes highlighted
- Migration guides for major versions

## Notes for Claude

### When Making Changes

- **Always run `./vendor/bin/pint`** before committing
- **Maintain strict type declarations** in all files
- **Use final classes** by default (prevent inheritance issues)
- **Use readonly properties** where immutability makes sense
- **Test with multiple Laravel versions** via Orchestra Testbench
- **Update SDK_VERSION constant** when releasing new version
- **Update CHANGELOG.md** with all changes
- **Consider backward compatibility** for minor versions

### Laravel-Specific Conventions

- Use **Laravel's facades** when appropriate (Request, Response, etc.)
- Follow **Laravel naming conventions** (middleware aliases, config keys)
- Leverage **service provider lifecycle** (boot vs register)
- Use **terminable middleware** pattern for async work
- Support **middleware priority** for early capture
- Integrate with **Laravel Octane** events
- Provide **`artisan about`** integration

### Integration with Core SDK

- **Delegate to treblle-php** for core logic (masking, filtering, transmission)
- **Don't duplicate functionality** from core SDK
- **Use core SDK helper classes**: `SensitiveDataMasker`, `HeaderFilter`, `ErrorTypeTranslator`
- **Extend data providers** for Laravel-specific data
- **Follow core SDK conventions** (named arguments, strict types, DTOs)
- **Stay compatible** with core SDK version constraints
- **No custom helpers needed**: Laravel SDK has no `Helpers/` directory - uses treblle-php helpers directly

### Security Considerations

- **Never log credentials** in code or debug output
- **Mask sensitive fields** by default
- **Validate configuration** before enabling monitoring
- **Respect ignored environments** to prevent accidental production monitoring in dev
- **Use environment variables** for all sensitive configuration
- **Filter headers** by default (authorization, cookies)

### Performance Considerations

- **Use terminable middleware** to avoid blocking response
- **Lazy-load data providers** (create only when needed)
- **Respect 2MB response limit** to prevent memory issues
- **Use background processing** when pcntl available
- **Minimize middleware overhead** in handle() method (validation only)

### Testing Recommendations

When adding tests:
- Mock external HTTP requests (Guzzle)
- Test configuration validation thoroughly
- Test middleware termination (not just handle)
- Test early capture with transformed requests
- Test multi-project scenarios
- Test ignored environments
- Test exception handling
