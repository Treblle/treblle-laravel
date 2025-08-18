<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Illuminate\Http\Request;

final class TreblleEarlyMiddleware
{
    /**
     * Handle an incoming request and capture the original payload before any transformations.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only capture JSON or form data payloads
        if ($this->shouldCapturePayload($request)) {
            // Store the original payload as a request attribute
            $request->attributes->set('treblle_original_payload', $this->capturePayload($request));
        }

        return $next($request);
    }

    /**
     * Determine if we should capture the payload based on content type.
     */
    private function shouldCapturePayload(Request $request): bool
    {
        $contentType = $request->header('content-type', '');

        return $request->isMethod(['POST', 'PUT', 'PATCH', 'DELETE']) && (
            str_contains($contentType, 'application/json') ||
            str_contains($contentType, 'application/x-www-form-urlencoded') ||
            str_contains($contentType, 'multipart/form-data')
        );
    }

    /**
     * Capture the original payload from the request.
     */
    private function capturePayload(Request $request): array
    {
        // For JSON requests, get the raw JSON and decode it
        if ($request->isJson()) {
            $content = $request->getContent();

            return $content ? json_decode($content, true) ?? [] : [];
        }

        // For form data requests, get all the input data
        return $request->all();
    }
}
