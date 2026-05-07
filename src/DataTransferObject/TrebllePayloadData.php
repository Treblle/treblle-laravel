<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

/**
 * Data Transfer Object for Treblle payload.
 *
 * Holds the complete extracted payload data that can be safely serialized and
 * passed to queue jobs. By extracting data from Request/Response objects before
 * queuing, we avoid serialization issues with Closures and other non-serializable
 * properties.
 *
 * @package Treblle\Laravel\DataTransferObject
 */
final readonly class TrebllePayloadData
{
    public function __construct(
        public string $apiKey,
        public string $sdkToken,
        public string $sdkName,
        public float $sdkVersion,
        public Data $data,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'sdk_token' => $this->sdkToken,
            'sdk' => $this->sdkName,
            'version' => $this->sdkVersion,
            'data' => $this->data->jsonSerialize(),
        ];
    }
}
