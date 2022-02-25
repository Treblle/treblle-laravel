<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Treblle\Commands\SetupCommand;
use Treblle\Middlewares\TreblleMiddleware;

class TreblleServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
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
            $this->app['events']->listen(RequestReceived::class, function () {
                Cache::store('octane')->put('treblle_start', microtime(true));
            });
        }

        $this->app['router']->aliasMiddleware('treblle', TreblleMiddleware::class);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/treblle.php', 'treblle');
    }

    /**
     * Determine if server is running Octane
     */
    private function httpServerIsOctane(): bool
    {
        return (bool) isset($_ENV['OCTANE_DATABASE_SESSION_TTL']);
    }
}
