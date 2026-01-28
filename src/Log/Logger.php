<?php

declare(strict_types=1);

namespace Treblle\Laravel\Log;

use Throwable;

final class Logger
{
    public function __construct(
        private bool $enabled = false,
    ) {
        $this->enabled = config('treblle.debug');
    }

    /**
     * Log warnings when debug mode is enabled.
     *
     * @param  string  $message  The error message
     */
    public function logWarning(string $message): void
    {
        if ($this->enabled) {
            logger()->warning('[Treblle] ' . $message);
        }
    }

    /**
     * Log runtime errors when debug mode is enabled.
     *
     * @param  string  $message  The error message
     * @param  Throwable  $exception  The exception that was thrown
     */
    public function logException(string $message, Throwable $exception): void
    {
        if ($this->enabled) {
            logger()->error('[Treblle] ' . $message, [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }
    }
}
