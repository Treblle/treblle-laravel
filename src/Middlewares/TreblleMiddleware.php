<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Treblle\Php\FieldMasker;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Treblle\Php\Factory\TreblleFactory;
use Treblle\Php\DataTransferObject\Error;
use Treblle\Php\InMemoryErrorDataProvider;
use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Laravel\Utils\OptimizedFieldMasker;
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Laravel\DataProviders\LaravelRequestDataProvider;
use Treblle\Laravel\DataProviders\LaravelResponseDataProvider;
use Treblle\Laravel\DataProviders\OptimizedLaravelRequestDataProvider;
use Treblle\Laravel\DataProviders\OptimizedLaravelResponseDataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class TreblleMiddleware
{
    private readonly bool $enabled;

    private readonly array $ignoredEnvironments;

    private readonly string $currentEnvironment;

    private readonly ?string $apiKey;

    private readonly ?string $projectId;

    private readonly ?bool $debug;

    private readonly ?string $url;

    private readonly array $maskedFields;

    public function __construct()
    {
        $this->enabled = (bool) config('treblle.enable');
        $this->ignoredEnvironments = array_map(
            'trim',
            explode(',', config('treblle.ignored_environments', '') ?? '')
        );
        $this->currentEnvironment = app()->environment();
        $this->apiKey = config('treblle.api_key');
        $this->projectId = config('treblle.project_id');
        $this->debug = config('treblle.debug');
        $this->url = config('treblle.url');
        $this->maskedFields = (array) config('treblle.masked_fields');
    }

    /**
     * @throws TreblleException
     */
    public function handle(Request $request, Closure $next, string|null $projectId = null)
    {
        if (! $this->enabled) {
            return $next($request);
        }

        if (in_array($this->currentEnvironment, $this->ignoredEnvironments)) {
            return $next($request);
        }

        if (null !== $projectId) {
            config(['treblle.project_id' => $projectId]);
        }

        $apiKey = null !== $projectId ? config('treblle.api_key') : $this->apiKey;
        $currentProjectId = $projectId ?? $this->projectId;

        if (! $apiKey) {
            throw TreblleException::missingApiKey();
        }

        if (! $currentProjectId) {
            throw TreblleException::missingProjectId();
        }

        return $next($request);
    }

    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        if (in_array($this->currentEnvironment, $this->ignoredEnvironments)) {
            return;
        }

        $optimizedFieldMasker = new OptimizedFieldMasker($this->maskedFields);
        $errorProvider = new InMemoryErrorDataProvider();
        $requestProvider = new OptimizedLaravelRequestDataProvider($optimizedFieldMasker, $request);
        $responseProvider = new OptimizedLaravelResponseDataProvider($optimizedFieldMasker, $request, $response, $errorProvider);

        if (! empty($response->exception)) {
            $errorProvider->addError(new Error(
                $response->exception->getMessage(),
                $response->exception->getFile(),
                $response->exception->getLine(),
                'onException',
                'UNHANDLED_EXCEPTION',
            ));
        }

        // Get current values (in case projectId was overridden in handle method)
        $currentApiKey = config('treblle.api_key') ?? $this->apiKey;
        $currentProjectId = config('treblle.project_id') ?? $this->projectId;

        $treblle = TreblleFactory::create(
            apiKey: (string) $currentApiKey,
            projectId: (string) $currentProjectId,
            debug: (bool) $this->debug,
            maskedFields: $this->maskedFields,
            config: [
                'url' => $this->url,
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
