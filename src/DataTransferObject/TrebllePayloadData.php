<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;
use Treblle\Laravel\Monitor\Enums\PayloadDataType;

/**
 * Data Transfer Object for Treblle payload.
 *
 * This DTO holds the complete extracted payload data that can be safely
 * serialized and passed to queue jobs. By extracting data from Request/Response
 * objects before queuing, we avoid serialization issues with Closures and
 * other non-serializable properties.
 */
final class TrebllePayloadData extends BasePayloadData
{
    /**
     * Indicates that this is original api
     */
    protected string $type = PayloadDataType::ORIGIN->value;

    /**
     * The core Treblle data object containing request/response/errors
     */
    private JsonSerializable $data;

    public function setData(JsonSerializable $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Convert to array format for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            ...get_object_vars($this),
        ];
    }
}
