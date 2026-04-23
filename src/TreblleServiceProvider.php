<?php

declare(strict_types=1);

namespace Treblle\Laravel;

use function config;
use GuzzleHttp\Client;
use Illuminate\Routing\Router;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Treblle\Laravel\Console\TestCommand;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\AboutCommand;
use Treblle\Laravel\Helpers\SensitiveDataMasker;
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
            ], 'treblle-config');

            $this->commands([TestCommand::class]);
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
                'API Key' => config('treblle.api_key') ? '****' . mb_substr((string) config('treblle.api_key'), -4) : 'Not set',
                'SDK Token' => config('treblle.sdk_token') ? '****' . mb_substr((string) config('treblle.sdk_token'), -4) : 'Not set',
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

        // Singleton masker: masked_fields never changes per-request, so we build
        // the lowercase field hash map once per process instead of every request.
        $this->app->singleton(SensitiveDataMasker::class, fn () => new SensitiveDataMasker(
            (array) config('treblle.masked_fields', [])
        ));

        // Scoped binding: one Treblle instance per request, bound to the current
        // Request object. Under Octane, scoped bindings are reset between requests.
        $this->app->scoped(Treblle::class, fn ($app) => new Treblle(
            $app->make(\Illuminate\Http\Request::class)
        ));

        // Persistent Guzzle client: reuses TCP connections and TLS sessions to
        // Treblle's ingress endpoint across requests instead of opening a new
        // connection every time.
        $this->app->singleton('treblle.http_client', fn () => new Client([
            'timeout'         => 3.0,
            'connect_timeout' => 3.0,
            'verify'          => false,
            'http_errors'     => false,
            'headers'         => ['Accept-Encoding' => 'gzip'],
        ]));
    }
}
