<?php

declare(strict_types=1);

namespace Treblle\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;
use Treblle\Contracts\TreblleClientContract;

final class SetupCommand extends Command
{
    protected $signature = 'treblle:start';

    protected $description = 'Get up an running with Treblle directly from the your console';

    /**
     * @see SetUpCommandTest::if_lookup_request_doesnt_find_user_then_we_allow_user_to_register()
     */
    public function handle(TreblleClientContract $treblleClient): int
    {
        $this->components->info(
            string: '🙏 Thank you for installing Treblle for Laravel! Let\'s get you setup!',
        );

        $email = $this->components->ask(
            question: '📧 What\'s your email address?',
        );

        try {
            $user = $treblleClient->auth()->lookup(
                email: strval($email),
            );
        } catch (Throwable) {
            $this->components->error(
                string: 'We are having some problems at the moment. Please try again later!',
            );

            return SymfonyCommand::FAILURE;
        }

        if (! empty($user->name)) {
            $this->components->info(
                string: "Hello, $user->name, it looks like you already have an Treblle account - let\'s log you in!",
            );

            $password = $this->secret(
                question: '🔒 What\'s your password?',
            );

            try {
                $login = $treblleClient->auth()->login(
                    email: strval($email),
                    password: strval($password),
                );
            } catch (Throwable) {
                $this->components->error(
                    string: 'Your login data is incorrect! Please try again and make sure you type in the correct data!',
                );

                return SymfonyCommand::FAILURE;
            }
        } else {
            $this->components->info(
                string: 'Looks like you don\'t have a Treblle account yet. Let\'s create one quickly...',
            );

            $name = $this->components->ask(
                question: '👨‍💻 What\'s your name?',
            );
            $password = $this->secret(
                question: '🔒 Enter a new password for your account',
            );

            try {
                $login = $treblleClient->auth()->register(
                    name: strval($name),
                    email: strval($email),
                    password: strval($password),
                );
            } catch (Throwable) {
                $this->components->error(
                    string: 'We are having some problems at the moment. Please try again later!',
                );

                return SymfonyCommand::FAILURE;
            }
        }

        $this->components->info(
            string: '🎉 Great. You\'r in. Now let\'s create a project on Treblle for our API.',
        );

        $projectName = $this->components->ask(
            question: 'What\'s the name of your API project?',
        );

        try {
            $project = $treblleClient->projects()->create(
                name: strval($projectName),
                user: (string) ($login->uuid),
            );
        } catch (Throwable) {
            $this->components->error(
                string: 'We are having some problems at the moment. Please try again later!',
            );

            return SymfonyCommand::FAILURE;
        }

        $this->components->info(
            string: '👏 Your project is ready! Add the following lines to your .env file and you are done!',
        );

        $apiKey = $login->apiKey;
        $projectId = $project->apiID;

        $this->components->bulletList(
            elements: [
                "TREBLLE_API_KEY=$apiKey",
                "TREBLLE_PROJECT_ID=$projectId",
            ],
        );

        return SymfonyCommand::SUCCESS;
    }
}
