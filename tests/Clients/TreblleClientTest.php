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
            ->auth()
            ->lookup(
                email: 'test@test.test',
            );

        $this->assertNotEmpty($response);
    }

    public function testGivenNameEmailAndPasswordToRegisterReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            '*' => Http::response(
                body: $this->fixture(
                    name: 'auth/register',
                ),
            ),
        ]);

        $response = $this
            ->client()
            ->auth()
            ->register(
                name: 'test_user',
                email: 'test@test.test',
                password: 'test_password',
            );

        $this->assertNotEmpty($response);

        $this->assertSame('hello@treblle.com', $response->email);
    }

    public function testGivenEmailAndPasswordToLoginReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            '*' => Http::response(
                body: $this->fixture(
                    name: 'auth/login',
                ),
            ),
        ]);

        $response = $this
            ->client()
            ->auth()
            ->login(
                email: 'test@test.test',
                password: 'test_password',
            );

        $this->assertNotEmpty($response);

        $this->assertSame('test_uuid', $response->uuid);
    }

    public function testGivenProjectNameAndUserUuidToCreateProjectReturnsRegisteredUserInfo(): void
    {
        TreblleClient::fake([
            '*' => Http::response(
                body: $this->fixture(
                    name: 'projects/create',
                ),
            ),
        ]);

        $response = $this
            ->client()
            ->projects()
            ->create(
                name: 'test_project',
                user: 'test_uuid',
            );

        $this->assertNotEmpty($response);

        $this->assertSame('12345', $response->apiID);
    }
}
