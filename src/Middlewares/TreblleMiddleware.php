<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Treblle\Laravel\Config\Validator;
use Treblle\Php\Factory\TreblleFactory;
use Treblle\Php\DataTransferObject\Data;
use Treblle\Laravel\Jobs\SendTreblleData;
use Treblle\Php\DataTransferObject\Error;
use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Php\Helpers\SensitiveDataMasker;
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Laravel\Factories\PayloadDataFactory;
use Treblle\Php\DataProviders\PhpLanguageDataProvider;
use Treblle\Php\DataProviders\InMemoryErrorDataProvider;
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
 */
final class TreblleMiddleware
{
    /**
     * Cached configuration values for performance optimization.
     */
    private bool $enabled;

    private bool $queueEnabled;

    private ?string $queueConnection;

    private string $queueName;

    private array $maskedFields;

    private bool $debug;

    /**
     * Initialize middleware with cached configuration.
     */
    public function __construct(
        private Validator $configValidator,
    ) {
        $this->enabled = (bool) config('treblle.enable', true);
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
     * Uses Laravel's terminable middleware pattern - actual data transmission
     * happens in terminate() after the response has been sent to the client.
     *
     * IMPORTANT: This middleware NEVER throws exceptions. Missing configuration
     * results in silent failure (with debug logging) to ensure Treblle never
     * breaks your API.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     * @param  string|null  $apiKey  Optional API key override for this specific route
     * @return mixed The response from the next middleware
     */
    public function handle(Request $request, Closure $next, ?string $apiKey = null)
    {
        if (! $this->enabled) {
            return $next($request);
        }

        // O(1) hash lookup instead of O(n) in_array
        try {
            $this->configValidator->validateEnvironment();
        } catch (TreblleException) {
            return $next($request);
        }

        if (null !== $apiKey) {
            config(['treblle.api_key' => $apiKey]);
        }

        // Validate configuration - fail silently if missing to never break the API
        try {
            $this->configValidator->validateKeys();
        } catch (TreblleException) {
            return $next($request);
        }

        // Pass request through immediately - data transmission happens in terminate()
        return $next($request);
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  Request  $request  The HTTP request that was processed
     * @param  JsonResponse|Response|SymfonyResponse  $response  The response that was sent
     */
    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        // O(1) hash lookup for ignored environments
        try {
            $this->configValidator->validateEnvironment();
        } catch (TreblleException) {
            return;
        }

        // Re-validate configuration (in case it was changed after handle())
        try {
            $this->configValidator->validateKeys();
        } catch (TreblleException) {
            return;
        }

        // Queue mode: dispatch to background job for async processing
        if ($this->queueEnabled) {
            $this->dispatchToQueue($request, $response);

            return;
        }

        // Synchronous mode: send directly to Treblle
        $this->sendSynchronously($request, $response);
    }

    /**
     * Dispatch Treblle data to queue for background processing.
     *
     * Wrapped in try-catch to ensure Treblle never breaks the application.
     *
     * @param  Request  $request  The HTTP request
     * @param  JsonResponse|Response|SymfonyResponse  $response  The HTTP response
     */
    private function dispatchToQueue(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        try {
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
            try {
                $payloadData = PayloadDataFactory::create(PayloadDataFactory::TREBLLE);
            } catch (TreblleException $e) {
                if ($this->debug) {
                    logger()->error('[Treblle] ' . $e->getMessage());
                }
                return;
            }

            $payloadData->setData(new Data(
                $serverProvider->getServer(),
                $languageProvider->getLanguage(),
                $requestProvider->getRequest(),
                $responseProvider->getResponse(),
                $errorProvider->getErrors()
            ));

            // Create and dispatch job with serializable data
            $job = new SendTreblleData($payloadData);

            if ($this->queueConnection) {
                $job->onConnection($this->queueConnection);
            }

            $job->onQueue($this->queueName);

            dispatch($job);
        } catch (Throwable $e) {
            $this->logError('Treblle queue dispatch failed', $e);
        }
    }

    /**
     * Send Treblle data synchronously using the core SDK.
     *
     * Wrapped in try-catch to ensure Treblle never breaks the application.
     *
     * @param  Request  $request  The HTTP request
     * @param  JsonResponse|Response|SymfonyResponse  $response  The HTTP response
     */
    private function sendSynchronously(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        try {
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
        } catch (Throwable $e) {
            $this->logError('Treblle synchronous transmission failed', $e);
        }
    }

    /**
     * Log configuration errors when debug mode is enabled.
     *
     * @param  string  $message  The error message
     */
    private function logConfigError(string $message): void
    {
        if ($this->debug) {
            logger()->warning('[Treblle] ' . $message);
        }
    }

    /**
     * Log runtime errors when debug mode is enabled.
     *
     * @param  string  $message  The error message
     * @param  Throwable  $exception  The exception that was thrown
     */
    private function logError(string $message, Throwable $exception): void
    {
        if ($this->debug) {
            logger()->error('[Treblle] ' . $message, [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }
    }
}
