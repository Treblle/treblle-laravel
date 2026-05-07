<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Treblle\Laravel\Tests\TestCase;
use Treblle\Laravel\DataTransferObject\Os;
use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Laravel\DataTransferObject\Data;
use Treblle\Laravel\Helpers\SensitiveDataMasker;
use Treblle\Laravel\Middlewares\TreblleMiddleware;
use Treblle\Laravel\DataProviders\ServerDataProvider;
use Treblle\Laravel\DataProviders\LanguageDataProvider;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;
use Treblle\Laravel\DataProviders\InMemoryErrorDataProvider;
use Treblle\Laravel\DataProviders\LaravelRequestDataProvider;
use Treblle\Laravel\DataProviders\LaravelResponseDataProvider;

/**
 * Validates that the payload sent to Treblle matches the expected schema,
 * using the NDJSON sample as the canonical reference.
 *
 * Sample: /Users/cindreta/Downloads/0035bd95a26d81bbcbc4b108a9d14e.ndjson
 */
final class PayloadStructureTest extends TestCase
{
    public function test_payload_top_level_structure_matches_schema(): void
    {
        $payload = $this->buildPayload();
        $decoded = json_decode(json_encode($payload->toArray()), true);

        // Top-level keys from the sample
        $this->assertArrayHasKey('api_key', $decoded);
        $this->assertArrayHasKey('sdk_token', $decoded);
        $this->assertArrayHasKey('sdk', $decoded);
        $this->assertArrayHasKey('version', $decoded);
        $this->assertArrayHasKey('data', $decoded);

        $this->assertIsString($decoded['api_key']);
        $this->assertIsString($decoded['sdk_token']);
        $this->assertIsString($decoded['sdk']);
        $this->assertIsNumeric($decoded['version']);
        $this->assertIsArray($decoded['data']);
    }

    public function test_data_object_matches_schema(): void
    {
        $payload = $this->buildPayload();
        $data = json_decode(json_encode($payload->toArray()), true)['data'];

        $this->assertArrayHasKey('server', $data);
        $this->assertArrayHasKey('language', $data);
        $this->assertArrayHasKey('request', $data);
        $this->assertArrayHasKey('response', $data);
        $this->assertArrayHasKey('errors', $data);

        $this->assertIsArray($data['server']);
        $this->assertIsArray($data['language']);
        $this->assertIsArray($data['request']);
        $this->assertIsArray($data['response']);
        $this->assertIsArray($data['errors']);
    }

    public function test_server_schema_matches_sample(): void
    {
        $payload = $this->buildPayload();
        $server = json_decode(json_encode($payload->toArray()), true)['data']['server'];

        // From sample: {"os":{"architecture":"x64","name":"linux","release":"..."},"protocol":"HTTP/1.1","signature":null,"software":null,"timezone":"UTC"}
        $this->assertArrayHasKey('os', $server);
        $this->assertArrayHasKey('protocol', $server);
        $this->assertArrayHasKey('timezone', $server);

        $os = $server['os'];

        $this->assertArrayHasKey('architecture', $os);
        $this->assertArrayHasKey('name', $os);
        $this->assertArrayHasKey('release', $os);
    }

    public function test_language_schema_matches_sample(): void
    {
        $payload = $this->buildPayload();
        $language = json_decode(json_encode($payload->toArray()), true)['data']['language'];

        // From sample: {"name":"node","version":"v16.19.0"} — we use php
        $this->assertArrayHasKey('name', $language);
        $this->assertArrayHasKey('version', $language);
        $this->assertIsString($language['name']);
        $this->assertIsString($language['version']);
    }

    public function test_request_schema_matches_sample(): void
    {
        $payload = $this->buildPayload();
        $request = json_decode(json_encode($payload->toArray()), true)['data']['request'];

        // From sample: {"body":{},"headers":{...},"ip":"::1","method":"GET","timestamp":"...","url":"...","user_agent":"..."}
        $this->assertArrayHasKey('body', $request);
        $this->assertArrayHasKey('headers', $request);
        $this->assertArrayHasKey('ip', $request);
        $this->assertArrayHasKey('method', $request);
        $this->assertArrayHasKey('timestamp', $request);
        $this->assertArrayHasKey('url', $request);
        $this->assertArrayHasKey('user_agent', $request);

        $this->assertIsArray($request['body']);
        $this->assertIsArray($request['headers']);
        $this->assertIsString($request['ip']);
        $this->assertIsString($request['method']);
        $this->assertIsString($request['timestamp']);
        $this->assertIsString($request['url']);
    }

