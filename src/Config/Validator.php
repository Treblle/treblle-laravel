<?php

declare(strict_types=1);

namespace Treblle\Laravel\Config;

use Treblle\Laravel\Exceptions\TreblleException;

/**
 * Validation class, for required configuration properties
 *
 * Tasks:
 *  - check if sdk key is set
 *  - check if api key
 *  - check for excluded environments
 *
 * @package Treblle\Laravel\Config
 */
final class Validator
{
    private bool $debugEnabled;

    private array $ignoredEnvironments;

    public function __construct()
    {
        $this->debugEnabled = config('treblle.debug');

        $ignoredEnvs = array_map('trim', explode(',', config('treblle.ignored_environments', '') ?? ''));
        $this->ignoredEnvironments = array_flip($ignoredEnvs);
    }

    /**
     * Validates if sdk key and api key are set
     *
     * @throws TreblleException
     */
    public function validateKeys(): void
    {
        // Validate configuration - fail silently if missing to never break the API
        if (! config('treblle.sdk_token')) {
            $this->logConfigError('TREBLLE_SDK_TOKEN is not configured. Treblle monitoring disabled.');

            throw new TreblleException('Treblle sdk key not found');
        }

        if (! config('treblle.api_key')) {
            $this->logConfigError('TREBLLE_API_KEY is not configured. Treblle monitoring disabled.');

            throw new TreblleException('Treblle api key not found');
        }
    }

    /**
     * Validates that current environments is not ignored
     *
     * @throws TreblleException
     */
    public function validateEnvironment(): void
    {
        if (isset($this->ignoredEnvironments[app()->environment()])) {
            throw new TreblleException('Current environment is ignored');
        }
    }

    /**
     * Log configuration errors when debug mode is enabled.
     *
     * @param  string  $message  The error message
     */
    private function logConfigError(string $message): void
    {
        if ($this->debugEnabled) {
            logger()->warning('[Treblle] ' . $message);
        }
    }
}
