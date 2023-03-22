<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Treblle\Clients\TreblleClient;
use Treblle\Commands\SetupCommand;
use Treblle\Contracts\TreblleClientContract;
use Treblle\Core\Contracts\Masking\MaskingContract;
use Treblle\Core\Masking\FieldMasker;
use Treblle\Middlewares\TreblleMiddleware;

final class TreblleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/treblle.php' => config_path('treblle.php'),
            ], 'config');

            $this->commands([
                SetupCommand::class,
            ]);
        }

        if ($this->httpServerIsOctane()) {
            /**
             * @var Dispatcher $events
             */
            $events = $this->app->make(
                abstract: Dispatcher::class,
            );

            $events->listen(RequestReceived::class, function () {
                Cache::store('octane')->put('treblle_start', microtime(true));
            });
        }

        /**
         * @var Router $router
         */
        $router = $this->app->make(
            abstract: Router::class,
        );

        $router->aliasMiddleware('treblle', TreblleMiddleware::class);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/treblle.php',
            key: 'treblle',
        );

        $this->app->singleton(
            abstract: TreblleClientContract::class,
            concrete: static function () {
                $request = Http::baseUrl(
                    url: 'https://app-api.treblle.com/v1/',
                )->timeout(
                    seconds: 15,
                )->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'TreblleSetupCommand/0.1',
                ]);

                if (! empty(config('treblle.api_key'))) {
                    $request->withToken(
                        token: strval(config('treblle.api_key')),
                    );
                }

                return new TreblleClient(
                    request: $request,
                );
            },
        );

        $this->app->singleton(
            abstract: MaskingContract::class,
            concrete: fn () => new FieldMasker(
                fields: (array) config('treblle.masked_fields'),
            ),
        );
    }

    /**
     * Determine if server is running Octane
     */
    private function httpServerIsOctane(): bool
    {
        return isset($_ENV['OCTANE_DATABASE_SESSION_TTL']);
    }
}
