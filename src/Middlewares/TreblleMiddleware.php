<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Treblle\Php\Factory\TreblleFactory;
use Treblle\Php\DataTransferObject\Data;
use Treblle\Laravel\Jobs\SendTreblleData;
use Treblle\Php\DataTransferObject\Error;
use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Php\Helpers\SensitiveDataMasker;
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Php\DataProviders\PhpLanguageDataProvider;
use Treblle\Php\DataProviders\InMemoryErrorDataProvider;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;
use Treblle\Laravel\DataProviders\LaravelRequestDataProvider;
use Treblle\Php\DataProviders\SuperGlobalsServerDataProvider;
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
     * Cached configuration values for performance optimization.
     */
    private bool $enabled;

    private array $ignoredEnvironments;

    private bool $queueEnabled;

    private ?string $queueConnection;

    private string $queueName;

    private array $maskedFields;

    private bool $debug;

    /**
     * Initialize middleware with cached configuration.
     */
    public function __construct()
    {
        $this->enabled = (bool) config('treblle.enable', true);

        // Parse ignored environments once and create hash map for O(1) lookups
        $ignoredEnvs = array_map('trim', explode(',', config('treblle.ignored_environments', '') ?? ''));
        $this->ignoredEnvironments = array_flip($ignoredEnvs);

        $this->queueEnabled = (bool) config('treblle.queue.enabled', false);
        $this->queueConnection = config('treblle.queue.connection');
        $this->queueName = config('treblle.queue.queue', 'default');
        $this->maskedFields = (array) config('treblle.masked_fields', []);
        $this->debug = (bool) config('treblle.debug', false);
    }

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
        if (! $this->enabled) {
            return $next($request);
        }

        // O(1) hash lookup instead of O(n) in_array
        if (isset($this->ignoredEnvironments[app()->environment()])) {
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

        // Get the response from the next middleware
        $response = $next($request);

        // IMPORTANT: When queue mode is enabled, dispatch IMMEDIATELY in handle()
        // instead of waiting for terminate(). This is critical for PHP-FPM compatibility
        // because terminate() is not guaranteed to run in PHP-FPM environments.
        //
        // PHP-FPM can close the worker before terminate() executes, especially when
        // using FastCGI. By dispatching in handle() (after $next but before returning),
        // we ensure the job reaches the queue in ALL production environments.
        if ($this->queueEnabled) {
            $this->dispatchToQueue($request, $response);
        }

        return $response;
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * This method is called after the response has been sent to the client,
     * implementing Laravel's terminable middleware pattern. It is ONLY used
     * for synchronous transmission mode (when queues are disabled).
     *
     * IMPORTANT: This method is NOT used for queue mode because terminate()
     * is unreliable in PHP-FPM environments. Queue dispatching happens in
     * handle() instead to ensure reliability across all production setups.
     *
     * @param Request $request The HTTP request that was processed
     * @param JsonResponse|Response|SymfonyResponse $response The response that was sent
     *
     * @return void
     */
    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        // O(1) hash lookup for ignored environments
        if (isset($this->ignoredEnvironments[app()->environment()])) {
            return;
        }

        // Skip if queue mode is enabled (already dispatched in handle())
        if ($this->queueEnabled) {
            return;
        }

        // Synchronous transmission (fallback mode)
        $this->sendSynchronously($request, $response);
    }

    /**
     * Dispatch Treblle data to queue for background processing.
     * @param Request $request The HTTP request
     * @param JsonResponse|Response|SymfonyResponse $response The HTTP response
     *
     * @return void
     */
    private function dispatchToQueue(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        // Extract data from Request/Response BEFORE queuing to avoid serialization issues
        $fieldMasker = new SensitiveDataMasker($this->maskedFields);
        $errorProvider = new InMemoryErrorDataProvider();
        $requestProvider = new LaravelRequestDataProvider($fieldMasker, $request);
        $responseProvider = new LaravelResponseDataProvider($fieldMasker, $request, $response, $errorProvider);

        // Use core SDK providers for Server and Language data
        $serverProvider = new SuperGlobalsServerDataProvider();
        $languageProvider = new PhpLanguageDataProvider();

        // Capture exception data if present
        if (! empty($response->exception)) {
            $errorProvider->addError(new Error(
                $response->exception->getMessage(),
                $response->exception->getFile(),
                $response->exception->getLine(),
                'onException',
                'UNHANDLED_EXCEPTION',
            ));
        }

        // Build serializable DTO with extracted data
        $payloadData = new TrebllePayloadData(
            apiKey: (string) config('treblle.api_key'),
            sdkToken: (string) config('treblle.sdk_token'),
            sdkName: TreblleServiceProvider::SDK_NAME,
            sdkVersion: TreblleServiceProvider::SDK_VERSION,
            data: new Data(
                $serverProvider->getServer(),
                $languageProvider->getLanguage(),
                $requestProvider->getRequest(),
                $responseProvider->getResponse(),
                $errorProvider->getErrors()
            ),
            url: config('treblle.url'),
            debug: $this->debug
        );

        // Create and dispatch job with serializable data
        $job = new SendTreblleData($payloadData);

        if ($this->queueConnection) {
            $job->onConnection($this->queueConnection);
        }

        $job->onQueue($this->queueName);

        dispatch($job);
    }

    /**
     * Send Treblle data synchronously using the core SDK.
     * @param Request $request The HTTP request
     * @param JsonResponse|Response|SymfonyResponse $response The HTTP response
     *
     * @return void
     */
    private function sendSynchronously(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        // Use cached config values
        $fieldMasker = new SensitiveDataMasker($this->maskedFields);
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
            apiKey: (string) config('treblle.api_key'),
            sdkToken: (string) config('treblle.sdk_token'),
            debug: $this->debug,
            maskedFields: $this->maskedFields,
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
