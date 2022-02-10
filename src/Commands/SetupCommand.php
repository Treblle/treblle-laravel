<?php

declare(strict_types=1);

namespace Treblle\Commands;

use Illuminate\Console\Command;
use Treblle\Clients\TreblleClient;

class SetupCommand extends Command
{
    protected $signature = 'treblle:start';

    protected $description = 'Get up an running with Treblle directly from the your console';

    /**
     * @see SetUpCommandTest::if_lookup_request_doesnt_find_user_then_we_allow_user_to_register()
     */
    public function handle()
    {
        $treblleClient = new TreblleClient();

        $this->info('🙏 Thank you for installing Treblle for Laravel! Let\'s get you setup!');
        $email = $this->ask('📧 What\'s your email address?');

        $lookupRequest = $treblleClient->authLookUp($email);

        if ($lookupRequest->failed()) {
            $this->error('We are having some problems at the moment. Please try again later!');

            return;
        }

        $lookupResponse = $lookupRequest->object();

        if ($lookupResponse->user !== null) {
            $this->info('Hello '.$lookupResponse->user->name.', it looks like you already have an Treblle account - let\'s log you in!');
            $password = $this->secret('🔒 What\'s your password?');

            $loginRequest = $treblleClient->login($email, $password);

            if ($loginRequest->failed()) {
                $this->error('Your login data is incorrect! Please try again and make sure you type in the correct data!');

                return;
            }

            $loginResponse = $loginRequest->object();

            $user = $loginResponse->user;
        } else {
            $this->info('Looks like you don\'t have a Treblle account yet. Let\'s create one quickly...');

            $name = $this->ask('👨‍💻 What\'s your name?');
            $password = $this->secret('🔒 Enter a new password for your account');

            $registerRequest = $treblleClient->register($name, $email, $password);

            if ($registerRequest->failed()) {
                $this->error('We are having some problems at the moment. Please try again later!');

                return;
            }

            $registerResponse = $registerRequest->object();

            $user = $registerResponse->user;
        }

        $this->info('🎉 Great. You\'r in. Now let\'s create a project on Treblle for our API.');

        $projectName = $this->ask('What\'s the name of your API project?');

        $projectRequest = $treblleClient->createProject($projectName, $user->uuid);

        if ($projectRequest->failed()) {
            $this->error('We are having some problems at the moment. Please try again later!');

            return;
        }

        $projectResponse = $projectRequest->object();

        $this->info('👏 Your project is ready! Add the following lines to your .env file and you are done!');
        $this->info('TREBLLE_API_KEY='.$user->api_key);
        $this->info('TREBLLE_PROJECT_ID='.$projectResponse->project->api_id);
    }
}
