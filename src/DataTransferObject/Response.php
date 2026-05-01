<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

final readonly class Response implements JsonSerializable
{
    public function __construct(
        private int $code = 200,
        private float $size = 0.0,
        private float $load_time = 0.0,
        private array $body = [],
        private array $headers = [],
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'code'      => $this->code,
            'size'      => $this->size,
            'load_time' => $this->load_time,
            'body'      => $this->body,
            'headers'   => $this->headers,
        ];
    }
}
