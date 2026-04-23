<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

final readonly class Language implements JsonSerializable
{
    public function __construct(
        private string $name = 'php',
        private ?string $version = PHP_VERSION,
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
