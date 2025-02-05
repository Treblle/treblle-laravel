<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Treblle\Php\FieldMasker;
use Illuminate\Http\JsonResponse;
use Treblle\Php\DataTransferObject\Error;
use Treblle\Php\Contract\ErrorDataProvider;
use Treblle\Php\DataTransferObject\Response;
use Treblle\Php\Contract\ResponseDataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class LaravelResponseDataProvider implements ResponseDataProvider
{
    public function __construct(
        private readonly FieldMasker     $fieldMasker,
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
                collect($this->response->headers->all())->transform(
                    fn ($item) => collect($item)->first(),
                )->toArray()
            ),
        );
    }

    private function getLoadTimeInMilliseconds(): float
    {
        $currentTime = microtime(true) * 1000;
        $requestTimeInMilliseconds = microtime(true) * 1000;

        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $requestTimeInMilliseconds = (float)$_SERVER['REQUEST_TIME_FLOAT'] * 1000;
        } elseif (defined('LARAVEL_START')) {
            $requestTimeInMilliseconds = LARAVEL_START * 1000;
        }

        return $currentTime - $requestTimeInMilliseconds;
    }
}
