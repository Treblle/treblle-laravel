<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Throwable;
use Illuminate\Http\UploadedFile;
use Treblle\Laravel\Helpers\HeaderFilter;
use Treblle\Laravel\DataTransferObject\Request;
use Treblle\Laravel\Helpers\SensitiveDataMasker;
use Illuminate\Http\Request as IlluminateRequest;
use Treblle\Laravel\Contracts\RequestDataProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Laravel-specific Request Data Provider for Treblle.
 *
 * Extracts and formats request data from Laravel/Symfony request objects.
 * Handles sensitive data masking, header filtering, file upload normalization,
 * original payload capture, and 2MB payload size enforcement.
 *
 * @package Treblle\Laravel\DataProviders
 */
final readonly class LaravelRequestDataProvider implements RequestDataProvider
{
    public function __construct(
        private SensitiveDataMasker $fieldMasker,
        private IlluminateRequest|SymfonyRequest $request,
    ) {
    }

    public function getRequest(): Request
    {
        return new Request(
            timestamp: gmdate('Y-m-d H:i:s'),
            url: $this->request->fullUrl(),
            ip: $this->request->ip() ?? 'bogon',
            user_agent: $this->request->userAgent() ?? '',
            method: $this->request->method(),
            headers: $this->fieldMasker->mask(
                HeaderFilter::filter($this->request->headers->all(), config('treblle.excluded_headers', []))
            ),
            query: $this->fieldMasker->mask($this->request->query->all()),
            body: $this->buildBody(),
            route_path: $this->request instanceof IlluminateRequest
                ? $this->request->route()?->uri()
                : null,
        );
    }

    /**
     * Builds the masked request body, enforcing the 2MB limit and normalizing file uploads.
     *
     * @return array<int|string, mixed>
     */
    private function buildBody(): array
    {
        $masked = $this->fieldMasker->mask($this->getRawBody());
        $encoded = json_encode($masked);

        if (false !== $encoded && strlen($encoded) > 2 * 1024 * 1024) {
            return ['error' => 'Payload too large', 'size' => strlen($encoded)];
        }

        return $masked;
    }

    /**
     * Returns the raw (unmasked) request body with file uploads normalized to metadata.
     *
     * @return array<int|string, mixed>
     */
    private function getRawBody(): array
    {
        // Use original payload captured by TreblleEarlyMiddleware if available
        if ($this->request->attributes->has('treblle_original_payload')) {
            $payload = $this->request->attributes->get('treblle_original_payload');
        } else {
            try {
                $payload = $this->request->input();
            } catch (Throwable) {
                $payload = [];
            }
        }

        // Merge in file metadata from the actual files bag
        if ($this->request instanceof IlluminateRequest) {
            foreach ($this->request->files->all() as $key => $file) {
                try {
                    $payload[$key] = $this->normalizeFile($file);
                } catch (Throwable) {
                    $payload[$key] = ['error' => 'file metadata unavailable'];
                }
            }
        }

        return $payload;
    }

    /**
     * Recursively normalizes UploadedFile instances to a metadata array.
     *
     * @param UploadedFile|array<mixed> $file
     * @return array<string, mixed>
     */
    private function normalizeFile(UploadedFile|array $file): array
    {
        if (is_array($file)) {
            return array_map([$this, 'normalizeFile'], $file);
        }

        return [
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'extension' => $file->getClientOriginalExtension(),
        ];
    }
}
