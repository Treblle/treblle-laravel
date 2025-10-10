<?php

declare(strict_types=1);

namespace Treblle\Laravel\Exceptions;

use Exception;

final class TreblleException extends Exception
{
    public static function missingSdkToken(): self
    {
        return new TreblleException(
            message: 'No SDK Token configured for Treblle. Ensure TREBLLE_SDK_TOKEN is set in your .env before trying again.',
        );
    }

    public static function missingApiKey(): self
    {
        return new TreblleException(
            message: 'No API Key configured for Treblle. Ensure TREBLLE_API_KEY is set in your .env before trying again.',
        );
    }
}
