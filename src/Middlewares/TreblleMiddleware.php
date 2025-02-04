<?php

declare(strict_types=1);

namespace Treblle\Middlewares;

use Closure;
use Treblle\FieldMasker;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Treblle\Factory\TreblleFactory;
use Treblle\DataTransferObject\Error;
use Treblle\InMemoryErrorDataProvider;
use Treblle\DataProviders\LaravelRequestDataProvider;
use Treblle\DataProviders\LaravelResponseDataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class TreblleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        //        if (! $request->headers->has('X-TREBLLE-TRACE-ID')) {
        //            $request->headers->add([
        //                'X-TREBLLE-TRACE-ID' => $id = Str::uuid(),
        //            ]);
        //        }
        //
        //        $response->headers->add([
        //            'X-TREBLLE-TRACE-ID' => $request->headers->get('X-TREBLLE-TRACE-ID'),
        //        ]);
        //
        //        if (strlen((string) $response->getContent()) > 2 * 1024 * 1024) {
        //            if (! app()->environment('production')) {
        //                Log::error(
        //                    message: 'Cannot send response over 2MB to Treblle.',
        //                    context: [
        //                        'url' => $request->fullUrl(),
        //                        'date' => now()->toDateTimeString(),
        //                    ]
        //                );
        //            }
        //
        //            return;
        //        }

        $maskedFields = (array)config('treblle.masked_fields');
        $fieldMasker = new FieldMasker($maskedFields);
        $requestProvider = new LaravelRequestDataProvider($fieldMasker, $request);
        $responseProvider = new LaravelResponseDataProvider($fieldMasker, $response);
        $errorProvider = new InMemoryErrorDataProvider();

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
