<?php

declare(strict_types=1);

namespace Treblle\Providers;

use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Treblle\Clients\TreblleClient;
use Treblle\Commands\SetupCommand;
use Treblle\Contracts\TreblleClientContract;
use Treblle\Middlewares\TreblleMiddleware;

final class TreblleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                paths: [
                    __DIR__.'/../../config/treblle.php' => config_path('treblle.php'),
                ],
                groups: 'config',
            );

            $this->commands(
                commands: [SetupCommand::class],
            );
        }

        if ($this->httpServerIsOctane()) {
            /**
             * @var Dispatcher $event
             */
            $event = $this->app->make(
                abstract: 'event',
            );
            $event->listen(
                events: RequestReceived::class,
                listener: static fn (): bool => Cache::store(
                    name: 'octane',
                )->put(
                    key: 'treblle_start',
                    value: microtime(true),
                ),
            );
        }

        /**
         * @var Router $router
         */
        $router = $this->app->make(
            abstract: 'router',
        );
        $router->aliasMiddleware(
            name: 'treblle',
            class: TreblleMiddleware::class,
        );
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../../config/treblle.php',
            key: 'treblle',
        );

        $this->app->singleton(
            abstract: TreblleClientContract::class,
            concrete: fn (): TreblleClientContract => new TreblleClient(
                request: Http::baseUrl(
                    url: 'https://app-api.treblle.com/v1/',
                )->timeout(
                    seconds: 15,
                )->withToken(
                    token: 'Y8fNzfhRab9FMeHXXbxT6Q0qqfmmTBKq',
                ),
            ),
        );
    }

    private function httpServerIsOctane(): bool
    {
        return isset($_ENV['OCTANE_DATABASE_SESSION_TTL']);
    }
}
