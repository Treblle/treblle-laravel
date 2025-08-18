# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Code Quality
- **Lint code**: `composer pint` or `./vendor/bin/pint`
- **Static analysis**: `composer stan` or `./vendor/bin/phpstan analyse`
- **Run tests**: `composer test` or `./vendor/bin/phpunit`

### Installation
- **Install dependencies**: `composer install`
- **Publish config**: `php artisan vendor:publish --tag=treblle-config`

## Architecture Overview

This is the **Treblle Laravel SDK** - a Laravel middleware package for API monitoring and observability. The package integrates with Laravel applications to automatically capture and send API request/response data to the Treblle platform.

### Core Components

**TreblleServiceProvider** (`src/TreblleServiceProvider.php`):
- Registers the middleware alias 'treblle' 
- Publishes configuration file
- Handles Laravel Octane integration
- Adds about command information

**TreblleMiddleware** (`src/Middlewares/TreblleMiddleware.php`):
- Main middleware that intercepts HTTP requests/responses
- Validates configuration (API key, project ID)
- Uses terminate() method for data collection after response
- Supports dynamic project ID assignment via middleware parameter
- Integrates with treblle-php core SDK for data transmission

**Data Providers** (`src/DataProviders/`):
- `LaravelRequestDataProvider`: Extracts request data (headers, body, etc.)
- `LaravelResponseDataProvider`: Extracts response data and errors

**Configuration** (`config/treblle.php`):
- Environment-based settings (TREBLLE_API_KEY, TREBLLE_PROJECT_ID)
- Field masking for sensitive data
- Environment-specific ignoring
- Debug mode toggle

### Integration Pattern

The middleware can be applied in three ways:
1. **Route groups**: `Route::middleware(['treblle'])->group(...)`
2. **Individual routes**: `Route::get(...)->middleware('treblle')`
3. **Dynamic project IDs**: `Route::middleware(['treblle:project-id-123'])->group(...)`

### Dependencies

- Requires PHP 8.2+
- Built on `treblle/treblle-php` core SDK (v4.0.2+)
- Uses Laravel service container and middleware system
- Supports Laravel Octane for high-performance applications

### Testing & Code Standards

- Uses Orchestra Testbench for Laravel package testing
- Follows PSR-12 coding standards with custom Pint configuration
- PHPStan level 9 static analysis
- Strict typing enforced (`declare(strict_types=1)`)

## Environment Variables

Required for operation:
- `TREBLLE_API_KEY`: API key from Treblle dashboard  
- `TREBLLE_PROJECT_ID`: Project ID from Treblle dashboard

Optional:
- `TREBLLE_ENABLE`: Enable/disable monitoring (default: true)
- `TREBLLE_IGNORED_ENV`: Comma-separated environments to ignore (default: 'dev,test,testing')
- `TREBLLE_DEBUG_MODE`: Debug mode for development (default: false)