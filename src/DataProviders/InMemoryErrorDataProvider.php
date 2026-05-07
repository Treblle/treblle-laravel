<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Treblle\Laravel\DataTransferObject\Error;
use Treblle\Laravel\Contracts\ErrorDataProvider;

final class InMemoryErrorDataProvider implements ErrorDataProvider
{
    private const MAX_ERRORS = 25;

    /** @var list<Error> */
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addError(Error $error): void
    {
        if (count($this->errors) >= self::MAX_ERRORS) {
            return;
        }

        $this->errors[] = $error;
    }
}
