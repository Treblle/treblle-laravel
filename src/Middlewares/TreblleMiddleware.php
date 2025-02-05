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
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Laravel\DataProviders\LaravelRequestDataProvider;
use Treblle\Laravel\DataProviders\LaravelResponseDataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class TreblleMiddleware
{
    /**
     * @throws TreblleException
     */
    public function handle(Request $request, Closure $next)
    {
        $ignoredEnvironments = array_map('trim', explode(',', config('treblle.ignored_environments', '') ?? ''));

        if (in_array(app()->environment(), $ignoredEnvironments)) {
            return $next($request);
        }

        if (! (config('treblle.api_key'))) {
            throw TreblleException::missingApiKey();
        }

        if (! (config('treblle.project_id'))) {
            throw TreblleException::missingProjectId();
        }

        return $next($request);
    }

    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        $maskedFields = (array)config('treblle.masked_fields');
        $fieldMasker = new FieldMasker($maskedFields);
        $errorProvider = new InMemoryErrorDataProvider();
        $requestProvider = new LaravelRequestDataProvider($fieldMasker, $request);
        $responseProvider = new LaravelResponseDataProvider($fieldMasker, $response, $errorProvider);

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
            projectId: (string)config('treblle.project_id'),
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
            ->setName('laravel')
            ->setVersion(5.0)
            ->onShutdown();
    }
}
