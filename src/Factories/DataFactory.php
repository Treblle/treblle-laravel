<?php

declare(strict_types=1);

namespace Treblle\Factories;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;
use Treblle\Utils\DataObjects\Data;
use Treblle\Utils\DataObjects\Error;
use Treblle\Utils\DataObjects\Language;
use Treblle\Utils\DataObjects\OS;
use Treblle\Utils\DataObjects\Request as RequestObject;
use Treblle\Utils\DataObjects\Response as ResponseObject;
use Treblle\Utils\DataObjects\Server;
use Treblle\Utils\Http\Method;
use Treblle\Utils\Masking\FieldMasker;
use Treblle\Utils\Support\PHP;

final class DataFactory
{
    public function __construct(
        private readonly FieldMasker $masker,
    ) {
    }

    public function make(Request $request, JsonResponse|Response|SymfonyResponse $response, float|int $loadTime): Data
    {
        $php = new PHP();

        $errors = [];

        try {
            $responseBody = $this->masker->mask(
                (array) json_decode(
                    $response->getContent(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
            );
        } catch (Throwable) {
            $responseBody = '{}';
        }

        if (! empty($response->exception)) {
            $errors[] = new Error(
                'onException',
                'UNHANDLED_EXCEPTION',
                $response->exception->getMessage(),
                $response->exception->getFile(),
                $response->exception->getLine(),
            );
        }

        return new Data(
            new Server(
                (string) $request->server('SERVER_ADDR'),
                (string) config('app.timezone'),
                (string) $request->server('SERVER_SOFTWARE'),
                (string) $request->server('SERVER_SIGNATURE'),
                (string) $request->server('SERVER_PROTOCOL'),
                new OS(
                    php_uname('s'),
                    php_uname('r'),
                    php_uname('m'),
                ),
                (string) $request->server('HTTP_ACCEPT_ENCODING'),
            ),
            new Language(
                'php',
                PHP_VERSION,
                $php->get(
                    'expose_php',
                ),
                $php->get(
                    'display_errors',
                ),
            ),
            new RequestObject(
                Carbon::now('UTC')->format('Y-m-d H:i:s'),
                (string) $request->ip(),
                $request->fullUrl(),
                $request->route()?->getName(),
                strval($request->userAgent()),
                Method::from(
                    $request->method(),
                ),
                $this->masker->mask(
                    collect($request->headers->all())->transform(
                        /* @phpstan-ignore-next-line */
                        fn ($item) => collect($item)->first(),
                    )->toArray(),
                ),
                $this->masker->mask(
                    $request->all(),
                ),
                $this->masker->mask(
                    $request->all(),
                ),
            ),
            new ResponseObject(
                $this->masker->mask(
                    collect($response->headers->all())->transform(
                        /* @phpstan-ignore-next-line */
                        fn ($item) => collect($item)->first(),
                    )->toArray(),
                ),
                $response->getStatusCode(),
                strlen($response->getContent()),
                $loadTime,
                $responseBody,
            ),
            $errors,
        );
    }
}
