<?php

declare(strict_types=1);

namespace Treblle\DataProviders;

use Treblle\FieldMasker;
use Treblle\DataTransferObject\Response;
use Treblle\Contract\ResponseDataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class LaravelResponseDataProvider implements ResponseDataProvider
{
    public function __construct(
        private FieldMasker     $fieldMasker,
        private SymfonyResponse $response,
    ) {
    }

    public function getResponse(): Response
    {
        return new Response(
            code: $this->response->getStatusCode(),
            size: mb_strlen($this->response->getContent()),
            load_time: microtime(true) - $this->startTime(),
            body: $this->fieldMasker->mask(
                json_decode($this->response->getContent(), true) ?? []
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
