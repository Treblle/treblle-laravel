<?php

declare(strict_types=1);

namespace Treblle\Laravel;

use function config;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\AboutCommand;
use Treblle\Laravel\Middlewares\TreblleMiddleware;
use Illuminate\Contracts\Container\BindingResolutionException;

final class TreblleServiceProvider extends ServiceProvider
{
    public const SDK_NAME = 'laravel';
    public const SDK_VERSION = 5.0;

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/treblle.php' => config_path('treblle.php'),
            ], 'treblle-config');
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        if (! isset($router->getMiddleware()['treblle'])) {
            $router->aliasMiddleware('treblle', TreblleMiddleware::class);
        }

        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        $events->listen('Laravel\Octane\Events\RequestReceived', function ($event): void {
            $event->request->attributes->set('treblle_request_started_at', microtime(true));
        });

        AboutCommand::add(
            section: 'Treblle',
            data: static fn (): array => [
                'Version' => self::SDK_VERSION,
                'URL' => config('treblle.url'),
                'Project ID' => config('treblle.project_id'),
                'API Key' => config('treblle.api_key'),
                'Ignored Environments' => config('treblle.ignored_environments'),
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
