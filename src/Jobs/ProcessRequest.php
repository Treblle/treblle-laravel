<?php

declare(strict_types=1);

namespace Treblle\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;
use Treblle\Contracts\TreblleClientContract;
use Treblle\Core\Contracts\Masking\MaskingContract;
use Treblle\Core\DataObjects\Data;
use Treblle\Core\DataObjects\Error;
use Treblle\Core\DataObjects\Language;
use Treblle\Core\DataObjects\OS;
use Treblle\Core\DataObjects\Request as RequestObject;
use Treblle\Core\DataObjects\Response as ResponseObject;
use Treblle\Core\DataObjects\Server;
use Treblle\Core\Http\Endpoint;
use Treblle\Core\Http\Method;
use Treblle\Core\Support\PHP;

final class ProcessRequest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Request $request,
        public JsonResponse|Response $response,
        public float $loadTime,
    ) {
    }

    /**
     * @throws RequestException
     * @throws Throwable
     * @throws JsonException
     */
    public function handle(TreblleClientContract $client, MaskingContract $masker): void
    {
        $data = $this->buildPayload(
            masker: $masker,
        );

        try {
            /**
             * @var Endpoint $url
             */
            $url = Arr::random(Endpoint::cases());

            $client->request()->send(
                method: Method::POST->value,
                url: $url->value,
                options: $data->jsonSerialize(),
            )->throw();
        } catch (Throwable $exception) {
            Log::error(
                message: 'Failed to process treblle request',
                context: $data->jsonSerialize(),
            );

            throw $exception;
        }
    }

    /**
     * @throws JsonException
     */
    public function buildPayload(MaskingContract $masker): Data
    {
        $php = new PHP();

        $errors = [];

        try {
            $responseBody = $masker->mask(
                data: (array) json_decode(
                    json: $this->response->content(),
                    associative: true,
                    flags: JSON_THROW_ON_ERROR,
                ),
            );
        } catch (Throwable $exception) {
            throw $exception;
        }

        if (! empty($this->response->exception)) {
            $errors[] = new Error(
                source: 'onException',
                type: 'UNHANDLED_EXCEPTION',
                message: $this->response->exception->getMessage(),
                file: $this->response->exception->getFile(),
                line: $this->response->exception->getLine(),
            );
        }

        return new Data(
            server: new Server(
                ip: (string) ($this->request->server('SERVER_ADDR')),
                timezone: (string) (config('app.timezone')),
                software: (string) ($this->request->server('SERVER_SOFTWARE')),
                signature: (string) ($this->request->server('SERVER_SIGNATURE')),
                protocol: (string) ($this->request->server('SERVER_PROTOCOL')),
                os: new OS(
                    name: php_uname('s'),
                    release: php_uname('r'),
                    architecture: php_uname('m'),
                ),
                encoding: (string) ($this->request->server('HTTP_ACCEPT_ENCODING')),
            ),
            language: new Language(
                name: 'php',
                version: PHP_VERSION,
                expose_php: $php->get(
                    string: 'expose_php',
                ),
                display_errors: $php->get(
                    string: 'display_errors',
                ),
            ),
            request: new RequestObject(
                timestamp: Carbon::now('UTC')->format('Y-m-d H:i:s'),
                ip: (string) ($this->request->ip()),
                url: (string) ($this->request->fullUrl()),
                user_agent: (string) ($this->request->server('HTTP_USER_AGENT')),
                method: Method::from(
                    value: $this->request->method(),
                ),
                headers: $masker->mask(
                    data: collect($this->request->headers->all())->transform(
                        /* @phpstan-ignore-next-line  */
                        callback: fn ($item) => collect($item)->first(),
                    )->toArray(),
                ),
                body: $masker->mask(
                    data: $this->request->all(),
                ),
                raw: $masker->mask(
                    data: $this->request->all(),
                ),
            ),
            response: new ResponseObject(
                headers: $masker->mask(
                    data: collect($this->response->headers->all())->transform(
                        /* @phpstan-ignore-next-line  */
                        callback: fn ($item) => collect($item)->first(),
                    )->toArray(),
                ),
                code: $this->response->status(),
                size: strlen($this->response->content()),
                load_time: $this->loadTime,
                body: $responseBody,
            ),
            errors: $errors,
        );
    }
}
