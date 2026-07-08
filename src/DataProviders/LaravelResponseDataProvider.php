<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Treblle\Laravel\Helpers\HeaderFilter;
use Treblle\Laravel\DataTransferObject\Error;
use Treblle\Laravel\Helpers\EventStreamParser;
use Treblle\Laravel\Contracts\ErrorDataProvider;
use Treblle\Laravel\DataTransferObject\Response;
use Treblle\Laravel\Helpers\SensitiveDataMasker;
use Treblle\Laravel\Contracts\ResponseDataProvider;
use Treblle\Laravel\Helpers\StreamedResponseCapture;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        $capture = $this->streamedCapture();
        $streamed = $this->isStreamed();
        $rawBody = $this->resolveRawBody($capture);
        $size = strlen($rawBody);

        // A streamed body that stopped growing early is reported so the
        // truncation is visible in Treblle. The reason distinguishes the
        // per-stream 2MB cap from the shared memory budget being exhausted.
        if (null !== $capture && $capture->isTruncated()) {
            $this->errorDataProvider->addError(new Error(
                message: 'memory_budget' === $capture->reason()
                    ? 'Streamed response body not fully captured (memory budget reached)'
                    : 'Streamed response truncated at capture limit',
                file: '',
                line: 0,
                type: 'E_USER_WARNING',
            ));
        }

        // Enforce 2MB limit before decoding to avoid processing a huge string
        if ($size > 2 * 1024 * 1024) {
            $this->errorDataProvider->addError(new Error(
                message: 'Response payload too large',
                file: '',
                line: 0,
                type: 'E_USER_ERROR',
            ));

            $rawBody = (string) json_encode(['error' => 'Payload too large', 'size' => $size]);
            $size = strlen($rawBody);

            return $this->buildResponse($size, $this->fieldMasker->mask((array) json_decode($rawBody, true)));
        }

        return $this->buildResponse($size, $this->fieldMasker->mask($this->decodeBody($rawBody, $streamed)));
    }

    /**
     * Whether the underlying response streams its body from a callback.
     */
    private function isStreamed(): bool
    {
        return $this->response instanceof StreamedResponse;
    }

    /**
     * The capture holder the middleware teed the streamed output into, if any.
     */
    private function streamedCapture(): ?StreamedResponseCapture
    {
        if (! $this->isStreamed()) {
            return null;
        }

        $capture = $this->request->attributes->get('treblle_streamed_capture');

        return $capture instanceof StreamedResponseCapture ? $capture : null;
    }

    /**
     * Resolve the raw response body.
     *
     * Streamed responses (stream, eventStream/SSE, streamJson) return false from
     * getContent(), so their body is read from the capture holder that the
     * middleware teed the streamed output into.
     */
    private function resolveRawBody(?StreamedResponseCapture $capture): string
    {
        if (null !== $capture) {
            return $capture->getContent() ?: '{}';
        }

        return $this->response->getContent() ?: '{}';
    }

    /**
     * Decode the raw body into the array stored in the payload.
     *
     * SSE bodies are parsed into a structured events array; everything else is
     * JSON-decoded (covering streamJson and standard JSON responses). Non-JSON
     * streamed bodies fall back to a raw string wrapper so streamed text is not
     * silently discarded.
     *
     * @return array<int|string, mixed>
     */
    private function decodeBody(string $rawBody, bool $streamed): array
    {
        // '{}' is the empty-body sentinel from resolveRawBody(); treat as no body.
        if ('' === $rawBody || '{}' === $rawBody) {
            return [];
        }

        if ($streamed && $this->isEventStream()) {
            return EventStreamParser::parse($rawBody);
        }

        $decoded = json_decode($rawBody, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if ($streamed && '{}' !== $rawBody) {
            return ['raw' => $rawBody];
        }

        return [];
    }

    /**
     * Whether the response advertises the SSE (text/event-stream) content type.
     */
    private function isEventStream(): bool
    {
        $contentType = $this->response->headers->get('Content-Type', '');

        return str_contains(strtolower((string) $contentType), 'text/event-stream');
    }

    /**
     * @param array<int|string, mixed> $body
     */
    private function buildResponse(int $size, array $body): Response
    {
        return new Response(
            code: $this->response->getStatusCode(),
            size: $size,
            load_time: $this->getLoadTimeInMilliseconds(),
            body: $body,
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
