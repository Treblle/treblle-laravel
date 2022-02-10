<?php

declare(strict_types=1);

namespace Treblle\Test\Commands;

use Illuminate\Support\Facades\Http;
use Treblle\Clients\TreblleClient;
use Treblle\Test\TestCase;

class SetUpCommandTest extends TestCase
{
    /** @test */
    public function if_lookup_request_doesnt_find_user_then_we_allow_user_to_register(): void
    {
        TreblleClient::fake(
            [
                TreblleClient::BASE_URL . 'auth/lookup' => Http::response(['user' => null]),
                TreblleClient::BASE_URL . 'auth/register' => Http::response(['user' => ['uuid' => 'test', 'api_key' => 'test_key']]),
                TreblleClient::BASE_URL . 'projects/store' => Http::response(['project' => ['api_id' => 'test_id']]),
            ]
        );

        $this
            ->artisan('treblle:start')
            ->expectsQuestion('📧 What\'s your email address?', 'test@test.test')
            ->expectsQuestion('👨‍💻 What\'s your name?', 'Test')
            ->expectsQuestion('🔒 Enter a new password for your account', 'password')
            ->expectsQuestion('What\'s the name of your API project?', 'test')
            ->expectsOutput('👏 Your project is ready! Add the following lines to your .env file and you are done!')
            ->expectsOutput('TREBLLE_API_KEY=test_key')
            ->expectsOutput('TREBLLE_PROJECT_ID=test_id')
            ->assertExitCode(0);
    }
}
