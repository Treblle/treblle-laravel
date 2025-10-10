<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Carbon\Carbon;
use Treblle\Php\Helpers\HeaderFilter;
use Treblle\Php\DataTransferObject\Request;
use Treblle\Php\Helpers\SensitiveDataMasker;
use Treblle\Php\Contract\RequestDataProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final readonly class LaravelRequestDataProvider implements RequestDataProvider
{
    public function __construct(
        private SensitiveDataMasker    $fieldMasker,
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
                HeaderFilter::filter($this->request->headers->all())
            ),
            query: $this->fieldMasker->mask($this->request->query->all()),
            body: $this->fieldMasker->mask($this->getRequestBody()),
            route_path: $this->request->route()?->toSymfonyRoute()->getPath(),
        );
    }

    private function getRequestBody(): array
    {
        // Prioritizing original payload if captured by TreblleEarlyMiddleware.
        if ($this->request->attributes->has('treblle_original_payload')) {
            return $this->request->attributes->get('treblle_original_payload');
        }

        return $this->request->toArray();
    }
}