    public function test_response_schema_matches_sample(): void
    {
        $payload = $this->buildPayload();
        $response = json_decode(json_encode($payload->toArray()), true)['data']['response'];

        // From sample: {"body":{...},"code":404,"headers":{...},"load_time":1549,"size":"1155"}
        $this->assertArrayHasKey('body', $response);
        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('headers', $response);
        $this->assertArrayHasKey('load_time', $response);
        $this->assertArrayHasKey('size', $response);

        $this->assertIsArray($response['body']);
        $this->assertIsInt($response['code']);
        $this->assertIsArray($response['headers']);
        $this->assertIsNumeric($response['load_time']);
        $this->assertIsNumeric($response['size']);
    }

    public function test_timestamp_format_matches_sample(): void
    {
        // Sample: "timestamp":"2026-04-16 10:48:56"
        $payload = $this->buildPayload();
        $timestamp = json_decode(json_encode($payload->toArray()), true)['data']['request']['timestamp'];

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp);
    }

    public function test_end_to_end_payload_is_built_from_middleware(): void
    {
        $httpRequest = Request::create('http://localhost:5000/api/users', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'l9explore/1.2.2',
            'HTTP_HOST' => 'localhost:5000',
        ]);

        $httpResponse = new Response(
            '{"message":"Not Found : /core/.git/config","success":false}',
            404,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );

        (new TreblleMiddleware())->terminate($httpRequest, $httpResponse);

        $this->assertTreblleRequestSent(function ($request) {
            $decompressed = gzdecode((string) $request->getBody());
            $decoded      = json_decode($decompressed, true);

            if (! isset($decoded['api_key'], $decoded['sdk_token'], $decoded['sdk'], $decoded['version'], $decoded['data'])) {
                return false;
            }

            $data = $decoded['data'];

            return isset($data['server'], $data['language'], $data['request'], $data['response'], $data['errors']);
        });
    }

    public function test_sdk_name_is_laravel(): void
    {
        $payload = $this->buildPayload();
        $decoded = json_decode(json_encode($payload->toArray()), true);

        $this->assertSame('laravel', $decoded['sdk']);
    }

    public function test_sdk_version_matches_service_provider(): void
    {
        $payload = $this->buildPayload();
        $decoded = json_decode(json_encode($payload->toArray()), true);

        $this->assertSame((float) TreblleServiceProvider::SDK_VERSION, (float) $decoded['version']);
    }

    private function buildPayload(): TrebllePayloadData
    {
        $masker = new SensitiveDataMasker(['password', 'secret', 'api_key']);
        $errorProvider = new InMemoryErrorDataProvider();

        $httpRequest = Request::create('http://localhost:5000/core/.git/config', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'l9explore/1.2.2',
            'HTTP_HOST' => 'localhost:5000',
            'HTTP_X_FORWARDED_FOR' => '185.177.72.61',
        ]);

        $httpResponse = new Response(
            '{"message":"Not Found","success":false}',
            404,
            ['content-type' => 'application/json; charset=utf-8'],
        );

        $requestProvider = new LaravelRequestDataProvider($masker, $httpRequest);
        $responseProvider = new LaravelResponseDataProvider($masker, $httpRequest, $httpResponse, $errorProvider);

        return new TrebllePayloadData(
            apiKey: 'test-api-key',
            sdkToken: 'test-sdk-token',
            sdkName: TreblleServiceProvider::SDK_NAME,
            sdkVersion: TreblleServiceProvider::SDK_VERSION,
            data: new Data(
                server: (new ServerDataProvider())->getServer(),
                language: (new LanguageDataProvider())->getLanguage(),
                request: $requestProvider->getRequest(),
                response: $responseProvider->getResponse(),
                errors: $errorProvider->getErrors(),
            ),
        );
    }
}
