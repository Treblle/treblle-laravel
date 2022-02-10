<?php

declare(strict_types=1);

namespace Treblle\Test\Clients;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Treblle\Clients\TreblleClient;
use Treblle\Test\TestCase;

class TreblleClientTest extends TestCase
{
    public function testGivenEmailToAuthLookUpReturnsValidResponse(): void
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL.'auth/lookup' => Http::response(['user' => null]),
        ]);

        $response = (new TreblleClient())->authLookup('test@test.test');

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testGivenNameEmailAndPasswordToRegisterReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL.'auth/register' => Http::response(['user' => 'test_user']),
        ]);

        $response = (new TreblleClient())->register('test_user', 'test@test.test', 'test_password');

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals('test_user', $response->object()->user);
    }

    public function testGivenEmailAndPasswordToLoginReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL.'auth/login' => Http::response(['user' => 'test_user']),
        ]);

        $response = (new TreblleClient())->login('test@test.test', 'test_password');

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals('test_user', $response->object()->user);
    }

    public function testGivenProjectNameAndUserUuidToCreateProjectReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL.'projects/store' => Http::response(['project' => ['api_id' => 'test_id']]),
        ]);

        $response = (new TreblleClient())->createProject('test_project', 'test_uuid');

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals('test_id', $response->object()->project->api_id);
    }
}
