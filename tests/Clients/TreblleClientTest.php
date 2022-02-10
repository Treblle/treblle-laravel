<?php

declare(strict_types=1);

namespace Treblle\Tests\Clients;

use Illuminate\Support\Facades\Http;
use Treblle\Clients\TreblleClient;
use Treblle\Tests\TestCase;

class TreblleClientTest extends TestCase
{
    /**
     * @var \Treblle\Clients\TreblleClient
     */
    private $treblleClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treblleClient = new TreblleClient;
    }

    public function testGivenEmailToAuthLookUpReturnsValidResponse(): void
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL.'auth/lookup' => Http::response(['user' => null]),
        ]);

        $response = $this->treblleClient->authLookup('test@test.test');

        $this->assertNotEmpty($response);
    }

    public function testGivenNameEmailAndPasswordToRegisterReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL.'auth/register' => Http::response(['user' => 'test_user']),
        ]);

        $response = $this->treblleClient->register('test_user', 'test@test.test', 'test_password');

        $this->assertNotEmpty($response);

        $this->assertSame('test_user', $response->object()->user);
    }

    public function testGivenEmailAndPasswordToLoginReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL.'auth/login' => Http::response(['user' => 'test_user']),
        ]);

        $response = $this->treblleClient->login('test@test.test', 'test_password');

        $this->assertNotEmpty($response);

        $this->assertSame('test_user', $response->object()->user);
    }

    public function testGivenProjectNameAndUserUuidToCreateProjectReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            TreblleClient::BASE_URL.'projects/store' => Http::response(['project' => ['api_id' => 'test_id']]),
        ]);

        $response = $this->treblleClient->createProject('test_project', 'test_uuid');

        $this->assertNotEmpty($response);

        $this->assertSame('test_id', $response->object()->project->api_id);
    }
}
