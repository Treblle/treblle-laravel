<?php

declare(strict_types=1);

namespace Treblle\Clients;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TreblleClient
{
    use HasFake;

    public const BASE_URL = 'https://treblle.com/api/v1/';
    private const API_KEY = 'Y8fNzfhRab9FMeHXXbxT6Q0qqfmmTBKq';

    /**
     * @see TreblleClientTest::given_email_to_auth_look_up_returns_valid_response()
     */
    public function authLookUp(string $email): Response
    {
        return $this->getAPIResponse('auth/lookup', [
            'email' => $email,
        ]);
    }

    /**
     * @see TreblleClientTest::given_name_email_and_password_to_register_returns_registered_user_info()
     */
    public function register(string $name, string $email, string $password): Response
    {
        return $this->getAPIResponse('auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * @see TreblleClientTest::given_email_and_password_to_login_returns_registered_user_info()
     */
    public function login(string $email, string $password): Response
    {
        return $this->getAPIResponse('auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * @see TreblleClientTest::given_project_name_and_user_uuid_to_create_project_returns_registered_user_info()
     */
    public function createProject(string $projectName, string $userUuid): Response
    {
        return $this->getAPIResponse('projects/store', [
            'name' => $projectName,
            'user' => $userUuid,
        ]);
    }

    private function getAPIResponse(string $url, array $formParams): Response
    {
        return Http::withToken(self::API_KEY)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'TreblleSetupCommand/0.1',
            ])
            ->post(self::BASE_URL.$url, $formParams);
    }
}
