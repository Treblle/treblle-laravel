<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Treblle\Laravel\Log\Logger;
use Treblle\Laravel\Schedule\TreblleScheduleRepository;
use Treblle\Php\Factory\TreblleFactory;
use Treblle\Php\DataTransferObject\Data;
use Treblle\Laravel\Jobs\SendTreblleData;
use Treblle\Php\DataTransferObject\Error;
use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Php\Helpers\SensitiveDataMasker;
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

    private $scheduleEnabled;

    private array $maskedFields;

    private bool $debug;

    /**
     * Initialize middleware with cached configuration.
     */
    public function __construct(
        private TreblleScheduleRepository $treblleScheduleRepository,
        private Logger $logger,
    ){
        $this->enabled = (bool) config('treblle.enable', true);

        // Parse ignored environments once and create hash map for O(1) lookups
        $ignoredEnvs = array_map('trim', explode(',', config('treblle.ignored_environments', '') ?? ''));
        $this->ignoredEnvironments = array_flip($ignoredEnvs);

        $this->queueEnabled = (bool) config('treblle.queue.enabled', false);
        $this->queueConnection = config('treblle.queue.connection');
        $this->queueName = config('treblle.queue.queue', 'default');
        $this->scheduleEnabled = config('treblle.schedule.enabled');
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
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the pipeline
     * @param string|null $apiKey Optional API key override for this specific route
     *
     * @return mixed The response from the next middleware
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

        // Validate configuration - fail silently if missing to never break the API
        if (! config('treblle.sdk_token')) {
            $this->logger->logWarning('TREBLLE_SDK_TOKEN is not configured. Treblle monitoring disabled.');

            return $next($request);
        }

        if (! config('treblle.api_key')) {
            $this->logger->logWarning('TREBLLE_API_KEY is not configured. Treblle monitoring disabled.');

            return $next($request);
        }

        // Pass request through immediately - data transmission happens in terminate()
        return $next($request);
    }

    /**
     * Perform any final actions for the request lifecycle.
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

        // Re-validate configuration (in case it was changed after handle())
        if (! config('treblle.sdk_token') || ! config('treblle.api_key')) {
            return;
        }

        // Queue mode: dispatch to background job for async processing
        if ($this->queueEnabled) {
            $this->dispatchToQueue($request, $response);

            return;
        }

        // Schedule mode: Register payload to be sent by Task scheduler
        if($this->scheduleEnabled) {
            $this->schedule($request, $response);

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
     * @param Request $request The HTTP request
     * @param JsonResponse|Response|SymfonyResponse $response The HTTP response
     *
     * @return void
     */
    private function dispatchToQueue(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        try {
            $payloadData = $this->generatePayloadData($request, $response);

            // Create and dispatch job with serializable data
            $job = new SendTreblleData($payloadData);

            if ($this->queueConnection) {
                $job->onConnection($this->queueConnection);
            }

            $job->onQueue($this->queueName);

            dispatch($job);
        } catch (Throwable $e) {
            $this->logger->logException('Treblle queue dispatch failed', $e);
        }
    }

    /**
     * Register payload for scheduled data transmission.
     *
     * Wrapped in try-catch to ensure Treblle never breaks the application.
     *
     * @param Request $request The HTTP request
     * @param JsonResponse|Response|SymfonyResponse $response The HTTP response
     *
     * @return void
     */
    private function schedule(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        try {
            $payloadData = $this->generatePayloadData($request, $response);
            $jsonPayload = json_encode($payloadData->toArray(), JSON_THROW_ON_ERROR);

            $this->treblleScheduleRepository->create($jsonPayload);
        } catch (Throwable $e) {
            $this->logger->logException('Treblle queue schedule registration failed', $e);
        }
    }

    private function generatePayloadData(Request $request, JsonResponse|Response|SymfonyResponse $response): TrebllePayloadData
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
        return new TrebllePayloadData(
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
    }

    /**
     * Send Treblle data synchronously using the core SDK.
     *
     * Wrapped in try-catch to ensure Treblle never breaks the application.
     *
     * @param Request $request The HTTP request
     * @param JsonResponse|Response|SymfonyResponse $response The HTTP response
     *
     * @return void
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
            $this->logger->logException('Treblle synchronous transmission failed', $e);
        }
    }
}
