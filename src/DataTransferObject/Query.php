<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

final readonly class Query implements JsonSerializable
{
    public function __construct(
        private string $sql,
        private float $time,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'sql'  => $this->sql,
            'time' => $this->time,
        ];
    }
}
