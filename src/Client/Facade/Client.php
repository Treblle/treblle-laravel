<?php

declare(strict_types=1);

namespace Treblle\Laravel\Client\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for Treblle Client
 *
 * @method static send(string $jsonPayload)
 */
final class Client extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Treblle\Laravel\Client\Client::class;
    }
}
