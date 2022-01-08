<?php

declare(strict_types=1);

namespace Treblle\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class SetupCommand extends Command
{
    protected $signature = 'treblle:start';

    protected $description = 'Get up an running with Treblle directly from the your console';

    /* @var GuzzleClient */
    private $guzzleClient;

    private const BASE_URL = 'https://treblle.com/api/v1/';
    private const API_KEY = 'Y8fNzfhRab9FMeHXXbxT6Q0qqfmmTBKq';

    public function handle()
    {
        $this->guzzleClient = new GuzzleClient();

        $this->info('🙏 Thank you for installing Treblle for Laravel! Let\'s get you setup!');
        $email = $this->ask('📧 What\'s your email address?');

        $lookupRequest = $this->getAPIResponse('auth/lookup', ['email' => $email]);

        if ($lookupRequest->getStatusCode() !== Response::HTTP_OK) {
            $this->error('We are having some problems at the moment. Please try again later!');

            return;
        }

        $lookupResponse = json_decode($lookupRequest->getBody()->getContents());

        if ($lookupResponse->user !== null) {
            $this->info('Hello ' . $lookupResponse->user->name . ', it looks like you already have an Treblle account - let\'s log you in!');
            $password = $this->secret('🔒 What\'s your password?');

            $loginRequest = $this->getAPIResponse('auth/login', ['email' => $email, 'password' => $password]);

            if ($loginRequest->getStatusCode() !== Response::HTTP_OK) {
                $this->error('Your login data is incorrect! Please try again and make sure you type in the correct data!');

                return;
            }

            $loginResponse = json_decode($loginRequest->getBody()->getContents());

            $user = $loginResponse->user;
        } else {
            $this->info('Looks like you don\'t have a Treblle account yet. Let\'s create one quickly...');

            $name = $this->ask('👨‍💻 What\'s your name?');
            $password = $this->secret('🔒 Enter a new password for your account');

            $registerRequest = $this->getAPIResponse('auth/register', ['name' => $name, 'email' => $email, 'password' => $password]);

            if ($registerRequest->getStatusCode() !== Response::HTTP_OK) {
                $this->error('We are having some problems at the moment. Please try again later!');

                return;
            }

            $registerResponse = json_decode($registerRequest->getBody()->getContents());

            $user = $registerResponse->user;
        }

        $this->info('🎉 Great. You\'r in. Now let\'s create a project on Treblle for our API.');

        $projectName = $this->ask('What\'s the name of your API project?');

        $projectRequest = $this->getAPIResponse('projects/store', ['name' => $projectName, 'user' => $user->uuid]);

        if ($projectRequest->getStatusCode() !== Response::HTTP_OK) {
            $this->error('We are having some problems at the moment. Please try again later!');

            return;
        }

        $projectResponse = json_decode($projectRequest->getBody()->getContents());

        $this->info('👏 Your project is ready! Add the following lines to your .env file and you are done!');
        $this->info('TREBLLE_API_KEY=' . $user->api_key);
        $this->info('TREBLLE_PROJECT_ID=' . $projectResponse->project->api_id);
    }

    private function getAPIResponse(string $url, array $formParams, string $method = 'POST'): ResponseInterface
    {
        return $this->guzzleClient->request(
            $method,
            self::BASE_URL . $url,
            [
                'http_errors' => false,
                'connect_timeout' => 3,
                'timeout' => 3,
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . self::API_KEY,
                    'User-Agent' => 'TreblleSetupCommand/0.1',
                ],
                'form_params' => $formParams,
            ]
        );
    }
}
