<?php

declare(strict_types=1);

namespace Treblle\Laravel\Monitor\DataTransferObjects;

use JsonSerializable;
use Treblle\Laravel\Monitor\Enums\PayloadDataType;
use Treblle\Laravel\DataTransferObject\BasePayloadData;

/**
 * Data Transfer Object for Monitoring payload.
 *
 * This DTO holds the complete extracted payload data that can be safely
 * serialized and passed to queue jobs.
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
    private JsonSerializable $data;

    public function setData(JsonSerializable $data): self
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
