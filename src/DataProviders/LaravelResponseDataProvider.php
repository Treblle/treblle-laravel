<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Treblle\Php\Helpers\HeaderFilter;
use Treblle\Php\DataTransferObject\Error;
use Treblle\Php\Contract\ErrorDataProvider;
use Treblle\Php\DataTransferObject\Response;
use Treblle\Php\Helpers\SensitiveDataMasker;
use Treblle\Php\Contract\ResponseDataProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Laravel-specific Response Data Provider for Treblle.
 *
 * Implements the ResponseDataProvider contract from treblle-php to extract
 * and format response data from Laravel/Symfony response objects. Handles
 * sensitive data masking, header filtering, response size validation, and
 * accurate load time calculation.
 *
 * @package Treblle\Laravel\DataProviders
 */
final class LaravelResponseDataProvider implements ResponseDataProvider
{
    /**
     * Create a new Laravel response data provider instance.
     *
     * @param SensitiveDataMasker $fieldMasker Masker for sensitive data fields
     * @param Request|SymfonyRequest $request The Laravel/Symfony request object
     * @param JsonResponse|\Illuminate\Http\Response|SymfonyResponse $response The Laravel/Symfony response object
     * @param ErrorDataProvider $errorDataProvider Reference to error data provider for logging issues
     */
    public function __construct(
        private readonly SensitiveDataMasker     $fieldMasker,
        private Request|SymfonyRequest $request,
        private readonly JsonResponse|\Illuminate\Http\Response|SymfonyResponse $response,
        private ErrorDataProvider                                      &$errorDataProvider,
    ) {
    }

    /**
     * Extract and format response data for Treblle.
     *
     * Builds a Response DTO containing all relevant response information including
     * status code, body size, load time, headers (filtered and masked), and response
     * body (masked). Validates response size and logs an error if it exceeds 2MB.
     *
     * @return Response The formatted response data transfer object
     */
    public function getResponse(): Response
    {
        $body = $this->response->getContent();
        $size = mb_strlen($body);

        if ($size > 2 * 1024 * 1024) {
            $body = '{}';
            $size = 0;

            $this->errorDataProvider->addError(new Error(
                message: 'JSON response size is over 2MB',
                file: '',
                line: 0,
                type: 'E_USER_ERROR'
            ));
        }

        return new Response(
            code: $this->response->getStatusCode(),
            size: $size,
            load_time: $this->getLoadTimeInMilliseconds(),
            body: $this->fieldMasker->mask(
                json_decode($body, true) ?? []
            ),
            headers: $this->fieldMasker->mask(
                HeaderFilter::filter($this->response->headers->all(), config('treblle.excluded_headers', []))
            ),
        );
    }

    /**
     * Calculate the request load time in milliseconds.
     *
     * Determines the total time taken to process the request by checking multiple
     * sources in order of accuracy:
     * 1. Custom timestamp set by Laravel Octane event listener (most accurate for Octane)
     * 2. PHP's REQUEST_TIME_FLOAT server variable
     * 3. Laravel's LARAVEL_START constant
     *
     * @return float The load time in milliseconds
     */
    private function getLoadTimeInMilliseconds(): float
    {
        $currentTimeInMilliseconds = microtime(true) * 1000;
        $requestTimeInMilliseconds = microtime(true) * 1000;

        if ($this->request->attributes->has('treblle_request_started_at')) {
            $requestTimeInMilliseconds = $this->request->attributes->get('treblle_request_started_at') * 1000;

            return $currentTimeInMilliseconds - $requestTimeInMilliseconds;
        }

        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $requestTimeInMilliseconds = (float)$_SERVER['REQUEST_TIME_FLOAT'] * 1000;
        } elseif (defined('LARAVEL_START')) {
            $requestTimeInMilliseconds = LARAVEL_START * 1000;
        }

        return $currentTimeInMilliseconds - $requestTimeInMilliseconds;
    }
}
