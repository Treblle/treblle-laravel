<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Treblle\Php\Factory\TreblleFactory;
use Treblle\Php\DataTransferObject\Error;
use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Php\Helpers\SensitiveDataMasker;
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Php\DataProviders\InMemoryErrorDataProvider;
use Treblle\Laravel\DataProviders\LaravelRequestDataProvider;
use Treblle\Laravel\DataProviders\LaravelResponseDataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Treblle Monitoring Middleware for Laravel Applications.
 *
 * This middleware captures and sends API request/response data to Treblle for monitoring
 * and observability. It uses Laravel's terminable middleware pattern to transmit data
 * after the response has been sent to the client, ensuring zero impact on response times.
 *
 * Features:
 * - Environment-based toggle (enable/disable monitoring)
 * - Ignored environments support (dev, test, etc.)
 * - Dynamic API key override per route
 * - Sensitive data masking
 * - Exception tracking
 * - Non-blocking data transmission
 *
 * @package Treblle\Laravel\Middlewares
 */
final class TreblleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Validates Treblle configuration (SDK token and API key) and optionally
     * overrides the API key for specific routes. The middleware respects the
     * `enable` flag and `ignored_environments` configuration to determine
     * whether monitoring should be active.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the pipeline
     * @param string|null $apiKey Optional API key override for this specific route
     *
     * @return mixed The response from the next middleware
     * @throws TreblleException If SDK token or API key is not configured
     */
    public function handle(Request $request, Closure $next, string|null $apiKey = null)
    {
        if (! config('treblle.enable')) {
            return $next($request);
        }

        $ignoredEnvironments = array_map('trim', explode(',', config('treblle.ignored_environments', '') ?? ''));

        if (in_array(app()->environment(), $ignoredEnvironments)) {
            return $next($request);
        }

        if (null !== $apiKey) {
            config(['treblle.api_key' => $apiKey]);
        }

        if (! (config('treblle.sdk_token'))) {
            throw TreblleException::missingSdkToken();
        }

        if (! (config('treblle.api_key'))) {
            throw TreblleException::missingApiKey();
        }

        return $next($request);
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * This method is called after the response has been sent to the client,
     * implementing Laravel's terminable middleware pattern. It captures request,
     * response, and error data, applies sensitive data masking, and transmits
     * everything to Treblle's API for monitoring and analysis.
     *
     * The transmission is non-blocking and occurs after the user has received
     * their response, ensuring zero impact on perceived application performance.
     *
     * @param Request $request The HTTP request that was processed
     * @param JsonResponse|Response|SymfonyResponse $response The response that was sent
     *
     * @return void
     */
    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        $ignoredEnvironments = array_map('trim', explode(',', config('treblle.ignored_environments', '') ?? ''));

        if (in_array(app()->environment(), $ignoredEnvironments)) {
            return;
        }

        $maskedFields = (array)config('treblle.masked_fields');
        $fieldMasker = new SensitiveDataMasker($maskedFields);
        $errorProvider = new InMemoryErrorDataProvider();
        $requestProvider = new LaravelRequestDataProvider($fieldMasker, $request);
        $responseProvider = new LaravelResponseDataProvider($fieldMasker, $request, $response, $errorProvider);

        if (! empty($response->exception)) {
            $errorProvider->addError(new Error(
                $response->exception->getMessage(),
                $response->exception->getFile(),
                $response->exception->getLine(),
                'onException',
                'UNHANDLED_EXCEPTION',
            ));
        }

        $treblle = TreblleFactory::create(
            apiKey: (string)config('treblle.api_key'),
            sdkToken: (string)config('treblle.sdk_token'),
            debug: (bool)config('treblle.debug'),
            maskedFields: $maskedFields,
            config: [
                'url' => config('treblle.url'),
                'register_handlers' => false,
                'fork_process' => false,
                'request_provider' => $requestProvider,
                'response_provider' => $responseProvider,
                'error_provider' => $errorProvider,
            ]
        );

        // Manually execute onShutdown because on octane server never shuts down
        // so registered shutdown function never gets called
        // hence we have disabled handlers using config register_handlers
        $treblle
            ->setName(TreblleServiceProvider::SDK_NAME)
            ->setVersion(TreblleServiceProvider::SDK_VERSION)
            ->onShutdown();
    }
}
