<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataTransferObject;

use JsonSerializable;

/**
 * Base parameters required for any PayloadData DataTransferObject.
 * Child classes can extend and add additional properties required for specific type of payload data
 * Each child is required to define payload data type and implement getData method
 *
 * @package Treblle\Laravel\DataTransferObject
 */
abstract class BasePayloadData
{
    /**
     * The SDK name
     */
    protected string $sdkName;

    /**
     * The SDK version
     */
    protected float $sdkVersion;

    /**
     * Optional custom Treblle endpoint URL
     */
    protected ?string $url = null;

    /**
     * Whether debug mode is enabled
     */
    protected bool $debug = false;

    /**
     * The Treblle API key
     */
    protected string $apiKey;

    /**
     * The Treblle SDK token
     */
    protected string $sdkToken;

    /**
     * Type od payload, needs to be overridden in child classes
     */
    protected string $type = '';

    abstract public function setData(JsonSerializable $data);

    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function setSdkToken(string $sdkToken): self
    {
        $this->sdkToken = $sdkToken;

        return $this;
    }

    public function setSdkName(string $sdkName): self
    {
        $this->sdkName = $sdkName;

        return $this;
    }

    public function setSdkVersion(float $sdkVersion): self
    {
        $this->sdkVersion = $sdkVersion;

        return $this;
    }

    public function withUrl(?string $url = null): self
    {
        $this->url = $url ?? config('treblle.url');

        return $this;
    }

    public function withDebug(): self
    {
        $this->debug = config('treblle.debug');

        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getSdkToken(): string
    {
        return $this->sdkToken;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'sdk_token' => $this->sdkToken,
            'sdk' => $this->sdkName,
            'version' => $this->sdkVersion,
        ];
    }
}
