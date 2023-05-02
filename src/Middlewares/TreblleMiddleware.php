<?php

declare(strict_types=1);

namespace Treblle\Middlewares;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;
use Treblle\Contracts\TreblleClientContract;
use Treblle\Core\Contracts\Masking\MaskingContract;
use Treblle\Core\DataObjects\Data;
use Treblle\Core\DataObjects\Error;
use Treblle\Core\DataObjects\Language;
use Treblle\Core\DataObjects\OS;
use Treblle\Core\DataObjects\Request as RequestObject;
use Treblle\Core\DataObjects\Response as ResponseObject;
use Treblle\Core\DataObjects\Server;
use Treblle\Core\Http\Endpoint;
use Treblle\Core\Http\Method;
use Treblle\Core\Support\PHP;

final class TreblleMiddleware
{
    public function __construct(
        private readonly TreblleClientContract $client,
        private readonly MaskingContract $masker,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        return $next($request);
    }

    /**
     * @throws RequestException
     * @throws Throwable
     * @throws JsonException
     */
    public function terminate(Request $request, JsonResponse|Response $response): void
    {
        $data = $this->buildPayload(
            $this->masker,
            $request,
            $response,
            $this->getLoadTime(),
        );

        try {
            /**
             * @var Endpoint $url
             */
            $url = Arr::random(Endpoint::cases());

            $this->client->request()->send(
                Method::POST->value,
                $url->value,
                $data->jsonSerialize(),
            )->throw();
        } catch (Throwable $exception) {
            Log::error(
                'Failed to process treblle request',
                $data->jsonSerialize(),
            );

            throw $exception;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getLoadTime(): float
    {
        if ($this->httpServerIsOctane()) {
            if (config('octane.server') === 'swoole') {
                return (float) microtime(true) - (float) (Cache::store('octane')->get('treblle_start'));
            }

            return (float) microtime(true) - (float) (Cache::get('treblle_start'));
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

    /**
     * @throws JsonException
     */
    public function buildPayload(MaskingContract $masker, Request $request, JsonResponse|Response $response, float|int $loadTime): Data
    {
        $php = new PHP();

        $errors = [];

        try {
            $responseBody = $masker->mask(
                (array) json_decode(
                    $response->content(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
            );
        } catch (Throwable) {
            $responseBody = '{}';
        }

        if (! empty($response->exception)) {
            $errors[] = new Error(
                'onException',
                'UNHANDLED_EXCEPTION',
                $response->exception->getMessage(),
                $response->exception->getFile(),
                $response->exception->getLine(),
            );
        }

        return new Data(
            new Server(
                (string) ($request->server('SERVER_ADDR')),
                (string) (config('app.timezone')),
                (string) ($request->server('SERVER_SOFTWARE')),
                (string) ($request->server('SERVER_SIGNATURE')),
                (string) ($request->server('SERVER_PROTOCOL')),
                new OS(
                    php_uname('s'),
                    php_uname('r'),
                    php_uname('m'),
                ),
                (string) ($request->server('HTTP_ACCEPT_ENCODING')),
            ),
            new Language(
                'php',
                PHP_VERSION,
                $php->get(
                    'expose_php',
                ),
                $php->get(
                    'display_errors',
                ),
            ),
            new RequestObject(
                Carbon::now('UTC')->format('Y-m-d H:i:s'),
                (string) $request->ip(),
                $request->fullUrl(),
                (string) $request->userAgent(),
                Method::from(
                    $request->method(),
                ),
                $masker->mask(
                    collect($request->headers->all())->transform(
                        /* @phpstan-ignore-next-line  */
                        fn ($item) => collect($item)->first(),
                    )->toArray(),
                ),
                $masker->mask(
                    $request->all(),
                ),
                $masker->mask(
                    $request->all(),
                ),
            ),
            new ResponseObject(
                $masker->mask(
                    collect($response->headers->all())->transform(
                        /* @phpstan-ignore-next-line  */
                        fn ($item) => collect($item)->first(),
                    )->toArray(),
                ),
                $response->status(),
                strlen($response->content()),
                $loadTime,
                $responseBody,
            ),
            $errors,
        );
    }
}
