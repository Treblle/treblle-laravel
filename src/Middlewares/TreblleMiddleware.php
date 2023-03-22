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
     * @throws RequestException
     * @throws Throwable
     * @throws JsonException
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        /**
         * @var Response|JsonResponse $response
         */
        $response = $next($request);

        /*
         * The terminate method is automatically called when the server supports the FastCGI protocol.
         * In the case the server does not support it, we fall back to manually calling the terminate method.
         *
         * @see https://laravel.com/docs/middleware#terminable-middleware
         */
        if (!str_contains(PHP_SAPI, 'fcgi') && !$this->httpServerIsOctane()) {
            if (!config('treblle.api_key') && config('treblle.project_id')) {
                return $response;
            }

            if (config('treblle.ignored_environments')) {
                if (in_array(config('app.env'), explode(',', (string) (config('treblle.ignored_environments'))))) {
                    return $response;
                }
            }

            /*
             * The terminate method is automatically called when the server supports the FastCGI protocol.
             * In the case the server does not support it, we fall back to manually calling the terminate method.
             *
             * @see https://laravel.com/docs/middleware#terminable-middleware
             */
            $this->terminate(
                request: $request,
                response: $response,
            );
        }

        return $response;
    }

    /**
     * @throws RequestException
     * @throws Throwable
     * @throws JsonException
     */
    public function terminate(Request $request, JsonResponse|Response $response): void
    {
        $data = $this->buildPayload(
            masker: $this->masker,
            request: $request,
            response: $response,
            loadTime: $this->getLoadTime(),
        );

        try {
            /**
             * @var Endpoint $url
             */
            $url = Arr::random(Endpoint::cases());

            $this->client->request()->send(
                method: Method::POST->value,
                url: $url->value,
                options: $data->jsonSerialize(),
            )->throw();
        } catch (Throwable $exception) {
            Log::error(
                message: 'Failed to process treblle request',
                context: $data->jsonSerialize(),
            );

            throw $exception;
        }
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

    /**
     * @throws JsonException
     */
    public function buildPayload(MaskingContract $masker, Request $request, JsonResponse|Response $response, float|int $loadTime): Data
    {
        $php = new PHP();

        $errors = [];

        $responseBody = $masker->mask(
            data: (array) json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR),
        );

        if (! empty($response->exception)) {
            $errors[] = new Error(
                source: 'onException',
                type: 'UNHANDLED_EXCEPTION',
                message: $response->exception->getMessage(),
                file: $response->exception->getFile(),
                line: $response->exception->getLine(),
            );
        }

        return new Data(
            server: new Server(
                ip: (string) ($request->server('SERVER_ADDR')),
                timezone: (string) (config('app.timezone')),
                software: (string) ($request->server('SERVER_SOFTWARE')),
                signature: (string) ($request->server('SERVER_SIGNATURE')),
                protocol: (string) ($request->server('SERVER_PROTOCOL')),
                os: new OS(
                    name: php_uname('s'),
                    release: php_uname('r'),
                    architecture: php_uname('m'),
                ),
                encoding: (string) ($request->server('HTTP_ACCEPT_ENCODING')),
            ),
            language: new Language(
                name: 'php',
                version: PHP_VERSION,
                expose_php: $php->get(
                    string: 'expose_php',
                ),
                display_errors: $php->get(
                    string: 'display_errors',
                ),
            ),
            request: new RequestObject(
                timestamp: Carbon::now('UTC')->format('Y-m-d H:i:s'),
                ip: (string) ($request->ip()),
                url: (string) ($request->fullUrl()),
                user_agent: (string) ($request->server('HTTP_USER_AGENT')),
                method: Method::from(
                    value: $request->method(),
                ),
                headers: $masker->mask(
                    data: collect($request->headers->all())->transform(
                        /* @phpstan-ignore-next-line  */
                        callback: fn ($item) => collect($item)->first(),
                    )->toArray(),
                ),
                body: $masker->mask(
                    data: $request->all(),
                ),
                raw: $masker->mask(
                    data: $request->all(),
                ),
            ),
            response: new ResponseObject(
                headers: $masker->mask(
                    data: collect($response->headers->all())->transform(
                        /* @phpstan-ignore-next-line  */
                        callback: fn ($item) => collect($item)->first(),
                    )->toArray(),
                ),
                code: $response->status(),
                size: strlen($response->content()),
                load_time: $loadTime,
                body: $responseBody,
            ),
            errors: $errors,
        );
    }
}
