<?php

declare(strict_types=1);

namespace Treblle\Tests\Commands;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Treblle\Clients\TreblleClient;
use Treblle\Core\Http\Endpoint;
use Treblle\Tests\PackageTestCase;

class SetUpCommandTest extends PackageTestCase
{
    public function testIfLookupRequestDoesntFindUserThenWeAllowUserToRegister(): void
    {
        TreblleClient::fake([
            Arr::random(Endpoint::cases())->value.'auth/lookup' => Http::response(['user' => null]),
            Arr::random(Endpoint::cases())->value.'auth/register' => Http::response(['user' => ['uuid' => 'test', 'api_key' => 'test_key']]),
            Arr::random(Endpoint::cases())->value.'projects/store' => Http::response(['project' => ['api_id' => 'test_id']]),
        ]);

        $this->artisan('treblle:start')
             ->expectsQuestion("📧 What's your email address?", 'test@test.test')
             ->expectsQuestion("👨‍💻 What's your name?", 'Test')
             ->expectsQuestion('🔒 Enter a new password for your account', 'password')
             ->expectsQuestion("What's the name of your API project?", 'test')
             ->expectsOutput('👏 Your project is ready! Add the following lines to your .env file and you are done!')
             ->expectsOutput('TREBLLE_API_KEY=test_key')
             ->expectsOutput('TREBLLE_PROJECT_ID=test_id')
             ->assertExitCode(0);
    }
}
