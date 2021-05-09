<?php

namespace Treblle\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client as GuzzleClient;

class SetupCommand extends Command {

    // COMMAND SETUP
    protected $signature = 'treblle:start';
    protected $description = 'Get up an running with Treblle directly from the your console';
    
    // API SETUP
    protected $api_key = 'Y8fNzfhRab9FMeHXXbxT6Q0qqfmmTBKq';
    protected $base_url = 'https://treblle.com/api/v1/';

    public function handle() {

        $guzzle = new GuzzleClient;
        $user = [];

        $this->info('🙏 Thank you for installing Treblle for Laravel! Let\'s get you setup!');
        $email = $this->ask('📧 What\'s your email address?');

        $lookup_request = $guzzle->request(
            'POST', 
            $this->base_url.'auth/lookup',
            [
                'http_errors' => false,
                'connect_timeout' => 3,
                'timeout' => 3,
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer '.$this->api_key,
                    'User-Agent' => 'TreblleSetupCommand/0.1'     
                ],
                'form_params' => [
                    'email' => $email
                ]
            ]
        );

        if($lookup_request->getStatusCode() != 200) {
            $this->error('We are having some problems at the moment. Please try again later!');
            return;
        }

        $lookup_response = json_decode($lookup_request->getBody());

        if(!is_null($lookup_response->user)) {
            
            $this->info('Hello '.$lookup_response->user->name.', it looks like you already have an Treblle account - let\'s log you in!');
            $password = $this->secret('🔒 What\'s your password?');
               
           $login_request = $guzzle->request(
                'POST', 
                $this->base_url.'auth/login',
                [
                    'http_errors' => false,
                    'connect_timeout' => 3,
                    'timeout' => 3,
                    'verify' => false,
                    'headers' => [
                        'Authorization' => 'Bearer '.$this->api_key,
                        'User-Agent' => 'TreblleSetupCommand/0.1'      
                    ],
                    'form_params' => [
                        'email' => $email,
                        'password' => $password
                    ]
                ]
            );

            if($login_request->getStatusCode() != 200) {
                $this->error('Your login data is incorrent! Please try again and make sure you type in the correct data!');
                return;
            }

            $login_response = json_decode($login_request->getBody());

            $user = $login_response->user;

        } else {
            $this->info('Looks like you don\'t have a Treblle account yet. Let\'s create one quickly...');

            $name = $this->ask('👨‍💻 What\'s your name?');
            $password = $this->secret('🔒 Enter a new password for your account');

            $register_request = $guzzle->request(
                'POST', 
                $this->base_url.'auth/register',
                [
                    'http_errors' => false,
                    'connect_timeout' => 3,
                    'timeout' => 3,
                    'verify' => false,
                    'headers' => [
                        'Authorization' => 'Bearer '.$this->api_key,  
                        'User-Agent' => 'TreblleSetupCommand/0.1'    
                    ],
                    'form_params' => [
                        'email' => $email,
                        'password' => $password,
                        'name' => $name
                    ]
                ]
            );

            if($register_request->getStatusCode() != 200) {
                $this->error('We are having some problems at the moment. Please try again later!');
                return;
            }

            $register_response = json_decode($register_request->getBody());

            $user = $register_response->user;
        }

        $this->info('🎉 Great. You\'r in. Now let\'s create a project on Treblle for our API.');
        $project_name = $this->ask('What\'s the name of your API project?');

        $project_request = $guzzle->request(
            'POST', 
            $this->base_url.'projects/store',
            [
                'http_errors' => false,
                'connect_timeout' => 3,
                'timeout' => 3,
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer '.$this->api_key,
                    'User-Agent' => 'TreblleSetupCommand/0.1'   
                ],
                'form_params' => [
                    'name' => $project_name,
                    'user' => $user->uuid
                ]
            ]
        );

        if($project_request->getStatusCode() != 200) {
            $this->error('We are having some problems at the moment. Please try again later!');
            return;
        }

        $project_response = json_decode($project_request->getBody());

        $this->info('👏 Your project is ready! Add the following lines to your .ENV file and you are done!');
        $this->info('TREBLLE_API_KEY='.$user->api_key);
        $this->info('TREBLLE_PROJECT_ID='.$project_response->project->api_id);

    }

}
