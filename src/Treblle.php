<?php

namespace Treblle;

use GuzzleHttp\Client;
use Carbon\Carbon;
use Closure;

class Treblle {

    protected $payload;

    public function __construct() {

        $this->payload = [
            'api_key' => config('treblle.api_key'),
            'project_id' => config('treblle.project_id'),
            'version' => 0.8,
            'sdk' => 'laravel',
            'data' => [
                'server' => [
                    'ip' => null,
                    'timezone' => config('app.timezone'),
                    'os' => [
                        'name' => php_uname('s'),
                        'release' => php_uname('r'),
                        'architecture' => php_uname('m')
                    ],
                    'software' => null,
                    'signature' => null,
                    'protocol' => null,
                    'encoding' => null
                ],
                'language' => [
                    'name' => 'php',
                    'version' => phpversion(),
                    'expose_php' => $this->getIniValue('expose_php'),
                    'display_errors' => $this->getIniValue('display_errors')
                ],
                'request' => [
                    'timestamp' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                    'ip' => null,
                    'url' => null,
                    'user_agent' => null,
                    'method' => null,
                    'headers' => getallheaders(),
                    'body' => $this->maskFields($_REQUEST),
                    'raw' => $this->maskFields(json_decode(file_get_contents('php://input'), true))
                ],
                'response' => [
                    'headers' => $this->getResponseHeaders(),
                    'code' => null,
                    'size' => 0,
                    'load_time' => 0,
                    'body' => null
                ],
                'errors' => []
            ]
        ];

    }


    public function handle($request, Closure $next) {
        
        $response = $next($request);
        
        return $response;
    }

    public function terminate($request, $response) {

        if(config('treblle.exclude')) {
            if(in_array(config('app.env'), config('treblle.exclude'))) {
                exit;
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

        
        if(empty($response->exception)) {
            $this->payload['data']['response']['body'] = json_decode($response->content());
            $this->payload['data']['response']['size'] = strlen($response->content());
        } else {
            array_push($this->payload['data']['errors'],
                [
                    'source' => 'onException',
                    'type' => 'UNHANDLED_EXCEPTION',
                    'message' => $response->exception->getMessage(),
                    'file' => $response->exception->getFile(),
                    'line' => $response->exception->getLine()
                ]
            );
        }


        $guzzle = new Client;
        $guzzle->request('POST', 'https://rocknrolla.treblle.com', [
            'connect_timeout' => 10,
            'timeout' => 10,
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => config('treblle.api_key')
            ], 
            'body' => json_encode($this->payload)
        ]);

    }


    public function getLoadTime() {
        if(isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return (float) microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        } else {
            return (float) 0.0000;
        }
    }

    /**
     * Mask fields
     * @return array
     */
    public function maskFields($data) {

        $fields = [
            'password', 'pwd',  'secret', 'password_confirmation', 'cc', 'card_number', 'ccv', 'ssn',
            'credit_score'
        ];
    
        if(!is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {

            if(is_array($value)) {
                $this->maskFields($data[$key]);
            } else {
                foreach ($fields as $field) {
                    
                    if(preg_match('/\b'.$field.'\b/mi', $key)) {
                        $data[$key] = str_repeat('*', strlen($value));
                        continue;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get PHP configuration variables
     * return @string
     */
    public function getIniValue($variable) {

        $bool_value = filter_var(ini_get($variable), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if(is_bool($bool_value)) {

            if(ini_get($variable)) {
                return 'On';
            } else {
                return 'Off';
            }

        } else {
            return ini_get($variable);
        }
    }

    /**
     * Get response headers
     * 
     * return @array
     */
    public function getResponseHeaders() {
        
        $data = [];
        $headers = headers_list();

        if(is_array($headers) && ! empty($headers)) {
            foreach ($headers as $header) {
                $header = explode(':', $header);
                $data[array_shift($header)] = trim(implode(':', $header));
            }
        }

        if(empty($data)) {
            return null;
        }

        return $data;
    }

}
