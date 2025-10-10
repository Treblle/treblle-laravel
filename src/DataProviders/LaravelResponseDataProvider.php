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

final class LaravelResponseDataProvider implements ResponseDataProvider
{
    public function __construct(
        private readonly SensitiveDataMasker     $fieldMasker,
        private Request|SymfonyRequest $request,
        private readonly JsonResponse|\Illuminate\Http\Response|SymfonyResponse $response,
        private ErrorDataProvider                                      &$errorDataProvider,
    ) {
    }

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
