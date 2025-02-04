<?php

declare(strict_types=1);

namespace Treblle\Laravel\Clients;

use Illuminate\Http\Client\PendingRequest;
use Treblle\Laravel\Clients\Resources\AuthResource;
use Treblle\Laravel\Contracts\TreblleClientContract;
use Treblle\Laravel\Clients\Resources\ProjectResource;

final class TreblleClient implements TreblleClientContract
{
    use HasFake;

    public function __construct(
        private readonly PendingRequest $request,
    ) {
    }

    /**
     * @return AuthResource
     */
    public function auth(): AuthResource
    {
        return new AuthResource(
            client: $this,
        );
    }

    /**
     * @return ProjectResource
     */
    public function projects(): ProjectResource
    {
        return new ProjectResource(
            client: $this,
        );
    }

    /**
     * @return PendingRequest
     */
    public function request(): PendingRequest
    {
        return $this->request;
    }
}
