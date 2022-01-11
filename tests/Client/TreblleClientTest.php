<?php

declare(strict_types=1);

namespace Treblle\Test\Client;

use Treblle\Test\TestCase;
use Treblle\Client\TreblleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class TreblleClientTest extends TestCase
{
    /** @test */
    public function given_email_to_auth_look_up_returns_valid_response()
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL . 'auth/lookup' => Http::response(['user' => null]),
        ]);

        $response = (new TreblleClient())->authLookup('test@test.test');

        self::assertInstanceOf(Response::class, $response);
    }

    /** @test */
    public function given_name_email_and_password_to_register_returns_registered_user_info()
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL . 'auth/register' => Http::response(['user' => 'test_user']),
        ]);

        $response = (new TreblleClient())->register('test_user', 'test@test.test', 'test_password');

        self::assertInstanceOf(Response::class, $response);

        self::assertEquals('test_user', $response->object()->user);
    }

    /** @test */
    public function given_email_and_password_to_login_returns_registered_user_info()
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL . 'auth/login' => Http::response(['user' => 'test_user']),
        ]);

        $response = (new TreblleClient())->register('test_user', 'test@test.test', 'test_password');

        self::assertInstanceOf(Response::class, $response);

        self::assertEquals('test_user', $response->object()->user);
    }

    /** @test */
    public function given_project_name_and_user_uuid_to_create_project_returns_registered_user_info()
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL . 'projects/store' => Http::response(['project' => ['api_id' => 'test_id']]),
        ]);

        $response = (new TreblleClient())->createProject('test_project', 'test_uuid');

        self::assertInstanceOf(Response::class, $response);

        self::assertEquals('test_id', $response->object()->project->api_id);
    }
}
