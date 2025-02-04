<?php

declare(strict_types=1);

namespace Treblle\Laravel;

use function config;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Treblle\Laravel\Commands\SetupCommand;
use Illuminate\Foundation\Console\AboutCommand;
use Treblle\Laravel\Middlewares\TreblleMiddleware;
use Illuminate\Contracts\Container\BindingResolutionException;

final class TreblleServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/treblle.php' => config_path('treblle.php'),
            ], 'treblle-config');

            $this->commands([
                SetupCommand::class,
            ]);
        }

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('treblle', TreblleMiddleware::class);

        AboutCommand::add(
            section: 'Treblle',
            data: static fn (): array => [
                'Version' => 5.0,
                'URL' => config('treblle.url'),
                'Project ID' => config('treblle.project_id'),
                'API Key' => config('treblle.api_key'),
                'Ignored Environments' => config('treblle.ignore_environments'),
            ],
        );
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../config/treblle.php',
            key: 'treblle',
        );
    }
}
