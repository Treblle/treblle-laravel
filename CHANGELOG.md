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

### Added
- Support for Laravel 11+ via Orchestra Testbench ^9, ^10, ^11
- Updated SDK version to 6.0

### Migration Guide
1. Update your `.env` file:
   ```shell
   # Old configuration
   TREBLLE_API_KEY=your_old_api_key
   TREBLLE_PROJECT_ID=your_old_project_id

   # New configuration
   TREBLLE_SDK_TOKEN=your_old_api_key
   TREBLLE_API_KEY=your_old_project_id
   ```

2. If using dynamic middleware parameters, update route definitions:
   ```php
   // Old
   Route::middleware(['treblle:project-id-1'])->group(function () { ... });

   // New
   Route::middleware(['treblle:api-key-1'])->group(function () { ... });
   ```

3. Republish the config file (optional but recommended):
   ```bash
   php artisan vendor:publish --provider="Treblle\Laravel\TreblleServiceProvider" --force
   ```

## [2.8.1] - 2022-02-25
### Changed
- provide a fallback for masked fields list in case someone updated the package and didn't clear their cache (it happens)

## [2.8.0] - 2022-02-25
### Changed
- using Laravel HTTP client instead of Guzzle
- fixed missing Cache facade
- simplifed the Laravel Octane check
