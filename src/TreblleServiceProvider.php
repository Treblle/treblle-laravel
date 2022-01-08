<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Routing\Router;
use Treblle\Commands\SetupCommand;
use Illuminate\Support\ServiceProvider;
use Treblle\Middlewares\TreblleMiddleware;

class TreblleServiceProvider extends ServiceProvider
{
    public function boot(Router $router)
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/treblle.php' => config_path('treblle.php'),
            ], 'config');

            $this->commands([
                SetupCommand::class,
            ]);
        }

        $router->aliasMiddleware('treblle', TreblleMiddleware::class);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/treblle.php', 'treblle');
    }
}
