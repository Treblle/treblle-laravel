<?php

declare(strict_types=1);

namespace Treblle\Middlewares;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;
use Treblle\Core\Http\Endpoint;
use Treblle\Exceptions\ConfigurationException;
use Treblle\Exceptions\TreblleApiException;
use Treblle\Factories\DataFactory;
use Treblle\Treblle;

class TreblleMiddleware
{
    /**
     * @param DataFactory $factory
     */
    public function __construct(
        protected readonly DataFactory $factory,
    ) {}

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response|JsonResponse
     * @throws ConfigurationException|InvalidArgumentException|TreblleApiException
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        return $next($request);
    }

    /**
     * @param Request $request
     * @param JsonResponse|Response $response
     * @return void
     * @throws ConfigurationException|TreblleApiException|InvalidArgumentException
     */
    public function terminate(Request $request, JsonResponse|Response $response): void
    {
        Treblle::log(
            endpoint: Arr::random(Endpoint::cases()),
            data: $this->factory->make(
                request: $request,
                response: $response,
                loadTime: $this->getLoadTime(request: $request),
            )
        );
    }

    /**
     * @return float
     * @throws InvalidArgumentException
     */
    private function getLoadTime(Request $request): float
    {
        if (isset($_SERVER['LARAVEL_OCTANE'])) {
            if (config('octane.server') === 'swoole') {
                return (float) microtime(true) - floatval(Cache::store('octane')->get($request->fingerprint()));
            }

            return (float) microtime(true) - floatval(Cache::get($request->fingerprint()));
        }

        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return (float) microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        }

        return 0.0000;
    }
}
