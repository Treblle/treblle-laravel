<?php

declare(strict_types=1);

namespace Treblle\Laravel\Monitor\DataTransferObjects;

use JsonSerializable;

/**
 * Data Transfer Object for specific data needed for Treblle third party monitoring endpoints.
 *
 * @package Treblle\Laravel\Monitor\DataTransferObjects
 */
final readonly class MonitoringData implements JsonSerializable
{
    public function __construct(
        private int $statusCode,
        private int $time, // Time of the API call
        private int $duration, // Duration of API call
        private string $apiId, // Unique id that identifies third party api
        private string $endpointId, // Unique id that identifies endpoint in third party api
        private array $config, // Whole user defined monitoring configuration will be passed
    ) {}

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
