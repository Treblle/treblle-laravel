<?php

declare(strict_types=1);

namespace Treblle\Middlewares;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public static ?string $project = null;

    public function __construct(
        protected readonly DataFactory $factory,
    ) {
    }

    public function handle(Request $request, Closure $next, ?string $projectId = null): Response|JsonResponse|SymfonyResponse
    {
        self::$start = microtime(true);
        self::$project = $projectId;

        if (! $request->headers->has('X-TREBLLE-TRACE-ID')) {
            $request->headers->add([
                'X-TREBLLE-TRACE-ID' => $id = Str::uuid(),
            ]);
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        $response->headers->add([
            'X-TREBLLE-TRACE-ID' => $request->headers->get('X-TREBLLE-TRACE-ID'),
        ]);

        return $response;
    }

    /**
     * @throws ConfigurationException|TreblleApiException
     */
    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        if (strlen((string) $response->getContent()) > 2 * 1024 * 1024) {
            if (! app()->environment('production')) {
                Log::error(
                    message: 'Cannot send response over 2MB to Treblle.',
                    context: [
                        'url' => $request->fullUrl(),
                        'date' => now()->toDateTimeString(),
                    ]
                );
            }

            return;
        }

        Treblle::log(
            endpoint: Arr::random(Endpoint::cases()),
            data: $this->factory->make(
                request: $request,
                response: $response,
                loadTime: microtime(true) - self::$start,
            ),
            projectId: self::$project ?? (string) config('treblle.project_id'),
        );
    }
}
