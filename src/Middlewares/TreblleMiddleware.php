<?php

declare(strict_types=1);

namespace Treblle\Middlewares;

use Carbon\Carbon;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class TreblleMiddleware
{
    protected $payload;

    public function __construct()
    {
        $this->payload = [
            'api_key' => config('treblle.api_key'),
            'project_id' => config('treblle.project_id'),
            'version' => 0.9,
            'sdk' => 'laravel',
            'data' => [
                'server' => [
                    'ip' => null,
                    'timezone' => config('app.timezone'),
                    'os' => [
                        'name' => php_uname('s'),
                        'release' => php_uname('r'),
                        'architecture' => php_uname('m'),
                    ],
                    'software' => null,
                    'signature' => null,
                    'protocol' => null,
                    'encoding' => null,
                ],
                'language' => [
                    'name' => 'php',
                    'version' => phpversion(),
                    'expose_php' => $this->getPHPConfigValue('expose_php'),
                    'display_errors' => $this->getPHPConfigValue('display_errors'),
                ],
                'request' => [
                    'timestamp' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                    'ip' => null,
                    'url' => null,
                    'user_agent' => null,
                    'method' => null,
                    'headers' => $this->maskFields(getallheaders()),
                    'body' => $this->maskFields($_REQUEST),
                    'raw' => $this->maskFields(json_decode(file_get_contents('php://input'), true)),
                ],
                'response' => [
                    'headers' => $this->getResponseHeaders(),
                    'code' => null,
                    'size' => 0,
                    'load_time' => 0,
                    'body' => null,
                ],
                'errors' => [],
            ],
        ];
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        /**
         * The terminate method is automatically called when the server supports the FastCGI protocol.
         * In the case the server does not support it, we fall back to manually calling the terminate method.
         *
         * @see https://laravel.com/docs/middleware#terminable-middleware
         */
        if (! str_contains(php_sapi_name(), 'fcgi') && ! $this->httpServerIsOctane()) {
            $this->terminate($request, $response);
        }

        return $response;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function terminate($request, $response)
    {
        if (! config('treblle.api_key') && config('treblle.project_id')) {
            return;
        }

        if (config('treblle.ignored_environments')) {
            if (in_array(config('app.env'), explode(',', config('treblle.ignored_environments')))) {
                return;
            }
        }

        $this->payload['data']['server']['ip'] = $request->server('SERVER_ADDR');
        $this->payload['data']['server']['software'] = $request->server('SERVER_SOFTWARE');
        $this->payload['data']['server']['signature'] = $request->server('SERVER_SIGNATURE');
        $this->payload['data']['server']['protocol'] = $request->server('SERVER_PROTOCOL');
        $this->payload['data']['server']['encoding'] = $request->server('HTTP_ACCEPT_ENCODING');

        $this->payload['data']['request']['user_agent'] = $request->server('HTTP_USER_AGENT');
        $this->payload['data']['request']['ip'] = $request->ip();
        $this->payload['data']['request']['url'] = $request->url();
        $this->payload['data']['request']['method'] = $request->method();

        $this->payload['data']['response']['load_time'] = $this->getLoadTime();
        $this->payload['data']['response']['code'] = $response->status();

        if (empty($response->exception)) {
            $this->payload['data']['response']['body'] = json_decode($response->content());
            $this->payload['data']['response']['size'] = strlen($response->content());
        } else {
            array_push(
                $this->payload['data']['errors'],
                [
                    'source' => 'onException',
                    'type' => 'UNHANDLED_EXCEPTION',
                    'message' => $response->exception->getMessage(),
                    'file' => $response->exception->getFile(),
                    'line' => $response->exception->getLine(),
                ]
            );
        }

        try {
            (new Client())
                ->request('POST', 'https://rocknrolla.treblle.com', [
                    'connect_timeout' => 1,
                    'timeout' => 1,
                    'verify' => false,
                    'http_errors' => false,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'x-api-key' => config('treblle.api_key'),
                    ],
                    'body' => json_encode($this->payload),
                ]);
        } catch (RequestException | ConnectException $e) {
        }
    }

    public function getLoadTime(): float
    {
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return (float)microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        }

        return 0.0000;
    }

    public function maskFields($data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $fields = config('treblle.masked_fields', []);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->maskFields($value);
            } else {
                foreach ($fields as $field) {
                    if (preg_match('/\b' . $field . '\b/mi', $key)) {
                        if (strtolower($field) === 'authorization') {
                            $authStringParts = explode(' ', $value);

                            if (count($authStringParts) > 1) {
                                if (in_array(strtolower($authStringParts[0]), ['basic', 'bearer', 'negotiate'])) {
                                    $data[$key] = $authStringParts[0] . ' ' . str_repeat('*', strlen($authStringParts[1]));
                                }
                            }
                        } else {
                            $data[$key] = str_repeat('*', strlen($value));
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function getPHPConfigValue($variable): string
    {
        $isBooleanValue = filter_var(ini_get($variable), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (is_bool($isBooleanValue)) {
            return ini_get($variable) ? 'On' : 'Off';
        }

        return ini_get($variable);
    }

    public function getResponseHeaders(): array
    {
        $data = [];
        $headers = headers_list();

        if (is_array($headers) && ! empty($headers)) {
            foreach ($headers as $header) {
                $header = explode(':', $header);
                $data[array_shift($header)] = trim(implode(':', $header));
            }
        }

        return $data;
    }

    /**
     * @see https://github.com/laravel/octane/blob/1.x/src/Commands/StartSwooleCommand.php#L84
     */
    private function httpServerIsOctane(): bool
    {
        return (bool) env("LARAVEL_OCTANE");
    }
}
