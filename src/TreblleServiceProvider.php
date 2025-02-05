<?php

declare(strict_types=1);

namespace Treblle\Laravel;

use function config;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Treblle\Laravel\Clients\TreblleClient;
use Treblle\Laravel\Commands\SetupCommand;
use Illuminate\Foundation\Console\AboutCommand;
use Treblle\Laravel\Middlewares\TreblleMiddleware;
use Treblle\Laravel\Contracts\TreblleClientContract;
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

            //            $this->commands([
            //                SetupCommand::class,
            //            ]);
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        if (! isset($router->getMiddleware()['treblle'])) {
            $router->aliasMiddleware('treblle', TreblleMiddleware::class);
        }

        AboutCommand::add(
            section: 'Treblle',
            data: static fn (): array => [
                'Version' => 5.0,
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

        //        $this->app->bind(
        //            abstract: TreblleClientContract::class,
        //            concrete: static function () {
        //                $request = Http::baseUrl(
        //                    url: 'https://app-api.treblle.com/v1/',
        //                )->timeout(
        //                    seconds: 15,
        //                )->withHeaders([
        //                    'Accept' => 'application/json',
        //                    'Content-Type' => 'application/json',
        //                    'User-Agent' => 'TreblleSetupCommand/0.1',
        //                ]);
        //
        //                if (! empty(config('treblle.api_key'))) {
        //                    $request->withHeaders(
        //                        headers: [
        //                            'x-api-key' => (string) config('treblle.api_key'),
        //                        ]
        //                    );
        //                }
        //
        //                return new TreblleClient(
        //                    request: $request,
        //                );
        //            },
        //        );
    }
}
