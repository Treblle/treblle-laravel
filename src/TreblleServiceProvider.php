<?php

namespace Treblle;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Treblle\Exceptions\InvalidConfig;

/**
 * Register the Treblle middlware
 */
class TreblleServiceProvider extends ServiceProvider {

    public function boot(Router $router) {

        if($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/treblle.php' => config_path('treblle.php'),
            ], 'config');
        }

        if(! config('treblle.api_key')) {
            throw InvalidConfig::apiKeyMissing();
        }

        if(! config('treblle.project_id')) {
            throw InvalidConfig::projectIdMissing();
        }

        $router->aliasMiddleware('treblle', Treblle::class);
    }

    public function register() {
        $this->mergeConfigFrom(__DIR__.'/../config/treblle.php', 'treblle');
    }
}
