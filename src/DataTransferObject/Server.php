<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

final readonly class Server implements JsonSerializable
{
    public function __construct(
        private string $ip = 'bogon',
        private string $timezone = 'UTC',
        private ?string $software = null,
        private ?string $protocol = null,
        private Os $os = new Os(),
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'ip'       => $this->ip,
            'timezone' => $this->timezone,
            'software' => $this->software,
            'protocol' => $this->protocol,
            'os'       => $this->os,
        ];
    }
}
