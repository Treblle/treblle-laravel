<?php

declare(strict_types=1);

namespace Treblle\Laravel\Monitor\DataTransferObjects;

use Treblle\Laravel\DataTransferObject\BasePayloadData;
use Treblle\Laravel\Monitor\Enums\PayloadDataType;
use Treblle\Php\DataTransferObject\Data;

/**
 * Data Transfer Object for Monitoring payload.
 *
 * This DTO holds the complete extracted payload data that can be safely
 * serialized and passed to queue jobs.
 *
 * @package Treblle\Laravel\Monitor\DataTransferObjects
 */
final class MonitoringPayloadData extends BasePayloadData
{
    /**
     * Indicates that this is third party api monitoring
     */
    protected string $type = PayloadDataType::THIRD_PARTY->value;

    /**
     * Data necessary for third party api's monitoring
     */
    private MonitoringData $data;

    public function setData(Data|MonitoringData $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            ...get_object_vars($this),
        ];
    }
}
