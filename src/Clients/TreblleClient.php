<?php

declare(strict_types=1);

namespace Treblle\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
     * @throws \Exception
     *
     * @see TreblleClientTest::given_email_to_auth_look_up_returns_valid_response()
     */
    public function authLookUp(string $email): Response
    {
        return $this->request->send(
            method: 'POST',
            url: 'auth/lookup',
            options: [
                'email' => $email,
            ],
        );
    }

    /**
     * @throws \Exception
     *
     * @see TreblleClientTest::given_name_email_and_password_to_register_returns_registered_user_info()
     */
    public function register(string $name, string $email, string $password): Response
    {
        return $this->request->send(
            method: 'POST',
            url: 'auth/register',
            options: [
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ],
        );
    }

    /**
     * @see TreblleClientTest::given_email_and_password_to_login_returns_registered_user_info()
     */
    public function login(string $email, string $password): Response
    {
        return $this->request->send(
            method: 'POST',
            url: 'auth/login',
            options: [
                'email' => $email,
                'password' => $password,
            ],
        );
    }

    /**
     * @see TreblleClientTest::given_project_name_and_user_uuid_to_create_project_returns_registered_user_info()
     */
    public function createProject(string $projectName, string $userUuid): Response
    {
        return $this->request->send(
            method: 'POST',
            url: 'projects/store',
            options: [
                'name' => $projectName,
                'user' => $userUuid,
            ],
        );
    }
}
