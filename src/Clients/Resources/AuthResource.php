<?php

declare(strict_types=1);

namespace Treblle\Laravel\Clients\Resources;

use Treblle\Laravel\DataObjects\User;
use Treblle\Laravel\DataObjects\Account;
use Illuminate\Http\Client\RequestException;
use Treblle\Laravel\Contracts\TreblleClientContract;

final class AuthResource
{
    public function __construct(
        private TreblleClientContract $client,
    ) {

    }

    /**
     * @param string $email
     *
     * @throws RequestException
     *
     * @return User
     */
    public function lookup(string $email): User
    {
        $response = $this->client->request()->send(
            method: 'POST',
            url: 'auth/lookup',
            options: [
                'json' => [
                    'email' => $email,
                ],
            ],
        )->throw();

        return User::fromRequest(
            data: (array) $response->json(),
        );
    }

    /**
     * @param string $name
     * @param string $email
     * @param string $password
     *
     * @throws RequestException
     *
     * @return Account
     */
    public function register(string $name, string $email, string $password): Account
    {
        $response = $this->client->request()->send(
            method: 'POST',
            url: 'auth/register',
            options: [
                'json' => [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ],
            ],
        )->throw();

        return Account::fromRequest(
            data: (array) $response->json(),
        );
    }

    /**
     * @param string $email
     * @param string $password
     *
     * @throws RequestException
     *
     * @return Account
     */
    public function login(string $email, string $password): Account
    {
        $response = $this->client->request()->send(
            method: 'POST',
            url: 'auth/login',
            options: [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                ],
            ],
        )->throw();

        return Account::fromRequest(
            data: (array) $response->json(),
        );
    }
}
