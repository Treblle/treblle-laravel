<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

final readonly class Data implements JsonSerializable
{
    /**
     * @param list<Error> $errors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private Server $server,
        private Language $language,
        private Request $request,
        private Response $response,
        private array $errors,
        private array $metadata = [],
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'server'   => $this->server,
            'language' => $this->language,
            'request'  => $this->request,
            'response' => $this->response,
            'errors'   => $this->errors,
            'metadata' => $this->metadata,
        ];
    }
}
