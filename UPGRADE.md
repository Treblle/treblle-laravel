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

- **Exception handling**: `TreblleException::missingProjectId()` is now `TreblleException::missingApiKey()`
- **New exception**: `TreblleException::missingSdkToken()` is available for SDK token validation
- **Config access**: Update any code that reads `config('treblle.project_id')` to `config('treblle.api_key')`

### Laravel Compatibility

Version 6.0 adds support for Laravel 11 while maintaining backward compatibility:

- Laravel 10: ✅ Supported
- Laravel 11: ✅ Supported
- PHP 8.2+: ✅ Required

### Need Help?

If you encounter any issues during the upgrade:

1. Check the [documentation](https://docs.treblle.com/en/integrations/laravel)
2. Review the [CHANGELOG.md](CHANGELOG.md) for all changes
3. Open an issue on [GitHub](https://github.com/Treblle/treblle-laravel/issues)
4. Join our [Discord community](https://treblle.com/chat)
