<?php

declare(strict_types=1);

namespace Treblle\Exceptions;

use Exception;

final class ConfigurationException extends Exception
{
    public static function noApiKey(): ConfigurationException
    {
        return new ConfigurationException(
            message: 'No API Key configured for Treblle. Ensure this is set before trying again.',
        );
    }
}
