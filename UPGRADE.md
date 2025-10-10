# Upgrade Guide

## Upgrading from v5.x to v6.0

Version 6.0 brings compatibility with treblle-php v5 and includes breaking changes to configuration keys. This guide will help you migrate smoothly.

### What Changed?

The main change is in how Treblle credentials are configured. The naming has been updated to align with treblle-php v5:

| Old Key (v5.x) | New Key (v6.0) | Description |
|----------------|----------------|-------------|
| `TREBLLE_API_KEY` | `TREBLLE_SDK_TOKEN` | Your SDK authentication token |
| `TREBLLE_PROJECT_ID` | `TREBLLE_API_KEY` | Your project/API identifier |

### Step-by-Step Migration

#### 1. Update Environment Variables

Update your `.env` file by swapping the values:

```shell
# Before (v5.x)
TREBLLE_API_KEY=abc123xyz
TREBLLE_PROJECT_ID=proj_456def

# After (v6.0)
TREBLLE_SDK_TOKEN=abc123xyz
TREBLLE_API_KEY=proj_456def
```

**Important**: Notice that the *values* are swapped - what was your API key is now your SDK token, and what was your project ID is now your API key.

#### 2. Update Dynamic Middleware Parameters (If Applicable)

If you're using dynamic project/API key parameters in your routes, update the middleware calls:

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

#### 3. Republish Configuration (Optional but Recommended)

To get the latest config file with updated comments:

```bash
php artisan vendor:publish --provider="Treblle\Laravel\TreblleServiceProvider" --force
```

Then re-apply any custom configurations you had.

#### 4. Update Composer Dependencies

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

#### 5. Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
```

### Verification

After migration, verify everything is working:

1. Check your configuration:
```bash
php artisan about
```

Look for the Treblle section to confirm your keys are loaded correctly.

2. Make a test API request and verify it appears in your Treblle dashboard.

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

### Common Issues

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

### Need Help?

If you encounter any issues during the upgrade:

1. Check the [documentation](https://docs.treblle.com/en/integrations/laravel)
2. Review the [CHANGELOG.md](CHANGELOG.md) for all changes
3. Open an issue on [GitHub](https://github.com/Treblle/treblle-laravel/issues)
4. Join our [Discord community](https://treblle.com/chat)

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
