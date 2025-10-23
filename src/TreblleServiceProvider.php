<?php

declare(strict_types=1);

namespace Treblle\Laravel;

use function config;
use Illuminate\Routing\Router;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\AboutCommand;
use Treblle\Laravel\Middlewares\TreblleMiddleware;
use Treblle\Laravel\Middlewares\TreblleEarlyMiddleware;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * Treblle Service Provider for Laravel.
 *
 * This service provider registers Treblle middleware, publishes configuration,
 * integrates with Laravel Octane for accurate request timing, and provides
 * integration with the Laravel `about` command.
 *
 * @package Treblle\Laravel
 */
final class TreblleServiceProvider extends ServiceProvider
{
    /**
     * The name of the SDK for identification in Treblle platform.
     */
    public const SDK_NAME = 'laravel';

    /**
     * The current version of the Treblle Laravel SDK.
     */
    public const SDK_VERSION = 6.0;

    /**
     * Bootstrap any application services.
     *
     * Registers middleware aliases, publishes configuration, sets up Laravel Octane
     * integration for request timing, and adds Treblle information to the `about` command.
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/treblle.php' => config_path('treblle.php'),
            ], ['config', 'treblle-config']);
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        if (! isset($router->getMiddleware()['treblle'])) {
            $router->aliasMiddleware('treblle', TreblleMiddleware::class);
        }

        if (! isset($router->getMiddleware()['treblle.early'])) {
            $router->aliasMiddleware('treblle.early', TreblleEarlyMiddleware::class);

            /** @var Kernel $kernel */
            $kernel = $this->app->make(Kernel::class);
            $kernel->prependToMiddlewarePriority(TreblleEarlyMiddleware::class);
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
                'API Key' => config('treblle.api_key'),
                'SDK Token' => config('treblle.sdk_token'),
                'Ignored Environments' => config('treblle.ignored_environments'),
            ],
        );
    }

    /**
     * Register any application services.
     *
     * Merges the package configuration with the application's published configuration.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../config/treblle.php',
            key: 'treblle',
        );
    }
}
