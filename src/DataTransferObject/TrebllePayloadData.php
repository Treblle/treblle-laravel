<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use Treblle\Php\DataTransferObject\Data;

/**
 * Data Transfer Object for Treblle payload.
 *
 * This DTO holds the complete extracted payload data that can be safely
 * serialized and passed to queue jobs. By extracting data from Request/Response
 * objects before queuing, we avoid serialization issues with Closures and
 * other non-serializable properties.
 *
 * @package Treblle\Laravel\DataTransferObject
 */
final readonly class TrebllePayloadData
{
    /**
     * Create a new TrebllePayloadData instance.
     *
     * @param string $apiKey The Treblle API key
     * @param string $sdkToken The Treblle SDK token
     * @param string $sdkName The SDK name
     * @param float $sdkVersion The SDK version
     * @param Data $data The core Treblle data object containing request/response/errors
     * @param string|null $url Optional custom Treblle endpoint URL
     * @param bool $debug Whether debug mode is enabled
     */
    public function __construct(
        public string $apiKey,
        public string $sdkToken,
        public string $sdkName,
        public float $sdkVersion,
        public Data $data,
        public string|null $url = null,
        public bool $debug = false
    ) {
    }

    /**
     * Convert to array format for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'sdk_token' => $this->sdkToken,
            'sdk' => $this->sdkName,
            'version' => $this->sdkVersion,
            'data' => $this->data,
        ];
    }
}
