<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Treblle\Laravel\FieldMasker;
use Illuminate\Http\JsonResponse;
use Treblle\Laravel\DataTransferObject\Error;
use Treblle\Laravel\Contract\ErrorDataProvider;
use Treblle\Laravel\DataTransferObject\Response;
use Treblle\Laravel\Contract\ResponseDataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class LaravelResponseDataProvider implements ResponseDataProvider
{
    public function __construct(
        private FieldMasker     $fieldMasker,
        private JsonResponse|\Illuminate\Http\Response|SymfonyResponse $response,
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
            load_time: microtime(true) - $this->startTime(),
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

    private function startTime(): float
    {
        return $_SERVER['REQUEST_TIME_FLOAT']
            ?? (defined('LARAVEL_START') ? LARAVEL_START : null)
            ?? microtime(true);
    }
}
