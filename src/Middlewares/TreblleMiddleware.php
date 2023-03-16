<?php

declare(strict_types=1);

namespace Treblle\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Treblle\Jobs\ProcessRequest;

final class TreblleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /*
         * The terminate method is automatically called when the server supports the FastCGI protocol.
         * In the case the server does not support it, we fall back to manually calling the terminate method.
         *
         * @see https://laravel.com/docs/middleware#terminable-middleware
         */
        if (! str_contains(PHP_SAPI, 'fcgi') && ! $this->httpServerIsOctane()) {
            if (! config('treblle.api_key') && config('treblle.project_id')) {
                return $response;
            }

            if (config('treblle.ignored_environments')) {
                if (in_array(config('app.env'), explode(',', (string) (config('treblle.ignored_environments'))))) {
                    return $response;
                }
            }

            dispatch(new ProcessRequest(
                request: $request,
                response: $response,
                loadTime: $this->getLoadTime(),
            ));
        }

        return $response;
    }

    public function getLoadTime(): float
    {
        if ($this->httpServerIsOctane()) {
            return (float) microtime(true) - (float) (Cache::store('octane')->get('treblle_start'));
        }

        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return (float) microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        }

        return 0.0000;
    }

    /**
     * Determine if server is running Octane
     */
    private function httpServerIsOctane(): bool
    {
        return isset($_ENV['OCTANE_DATABASE_SESSION_TTL']);
    }
}
