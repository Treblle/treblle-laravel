<?php

declare(strict_types=1);

namespace Treblle\Laravel\Contracts;

use Closure;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Treblle\Laravel\Clients\Resources\AuthResource;
use Treblle\Laravel\Clients\Resources\ProjectResource;

interface TreblleClientContract
{
    /**
     * Fake a request using a proxy to Http::fake()
     *
     * @param array|Closure|null $callback
     *
     * @return Factory
     */
    public static function fake(null|array|Closure $callback = null): Factory;

    /**
     * Return the current PendingRequest object
     *
     * @return PendingRequest
     */
    public function request(): PendingRequest;

    /**
     * Send an Auth request
     *
     * @return AuthResource
     */
    public function auth(): AuthResource;

    /**
     * Send a Projects request
     *
     * @return ProjectResource
     */
    public function projects(): ProjectResource;
}
