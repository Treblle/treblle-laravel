<?php

declare(strict_types=1);

namespace Treblle\Laravel\Clients\Resources;

use Exception;
use Treblle\Laravel\DataObjects\Project;
use Illuminate\Http\Client\RequestException;
use Treblle\Laravel\Contracts\TreblleClientContract;

final class ProjectResource
{
    public function __construct(
        private TreblleClientContract $client,
    ) {

    }

    /**
     * @param string $name
     * @param string $user
     *
     * @throws RequestException|Exception
     *
     * @return Project
     */
    public function create(string $name, string $user): Project
    {
        $response = $this->client->request()->send(
            method: 'POST',
            url: 'projects/store',
            options: [
                'json' => [
                    'name' => $name,
                    'user' => $user,
                ],
            ],
        )->throw();

        return Project::fromRequest(
            data: (array) $response->json(
                key: 'project',
            ),
        );
    }
}
