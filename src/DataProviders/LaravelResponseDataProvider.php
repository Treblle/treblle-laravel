<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Treblle\Laravel\Helpers\HeaderFilter;
use Treblle\Laravel\DataTransferObject\Error;
use Treblle\Laravel\Contracts\ErrorDataProvider;
use Treblle\Laravel\DataTransferObject\Response;
use Treblle\Laravel\Helpers\SensitiveDataMasker;
use Treblle\Laravel\Contracts\ResponseDataProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Laravel-specific Response Data Provider for Treblle.
 *
 * Extracts and formats response data from Laravel/Symfony response objects.
 * Handles sensitive data masking, header filtering, 2MB size enforcement, and
 * accurate load time calculation for standard Laravel, Octane, and Vapor.
 *
 * @package Treblle\Laravel\DataProviders
 */
final class LaravelResponseDataProvider implements ResponseDataProvider
{
    public function __construct(
        private readonly SensitiveDataMasker $fieldMasker,
        private readonly Request|SymfonyRequest $request,
        private readonly JsonResponse|\Illuminate\Http\Response|SymfonyResponse $response,
        private ErrorDataProvider $errorDataProvider,
    ) {
    }

    public function getResponse(): Response
    {
        $rawBody = $this->response->getContent() ?: '{}';
        $size = mb_strlen($rawBody);

        // Enforce 2MB limit before decoding to avoid processing a huge string
        if ($size > 2 * 1024 * 1024) {
            $this->errorDataProvider->addError(new Error(
                message: 'Response payload too large',
                file: '',
                line: 0,
                type: 'E_USER_ERROR',
            ));

            $rawBody = (string) json_encode(['error' => 'Payload too large', 'size' => $size]);
            $size = mb_strlen($rawBody);
        }

        return new Response(
            code: $this->response->getStatusCode(),
            size: $size,
            load_time: $this->getLoadTimeInMilliseconds(),
            body: $this->fieldMasker->mask(
                json_decode($rawBody, true) ?? []
            ),
            headers: $this->fieldMasker->mask(
                HeaderFilter::filter($this->response->headers->all(), config('treblle.excluded_headers', []))
            ),
        );
    }

    /**
     * Calculates the request load time in milliseconds.
     *
     * Priority:
     * 1. treblle_request_started_at attribute (set by Octane event listener)
     * 2. REQUEST_TIME_FLOAT server variable
     * 3. LARAVEL_START constant
     */
    private function getLoadTimeInMilliseconds(): float
    {
        $now = microtime(true) * 1000;

        if ($this->request->attributes->has('treblle_request_started_at')) {
            return $now - ($this->request->attributes->get('treblle_request_started_at') * 1000);
        }

        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return $now - ((float) $_SERVER['REQUEST_TIME_FLOAT'] * 1000);
        }

        if (defined('LARAVEL_START')) {
            return $now - (LARAVEL_START * 1000);
        }

        return 0.0;
    }
}
