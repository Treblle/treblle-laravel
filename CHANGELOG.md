# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.0.0] - 2025-10-10

### Breaking Changes

- **Updated to treblle-php v5**: Upgraded core dependency from treblle-php v4 to v5
- **Configuration key changes**:
  - `TREBLLE_PROJECT_ID` is now `TREBLLE_API_KEY`
  - `TREBLLE_API_KEY` is now `TREBLLE_SDK_TOKEN`
  - Config file keys updated: `project_id` → `api_key`, `api_key` → `sdk_token`
- **Middleware parameter change**: Dynamic middleware parameter changed from `project_id` to `api_key`
  - Old: `Route::middleware(['treblle:project-id-1'])`
  - New: `Route::middleware(['treblle:api-key-1'])`
- **Exception method changes**:
  - `TreblleException::missingProjectId()` renamed to `TreblleException::missingApiKey()`
  - Added new `TreblleException::missingSdkToken()` method
- **Helper classes removed**: No longer using custom `HeaderProcessor` - now uses `Treblle\Php\Helpers\HeaderFilter` from core SDK
- **Data masking class changed**: Now uses `Treblle\Php\Helpers\SensitiveDataMasker` instead of `FieldMasker`

### Added

- Support for Laravel 11+ via Orchestra Testbench ^9, ^10, ^11
- Laravel 12 compatibility
- Updated SDK version to 6.0
- Comprehensive README with detailed examples
- Complete `claude.md` documentation for contributors

### Changed

- Data providers now use `SensitiveDataMasker` from treblle-php v5
- Header filtering now uses `HeaderFilter::filter()` from treblle-php v5
- Updated composer dependencies for latest Laravel versions

### Migration Guide

1. Update your `.env` file:
   ```shell
   # Old configuration (v5.x)
   TREBLLE_API_KEY=your_old_api_key
   TREBLLE_PROJECT_ID=your_old_project_id

   # New configuration (v6.0)
   TREBLLE_SDK_TOKEN=your_old_api_key
   TREBLLE_API_KEY=your_old_project_id
   ```

2. If using dynamic middleware parameters, update route definitions:
   ```php
   // Old (v5.x)
   Route::middleware(['treblle:project-id-1'])->group(function () { ... });

   // New (v6.0)
   Route::middleware(['treblle:api-key-1'])->group(function () { ... });
   ```

3. Republish the config file (optional but recommended):
   ```bash
   php artisan vendor:publish --provider="Treblle\Laravel\TreblleServiceProvider" --force
   ```

4. Clear caches:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

> For a complete upgrade guide with troubleshooting tips, see the "Upgrading from v5.x to v6.0" section in [README.md](README.md#upgrading-from-v5x-to-v60)

## [5.0.0] - Previous Release

- Initial release with treblle-php v4 support
- Laravel 10 compatibility
