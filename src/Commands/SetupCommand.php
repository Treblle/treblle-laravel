<?php

declare(strict_types=1);

namespace Treblle\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client as GuzzleClient;

class SetupCommand extends Command
{
    // COMMAND SETUP
    protected $signature = 'treblle:start';
    protected $description = 'Get up an running with Treblle directly from the your console';

    // API SETUP
    protected $apiKey = 'Y8fNzfhRab9FMeHXXbxT6Q0qqfmmTBKq';
    protected $baseUrl = 'https://treblle.com/api/v1/';

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle()
    {
        $this->info('🙏 Thank you for installing Treblle for Laravel! Let\'s get you setup!');
        $email = $this->ask('📧 What\'s your email address?');

        $lookupRequest = (new GuzzleClient())->request(
            'POST',
            $this->baseUrl . 'auth/lookup',
            [
                'http_errors' => false,
                'connect_timeout' => 3,
                'timeout' => 3,
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'User-Agent' => 'TreblleSetupCommand/0.1',
                ],
                'form_params' => [
                    'email' => $email,
                ],
            ]
        );

        if ($lookupRequest->getStatusCode() !== 200) {
            $this->error('We are having some problems at the moment. Please try again later!');

            return;
        }

        $lookup_response = json_decode($lookupRequest->getBody());

        if (!is_null($lookup_response->user)) {
            $this->info('Hello ' . $lookup_response->user->name . ', it looks like you already have an Treblle account - let\'s log you in!');
            $password = $this->secret('🔒 What\'s your password?');

            $login_request = (new GuzzleClient())->request(
                'POST',
                $this->baseUrl . 'auth/login',
                [
                    'http_errors' => false,
                    'connect_timeout' => 3,
                    'timeout' => 3,
                    'verify' => false,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'User-Agent' => 'TreblleSetupCommand/0.1',
                    ],
                    'form_params' => [
                        'email' => $email,
                        'password' => $password,
                    ],
                ]
            );

            if ($login_request->getStatusCode() !== 200) {
                $this->error('Your login data is incorrent! Please try again and make sure you type in the correct data!');

                return;
            }

            $login_response = json_decode($login_request->getBody());

            $user = $login_response->user;
        } else {
            $this->info('Looks like you don\'t have a Treblle account yet. Let\'s create one quickly...');

            $name = $this->ask('👨‍💻 What\'s your name?');
            $password = $this->secret('🔒 Enter a new password for your account');

            $register_request = (new GuzzleClient())->request(
                'POST',
                $this->baseUrl . 'auth/register',
                [
                    'http_errors' => false,
                    'connect_timeout' => 3,
                    'timeout' => 3,
                    'verify' => false,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'User-Agent' => 'TreblleSetupCommand/0.1',
                    ],
                    'form_params' => [
                        'email' => $email,
                        'password' => $password,
                        'name' => $name,
                    ],
                ]
            );

            if ($register_request->getStatusCode() !== 200) {
                $this->error('We are having some problems at the moment. Please try again later!');

                return;
            }

            $register_response = json_decode($register_request->getBody());

            $user = $register_response->user;
        }

        $this->info('🎉 Great. You\'r in. Now let\'s create a project on Treblle for our API.');
        $project_name = $this->ask('What\'s the name of your API project?');

        $project_request = (new GuzzleClient())->request(
            'POST',
            $this->baseUrl . 'projects/store',
            [
                'http_errors' => false,
                'connect_timeout' => 3,
                'timeout' => 3,
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'User-Agent' => 'TreblleSetupCommand/0.1',
                ],
                'form_params' => [
                    'name' => $project_name,
                    'user' => $user->uuid,
                ],
            ]
        );

        if ($project_request->getStatusCode() !== 200) {
            $this->error('We are having some problems at the moment. Please try again later!');

            return;
        }

        $project_response = json_decode($project_request->getBody());

        $this->info('👏 Your project is ready! Add the following lines to your .ENV file and you are done!');
        $this->info('TREBLLE_API_KEY=' . $user->api_key);
        $this->info('TREBLLE_PROJECT_ID=' . $project_response->project->api_id);
    }
}
