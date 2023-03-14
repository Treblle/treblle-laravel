<?php

declare(strict_types=1);

namespace Treblle\Tests\Clients;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Treblle\Clients\TreblleClient;
use Treblle\Contracts\TreblleClientContract;
use Treblle\Core\Http\Endpoint;
use Treblle\Tests\PackageTestCase;

class TreblleClientTest extends PackageTestCase
{
    /**
     * @var \Treblle\Clients\TreblleClient
     */
    private $treblleClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treblleClient = app()->make(
            abstract: TreblleClientContract::class,
        );
    }

    public function testGivenEmailToAuthLookUpReturnsValidResponse(): void
    {
        TreblleClient::fake([
            Arr::random(Endpoint::cases())->value . 'auth/lookup' => Http::response(['user' => null]),
        ]);

        $response = $this->treblleClient->authLookup('test@test.test');

        $this->assertNotEmpty($response);
    }

    public function testGivenNameEmailAndPasswordToRegisterReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            Arr::random(Endpoint::cases())->value.'auth/register' => Http::response(['user' => 'test_user']),
        ]);

        $response = $this->treblleClient->register('test_user', 'test@test.test', 'test_password');

        $this->assertNotEmpty($response);

        $this->assertSame('test_user', $response->object()->user);
    }

    public function testGivenEmailAndPasswordToLoginReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            Arr::random(Endpoint::cases())->value.'auth/login' => Http::response(['user' => 'test_user']),
        ]);

        $response = $this->treblleClient->login('test@test.test', 'test_password');

        $this->assertNotEmpty($response);

        $this->assertSame('test_user', $response->object()->user);
    }

    public function testGivenProjectNameAndUserUuidToCreateProjectReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            Arr::random(Endpoint::cases())->value.'projects/store' => Http::response(['project' => ['api_id' => 'test_id']]),
        ]);

        $response = $this->treblleClient->createProject('test_project', 'test_uuid');

        $this->assertNotEmpty($response);

        $this->assertSame('test_id', $response->object()->project->api_id);
    }
}
