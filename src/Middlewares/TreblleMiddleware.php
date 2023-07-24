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
        self::$start = \microtime(true);
        self::$project = $projectId;

        return $next($request);
    }

    /**
     * @param Request $request
     * @param JsonResponse|Response $response
     * @return void
     * @throws ConfigurationException|TreblleApiException
     */
    public function terminate(Request $request, JsonResponse|Response $response): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->collectData($request, $response);
            return;
        }

        $pid = pcntl_fork();

        if ($this->isUnableToForkProcess($pid)) {
            $this->collectData($request, $response);
            return;
        }

        if ($this->isChildProcess($pid)) {
            $this->collectData($request, $response);
            $this->killProcessWithId((int)getmypid());
        }
    }

    /**
     * @throws ConfigurationException |TreblleApiException
     */
    protected function collectData(Request $request, JsonResponse|Response $response): void
    {
        Treblle::log(
            endpoint: Endpoint::PUNISHER,
            data: $this->factory->make(
                request: $request,
                response: $response,
                loadTime: \microtime(true) - self::$start,
            ),
            projectId: self::$project ?? (string) \config('treblle.project_id'),
        );
    }

    private function isChildProcess(int $pid): bool
    {
        return $pid === 0;
    }

    private function isUnableToForkProcess(int $pid): bool
    {
        return $pid === -1;
    }

    private function killProcessWithId(int $pid): void
    {
        strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? exec("taskkill /F /T /PID $pid") : exec("kill -9 $pid");
    }
}
