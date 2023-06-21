<?php

declare(strict_types=1);

namespace Treblle\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;
use Treblle\Contracts\TreblleClientContract;

final class SetupCommand extends Command
{
    protected $signature = 'treblle:start';

    protected $description = 'Get up an running with Treblle directly from the your console';

    protected const TREBLLE_API_KEY = 'TREBLLE_API_KEY';

    protected const TREBLLE_PROJECT_ID = 'TREBLLE_PROJECT_ID';

    /**
     * @param TreblleClientContract $treblleClient
     *
     * @return int
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
                email: (string) $email,
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
                    email: (string) $email,
                    password: (string) $password,
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
                    name: (string) $name,
                    email: (string) $email,
                    password: (string) $password,
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
                name: (string) $projectName,
                user: (string) ($login->uuid),
            );
        } catch (Throwable) {
            $this->components->error(
                string: 'We are having some problems at the moment. Please try again later!',
            );

            return SymfonyCommand::FAILURE;
        }

        $apiKey = $login->apiKey;
        $projectId = $project->apiID;

        $this->createEnvIfNeeded();
        $this->updateTreblleVariable(self::TREBLLE_API_KEY, $apiKey);
        $this->updateTreblleVariable(self::TREBLLE_PROJECT_ID, $projectId);

        $this->components->info(
            string: '👏 Your project is ready! Below lines has been added to your .env file.',
        );

        $this->components->bulletList(
            elements: [
                self::TREBLLE_API_KEY . "=$apiKey",
                self::TREBLLE_PROJECT_ID . "=$projectId",
            ],
        );

        return SymfonyCommand::SUCCESS;
    }

    protected function createEnvIfNeeded(): void
    {
        if (! File::exists($this->environmentPath())) {
            $this->components->info('Environment variable file (.env) not found. Creating one.');

            exec('cp .env.example ' . $this->environmentPath());
            exec('echo "'. self::TREBLLE_API_KEY .'=">>' . $this->environmentPath());
            exec('echo "'. self::TREBLLE_PROJECT_ID .'=">>' . $this->environmentPath());
        }

        if (! $this->laravel['config']->get('treblle.api_key')) {
            exec('echo "'. self::TREBLLE_API_KEY .'=">>' . $this->environmentPath());
        }

        if (! $this->laravel['config']->get('treblle.project_id')) {
            exec('echo "'. self::TREBLLE_PROJECT_ID .'=">>' . $this->environmentPath());
        }
    }

    protected function updateTreblleVariable(string $key, string|null $value): void
    {
        $envContents = File::get($this->environmentPath());

        $envKeyValueToReplace = collect(explode(PHP_EOL, $envContents))
            ->filter(fn ($variable) => str_contains($variable, $key))
            ->first();

        File::put(
            $this->environmentPath(),
            str_replace($envKeyValueToReplace ?? '', "$key=$value", $envContents)
        );

        $this->callSilently('config:clear');
    }

    protected function environmentPath(): string
    {
        if (method_exists($this->laravel, 'environmentFilePath')) {
            return $this->laravel->environmentFilePath();
        }

        return $this->laravel->basePath('.env');
    }
}
