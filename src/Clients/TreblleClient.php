<?php

declare(strict_types=1);

namespace Treblle\Clients;

use Illuminate\Http\Client\PendingRequest;
use Treblle\Clients\Resources\AuthResource;
use Treblle\Clients\Resources\ProjectResource;
use Treblle\Contracts\TreblleClientContract;

final class TreblleClient implements TreblleClientContract
{
    use HasFake;

    public function __construct(
        private PendingRequest $request,
    ) {
        //
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
