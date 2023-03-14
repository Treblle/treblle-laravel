<?php

declare(strict_types=1);

namespace Treblle\Tests\Clients;

use Illuminate\Support\Facades\Http;
use Treblle\Clients\TreblleClient;
use Treblle\Contracts\TreblleClientContract;
use Treblle\Tests\TestCase;

class TreblleClientTest extends TestCase
{
    private function client(): TreblleClientContract
    {
        return app()->make(
            abstract: TreblleClientContract::class,
        );
    }

    public function testGivenEmailToAuthLookUpReturnsValidResponse(): void
    {
        TreblleClient::fake([
            '*' => Http::response(['user' => null]),
        ]);

        $response = $this
            ->client()
            ->authLookup('test@test.test');

        $this->assertNotEmpty($response);
    }

    public function testGivenNameEmailAndPasswordToRegisterReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            '*' => Http::response(['user' => 'test_user']),
        ]);

        $response = $this
            ->client()
            ->register('test_user', 'test@test.test', 'test_password');

        $this->assertNotEmpty($response);

        $this->assertSame('test_user', $response->object()->user);
    }

    public function testGivenEmailAndPasswordToLoginReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            '*' => Http::response(['user' => 'test_user']),
        ]);

        $response = $this
            ->client()
            ->login('test@test.test', 'test_password');

        $this->assertNotEmpty($response);

        $this->assertSame('test_user', $response->object()->user);
    }

    public function testGivenProjectNameAndUserUuidToCreateProjectReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            '*' => Http::response(['project' => ['api_id' => 'test_id']]),
        ]);

        $response = $this
            ->client()
            ->createProject('test_project', 'test_uuid');

        $this->assertNotEmpty($response);

        $this->assertSame('test_id', $response->object()->project->api_id);
    }
}
