<?php

declare(strict_types=1);

namespace Treblle\DataObjects;

final class User
{
    public function __construct(
        public null|string $name = null,
    ) {
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            name: strval(data_get($data, 'user.name')),
        );
    }
}
