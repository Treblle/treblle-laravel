<?php

declare(strict_types=1);

namespace Treblle\Contracts;

use Closure;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Treblle\Clients\Resources\AuthResource;
use Treblle\Clients\Resources\ProjectResource;

interface TreblleClientContract
{
    /**
     * @param array|Closure|null $callback
     *
     * @return Factory
     */
    public static function fake(null|array|Closure $callback = null): Factory;

    /**
     * @return PendingRequest
     */
    public function request(): PendingRequest;

    /**
     * @return AuthResource
     */
    public function auth(): AuthResource;

    /**
     * @return ProjectResource
     */
    public function projects(): ProjectResource;
}
