# Contributing

Thank you for considering a contribution to Treblle for Laravel.

## Setup

```bash
git clone https://github.com/Treblle/treblle-laravel.git
cd treblle-laravel
composer install
```

## Running tests

```bash
./vendor/bin/phpunit
```

The test suite runs against all supported Laravel versions in CI. Locally it uses whatever version `composer install` resolved.

## Code style

This project enforces [PSR-12](https://www.php-fig.org/psr/psr-12/) via [Laravel Pint](https://laravel.com/docs/pint).

```bash
# Fix violations automatically
./vendor/bin/pint

# Check without making changes (what CI runs)
./vendor/bin/pint --test
```

Run Pint before pushing. The lint workflow will fail the PR if style violations are present.

## Pull requests

1. Fork the repository and create your branch from `main`.
2. Make your changes.
3. Add or update tests to cover your change.
4. Run `./vendor/bin/phpunit` — all tests must pass.
5. Run `./vendor/bin/pint` — fix any style violations.
6. Add an entry to `CHANGELOG.md` under `[Unreleased]`.
7. Open the pull request against `main`.

Keep pull requests focused. One feature or fix per PR makes review faster and history cleaner.

## Reporting bugs

Use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.md). Include your PHP version, Laravel version, and SDK version. A minimal reproduction case is the fastest way to get a fix.

## Security vulnerabilities

Do **not** open a public issue for security vulnerabilities. See [SECURITY.md](SECURITY.md) for the responsible disclosure process.
