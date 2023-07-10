<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Octane\Events\RequestReceived;
use Treblle\Clients\TreblleClient;
use Treblle\Commands\SetupCommand;
use Treblle\Contracts\TreblleClientContract;
use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Utils\Masking\FieldMasker;

final class TreblleServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/treblle.php' => config_path('treblle.php'),
            ], 'treblle-config');

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

            $uuid = Str::uuid()->toString();
            $this->app->bind('treblle-identifier', fn () => $uuid);

            $events->listen(RequestReceived::class, function () use ($uuid) {
                if (config('octane.server') === 'roadrunner') {
                    Cache::put($uuid, microtime(true));

                    return;
                }

                Cache::store('octane')->put($uuid, microtime(true));
            });
        }
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

        $this->app->bind(
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
                    $request->withHeaders(
                        headers: [
                            'x-api-key' => strval(config('treblle.api_key')),
                        ]
                    );
                }

                return new TreblleClient(
                    request: $request,
                );
            },
        );

        $this->app->bind(TreblleMiddleware::class);

        $fields = config('treblle.masked_fields');

        $this->app->singleton(
            abstract: FieldMasker::class,
            concrete: fn () => new FieldMasker(
                fields: is_array($fields) ? $fields : [],
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

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            FieldMasker::class,
            TreblleClientContract::class
        ];
    }
}
