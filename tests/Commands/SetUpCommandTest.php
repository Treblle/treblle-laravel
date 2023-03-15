<?php

declare(strict_types=1);

namespace Treblle\Tests\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Treblle\Clients\TreblleClient;
use Treblle\Tests\TestCase;

class SetUpCommandTest extends TestCase
{
    public function testIfLookupRequestDoesntFindUserThenWeAllowUserToRegister(): void
    {
        Http::fake([
            'https://app-api.treblle.com/v1/auth/lookup' => Http::response(['user' => null]),
            'https://app-api.treblle.com/v1/auth/register' => Http::response(['user' => ['uuid' => 'test', 'api_key' => 'test_key']]),
            'https://app-api.treblle.com/v1/projects/store' => Http::response(['project' => ['api_id' => 'test_id']]),
        ]);

        $this->artisan('treblle:start')
             ->expectsQuestion("📧 What's your email address?", 'test@test.test')
             ->expectsQuestion("👨‍💻 What's your name?", 'Test')
             ->expectsQuestion('🔒 Enter a new password for your account', 'password')
             ->expectsQuestion("What's the name of your API project?", 'test')
             ->expectsOutputToContain('👏 Your project is ready! Add the following lines to your .env file and you are done!')
//            ->expectsOutputToContain('TREBLLE_API_KEY')
//            ->expectsOutputToContain('TREBLLE_PROJECT_ID')
             ->assertExitCode(Command::SUCCESS);
    }
}
