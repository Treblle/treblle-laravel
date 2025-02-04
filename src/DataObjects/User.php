<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataObjects;

final class User
{
    public function __construct(
        public null|string $name = null,
    ) {
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            name: (string) (data_get($data, 'user.name')),
        );
    }
}
