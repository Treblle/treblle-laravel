<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Throwable;
use Carbon\Carbon;
use Treblle\Php\Helpers\HeaderFilter;
use Treblle\Php\DataTransferObject\Request;
use Treblle\Php\Helpers\SensitiveDataMasker;
use Treblle\Php\Contract\RequestDataProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Laravel-specific Request Data Provider for Treblle.
 *
 * Implements the RequestDataProvider contract from treblle-php to extract
 * and format request data from Laravel/Symfony request objects. Handles
 * sensitive data masking, header filtering, and original payload capture.
 *
 * @package Treblle\Laravel\DataProviders
 */
final readonly class LaravelRequestDataProvider implements RequestDataProvider
{
    /**
     * Create a new Laravel request data provider instance.
     *
     * @param SensitiveDataMasker $fieldMasker Masker for sensitive data fields
     * @param \Illuminate\Http\Request|SymfonyRequest $request The Laravel/Symfony request object
     */
    public function __construct(
        private SensitiveDataMasker    $fieldMasker,
        private \Illuminate\Http\Request|SymfonyRequest $request,
    ) {
    }

    /**
     * Extract and format request data for Treblle.
     *
     * Builds a Request DTO containing all relevant request information including
     * headers (filtered and masked), query parameters (masked), request body
     * (masked), URL, IP address, user agent, HTTP method, and route path.
     *
     * @return Request The formatted request data transfer object
     */
    public function getRequest(): Request
    {
        return new Request(
            timestamp: Carbon::now('UTC')->format('Y-m-d H:i:s'),
            url: $this->request->fullUrl(),
            ip: $this->request->ip() ?? 'bogon',
            user_agent: $this->request->userAgent() ?? '',
            method: $this->request->method(),
            headers: $this->fieldMasker->mask(
                HeaderFilter::filter($this->request->headers->all(), config('treblle.excluded_headers', []))
            ),
            query: $this->fieldMasker->mask($this->request->query->all()),
            body: $this->fieldMasker->mask($this->getRequestBody()),
            route_path: $this->request->route()?->toSymfonyRoute()->getPath(),
        );
    }

    /**
     * Get the request body with priority for original payload.
     *
     * Returns the original unmodified request payload if it was captured by
     * TreblleEarlyMiddleware. Otherwise, returns the current request data.
     * This ensures accurate logging even when middleware or form requests
     * modify the payload.
     *
     * @return array The request body data
     */
    private function getRequestBody(): array
    {
        // Prioritizing original payload if captured by TreblleEarlyMiddleware.
        if ($this->request->attributes->has('treblle_original_payload')) {
            return $this->request->attributes->get('treblle_original_payload');
        }

        // Try toArray() first (supports JSON requests), fallback to all() for GET/multipart
        try {
            return $this->request->toArray();
        } catch (Throwable $e) {
            // toArray() throws BadMethodCallException for GET requests and ValidationException
            // for malformed JSON. Fall back to all() which safely returns all input.
            return $this->request->all();
        }
    }
}
