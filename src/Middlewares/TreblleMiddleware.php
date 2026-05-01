<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Treblle\Laravel\Jobs\SendTreblleData;
use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Laravel\DataTransferObject\Data;
use Treblle\Laravel\DataTransferObject\Error;
use Treblle\Laravel\Helpers\SensitiveDataMasker;
use Treblle\Laravel\DataProviders\ServerDataProvider;
use Treblle\Laravel\DataProviders\LanguageDataProvider;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;
use Treblle\Laravel\DataProviders\InMemoryErrorDataProvider;
use Treblle\Laravel\DataProviders\LaravelRequestDataProvider;
use Treblle\Laravel\DataProviders\LaravelResponseDataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Treblle Monitoring Middleware for Laravel Applications.
 *
 * Captures and sends API request/response data to Treblle for monitoring and
 * observability. Uses Laravel's terminable middleware pattern to transmit data
 * after the response has been sent to the client, ensuring zero impact on
 * response times.
 *
 * All configuration is read fresh from config() on each request to ensure
 * correctness under Laravel Octane, where middleware instances are reused
 * across requests.
 *
 * @package Treblle\Laravel\Middlewares
 */
final class TreblleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Validates configuration, stores any per-route API key override in request
     * attributes (never mutates global config), and passes the request through.
     *
     * IMPORTANT: This middleware NEVER throws exceptions. Missing configuration
     * results in silent failure (with optional debug logging) to ensure Treblle
     * never breaks your API.
     *
     * @param string|null $apiKey Optional API key override for this specific route
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string|null $apiKey = null)
    {
        if (! (bool) config('treblle.enable', true)) {
            return $next($request);
        }

        if ($this->isIgnoredEnvironment()) {
            return $next($request);
        }

        if ($this->isIgnoredMethod($request)) {
            return $next($request);
        }

        // Store per-route API key override in request attributes to avoid
        // mutating global config state (critical for Octane correctness)
        if (null !== $apiKey) {
            $request->attributes->set('treblle_api_key_override', $apiKey);
        }

        if (! config('treblle.sdk_token')) {
            $this->logConfigError('TREBLLE_SDK_TOKEN is not configured. Treblle monitoring disabled.');

            return $next($request);
        }

        if (! ($apiKey ?? config('treblle.api_key'))) {
            $this->logConfigError('TREBLLE_API_KEY is not configured. Treblle monitoring disabled.');

            return $next($request);
        }

        return $next($request);
    }

    /**
     * Transmit data to Treblle after the response has been sent to the client.
     *
     * @param JsonResponse|Response|SymfonyResponse $response
     */
    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        if ($this->isIgnoredEnvironment()) {
            return;
        }

        if ($this->isIgnoredMethod($request)) {
            return;
        }

        $apiKey = $request->attributes->get('treblle_api_key_override') ?? (string) config('treblle.api_key');

        if (! config('treblle.sdk_token') || ! $apiKey) {
            return;
        }

        if ((bool) config('treblle.queue.enabled', false)) {
            $this->dispatchToQueue($request, $response, $apiKey);

            return;
        }

        $this->sendSynchronously($request, $response, $apiKey);
    }

    /**
     * Dispatch a queue job to send Treblle data asynchronously.
     */
    private function dispatchToQueue(Request $request, JsonResponse|Response|SymfonyResponse $response, string $apiKey): void
    {
        try {
            $job = new SendTreblleData($this->buildPayloadData($request, $response, $apiKey));

            if ($connection = config('treblle.queue.connection')) {
                $job->onConnection($connection);
            }

            $job->onQueue((string) config('treblle.queue.queue', 'default'));

            dispatch($job);
        } catch (Throwable $e) {
            $this->logError('Treblle queue dispatch failed', $e);
        }
    }

    /**
     * Send Treblle data synchronously by running the job inline.
     *
     * Reuses the job's handle() method so transmission logic lives in one place.
     */
    private function sendSynchronously(Request $request, JsonResponse|Response|SymfonyResponse $response, string $apiKey): void
    {
        try {
            $job = new SendTreblleData($this->buildPayloadData($request, $response, $apiKey));
            $job->handle();
        } catch (Throwable $e) {
            $this->logError('Treblle synchronous transmission failed', $e);
        }
    }

    /**
     * Build the serializable payload DTO from the current request/response.
     *
     * Shared between the queue and sync paths to avoid duplication.
     */
    private function buildPayloadData(
        Request $request,
        JsonResponse|Response|SymfonyResponse $response,
        string $apiKey,
    ): TrebllePayloadData {
        $fieldMasker = app(SensitiveDataMasker::class);
        $errorProvider = new InMemoryErrorDataProvider();
        $requestProvider = new LaravelRequestDataProvider($fieldMasker, $request);
        $responseProvider = new LaravelResponseDataProvider($fieldMasker, $request, $response, $errorProvider);

        if (! empty($response->exception)) {
            $errorProvider->addError(new Error(
                message: $response->exception->getMessage(),
                file: $response->exception->getFile(),
                line: $response->exception->getLine(),
                source: 'onException',
                type: 'UNHANDLED_EXCEPTION',
            ));
        }

        $metadata = array_merge(
            (array) config('treblle.metadata', []),
            (array) $request->attributes->get('treblle_metadata', []),
        );

        return new TrebllePayloadData(
            apiKey: $apiKey,
            sdkToken: (string) config('treblle.sdk_token'),
            sdkName: TreblleServiceProvider::SDK_NAME,
            sdkVersion: TreblleServiceProvider::SDK_VERSION,
            data: new Data(
                server: (new ServerDataProvider())->getServer(),
                language: (new LanguageDataProvider())->getLanguage(),
                request: $requestProvider->getRequest(),
                response: $responseProvider->getResponse(),
                errors: $errorProvider->getErrors(),
                metadata: $metadata,
            ),
        );
    }

    /**
     * Determine if the request method should be ignored.
     */
    private function isIgnoredMethod(Request $request): bool
    {
        $ignored = array_map('strtoupper', (array) config('treblle.ignored_methods', ['HEAD', 'OPTIONS']));

        return in_array(mb_strtoupper($request->method()), $ignored, true);
    }

    /**
     * Determine if the current environment should be ignored.
     */
    private function isIgnoredEnvironment(): bool
    {
        $ignored = array_map('trim', explode(',', config('treblle.ignored_environments', '') ?? ''));

        return in_array(app()->environment(), $ignored, true);
    }

    private function logConfigError(string $message): void
    {
        if (config('treblle.debug')) {
            logger()->warning('[Treblle] ' . $message);
        }
    }

    private function logError(string $message, Throwable $exception): void
    {
        if (config('treblle.debug')) {
            logger()->error('[Treblle] ' . $message, [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }
    }
}
