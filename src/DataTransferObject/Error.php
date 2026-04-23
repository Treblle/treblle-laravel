<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

final readonly class Error implements JsonSerializable
{
    public function __construct(
        private string $message,
        private string $file,
        private int $line,
        private string $source = 'onError',
        private string $type = 'UNHANDLED_EXCEPTION',
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
