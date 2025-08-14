<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Carbon\Carbon;
use Treblle\Php\DataTransferObject\Request;
use Treblle\Php\Contract\RequestDataProvider;
use Treblle\Laravel\Utils\OptimizedFieldMasker;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final readonly class OptimizedLaravelRequestDataProvider implements RequestDataProvider
{
    public function __construct(
        private OptimizedFieldMasker $fieldMasker,
        private \Illuminate\Http\Request|SymfonyRequest $request,
    ) {
    }

    public function getRequest(): Request
    {
        return new Request(
            timestamp: Carbon::now('UTC')->format('Y-m-d H:i:s'),
            url: $this->request->fullUrl(),
            ip: $this->request->ip() ?? 'bogon',
            user_agent: $this->request->userAgent() ?? '',
            method: $this->request->method(),
            headers: $this->fieldMasker->mask(
                $this->optimizeHeadersTransform($this->request->headers->all())
            ),
            query: $this->fieldMasker->mask($this->request->query->all()),
            body: $this->fieldMasker->mask($this->request->toArray()),
            route_path: $this->request->route()?->toSymfonyRoute()->getPath(),
        );
    }

    /**
     * Optimized headers transformation using array_map instead of collection chains
     */
    private function optimizeHeadersTransform(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $values) {
            $result[$key] = is_array($values) ? reset($values) : $values;
        }

        return $result;
    }
}
