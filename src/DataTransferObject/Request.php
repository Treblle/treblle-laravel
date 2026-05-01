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
        return [
            'timestamp'  => $this->timestamp,
            'url'        => $this->url,
            'ip'         => $this->ip,
            'user_agent' => $this->user_agent,
            'method'     => $this->method,
            'headers'    => $this->headers,
            'query'      => $this->query,
            'body'       => $this->body,
            'route_path' => $this->route_path,
        ];
    }
}
