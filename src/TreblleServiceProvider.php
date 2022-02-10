<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Support\ServiceProvider;
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
}
