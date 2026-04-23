<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

final readonly class Request implements JsonSerializable
{
    public function __construct(
        private string $timestamp,
        private string $url,
        private string $ip = 'bogon',
        private string $user_agent = '',
        private string $method = 'GET',
        private array $headers = [],
        private array $query = [],
        private array $body = [],
        private ?string $route_path = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
