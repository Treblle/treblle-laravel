<?php

declare(strict_types=1);

namespace Treblle\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Treblle runtime helper.
 *
 * @method static void meta(string|array<string, mixed> $key, mixed $value = null) Add metadata to the current request's Treblle payload.
 *
 * @see \Treblle\Laravel\Treblle
 *
 * @package Treblle\Laravel\Facades
 */
final class Treblle extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Treblle\Laravel\Treblle::class;
    }
}
