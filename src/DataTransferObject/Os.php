<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

final class Os implements JsonSerializable
{
    public function __construct(
        private readonly ?string $name = null,
        private readonly ?string $release = null,
        private readonly ?string $architecture = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
