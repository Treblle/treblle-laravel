<?php

declare(strict_types=1);

namespace Treblle\Middlewares;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Treblle\Exceptions\ConfigurationException;
use Treblle\Exceptions\TreblleApiException;
use Treblle\Factories\DataFactory;
use Treblle\Http\Endpoint;
use Treblle\Treblle;

use function config;
use function microtime;

class TreblleMiddleware
{
    public static float $start = 0.00;

    public static null|string $project = null;

    /**
     * @param DataFactory $factory
     */
    public function __construct(
        protected readonly DataFactory $factory,
    ) {
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @param string|null $projectId
     * @return Response|JsonResponse|SymfonyResponse
     */
    public function handle(Request $request, Closure $next, string $projectId = null): Response|JsonResponse|SymfonyResponse
    {
        self::$start = microtime(true);
        self::$project = $projectId;

        return $next($request);
    }

    /**
     * @param Request $request
     * @param JsonResponse|Response|SymfonyResponse $response
     * @return void
     * @throws ConfigurationException|TreblleApiException
     */
    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        Treblle::log(
            endpoint: Endpoint::PUNISHER,
            data: $this->factory->make(
                request: $request,
                response: $response,
                loadTime: microtime(true) - self::$start,
            ),
            projectId: self::$project ?? (string) config('treblle.project_id'),
        );
    }
}
