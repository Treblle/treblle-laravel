<?php

declare(strict_types=1);

namespace Treblle\Laravel\Monitor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for Trebble third party aPI's monitoring
 *
 * @method static stopWatchStat()
 * @method static stopWatchEnd()
 * @method static monitor(string|int $statusCode, string|int $apiId, string|int $endpointId)
 *
 * @package Treblle\Laravel\Monitor\Facades
 */
final class TreblleMonitoring extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return \Treblle\Laravel\Monitor\Services\TreblleMonitoring::class;
    }
}
