<?php

declare(strict_types=1);

namespace Treblle\Laravel\Exceptions;

use Exception;

/**
 * Treblle Configuration Exception.
 *
 * Exception thrown when required Treblle configuration is missing or invalid.
 * Provides specific factory methods for different configuration errors.
 *
 * @package Treblle\Laravel\Exceptions
 */
final class TreblleException extends Exception
{
    /**
     * Create an exception for missing SDK token configuration.
     *
     * The SDK token (previously called API key in v5.x) is required to authenticate
     * with the Treblle API. This should be set in the TREBLLE_SDK_TOKEN environment
     * variable.
     *
     * @return self A new exception instance with appropriate message
     */
    public static function missingSdkToken(): self
    {
        return new TreblleException(
            message: 'No SDK Token configured for Treblle. Ensure TREBLLE_SDK_TOKEN is set in your .env before trying again.',
        );
    }

    /**
     * Create an exception for missing API key configuration.
     *
     * The API key (previously called project ID in v5.x) identifies which
     * Treblle project this request should be associated with. This should be
     * set in the TREBLLE_API_KEY environment variable.
     *
     * @return self A new exception instance with appropriate message
     */
    public static function missingApiKey(): self
    {
        return new TreblleException(
            message: 'No API Key configured for Treblle. Ensure TREBLLE_API_KEY is set in your .env before trying again.',
        );
    }
}
