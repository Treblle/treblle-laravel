<?php

declare(strict_types=1);

namespace Treblle\Laravel\Factories;

use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Laravel\DataTransferObject\BasePayloadData;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;
use Treblle\Laravel\Monitor\DataTransferObjects\MonitoringPayloadData;

/**
 * Constructs object of type PayloadData
 *
 * @package Treblle\Laravel\Factories
 */
final class PayloadDataFactory
{
    public const TREBLLE = 'treblle';
    public const MONITORING = 'monitoring';

    /**
     * Creates a new object of type PayloadData
     *
     * @throws TreblleException
     */
    public static function create(string $type): BasePayloadData
    {
        $object = match ($type) {
            self::MONITORING => new MonitoringPayloadData(),
            self::TREBLLE => new TrebllePayloadData(),
            default => throw new TreblleException('Unknown payload type'),
        };

        $object->setApiKey((string) config('treblle.api_key'))
            ->setSdkToken((string) config('treblle.sdk_token'))
            ->setSdkName(TreblleServiceProvider::SDK_NAME)
            ->setSdkVersion(TreblleServiceProvider::SDK_VERSION)
            ->withUrl()
            ->withDebug();

        return $object;
    }
}
