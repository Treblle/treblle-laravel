<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Early Capture Middleware for Treblle Monitoring.
 *
 * This middleware captures the original request payload before any transformations,
 * validations, or modifications occur in subsequent middleware or controllers. This
 * ensures Treblle can log the exact data as it was received by the application.
 *
 * Usage:
 * Register this middleware with high priority (it's automatically prepended to
 * middleware priority list by TreblleServiceProvider) or apply it to specific
 * routes that require original payload capture.
 *
 * @package Treblle\Laravel\Middlewares
 */
final class TreblleEarlyMiddleware
{
    /**
     * Handle an incoming request and capture original payload.
     *
     * Stores the unmodified request payload in request attributes before any
     * subsequent middleware or application logic can transform it. This is
     * particularly useful for:
     * - Form requests with validation
     * - Middleware that modifies request data
     * - Controllers that mutate input before processing
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the pipeline
     *
     * @return mixed The response from the next middleware
     */
    public function handle(Request $request, Closure $next)
    {
        $payload = $request->input();

        foreach ($request->files->all() as $key => $file) {
            try {
                $payload[$key] = $this->normalizeFile($file);
            } catch (Throwable) {
                // Skip files that can't be read (should not happen at request start)
            }
        }

        $request->attributes->set('treblle_original_payload', $payload);

        return $next($request);
    }

    private function normalizeFile(UploadedFile|array $file): array
    {
        if (is_array($file)) {
            return array_map([$this, 'normalizeFile'], $file);
        }

        return [
            'name'      => $file->getClientOriginalName(),
            'size'      => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'extension' => $file->getClientOriginalExtension(),
        ];
    }
}
