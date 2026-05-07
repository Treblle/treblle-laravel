<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\DataTransferObject;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\DataTransferObject\Os;
use Treblle\Laravel\DataTransferObject\Data;
use Treblle\Laravel\DataTransferObject\Server;
use Treblle\Laravel\DataTransferObject\Request;
use Treblle\Laravel\DataTransferObject\Language;
use Treblle\Laravel\DataTransferObject\Response;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;

final class TrebllePayloadDataTest extends TestCase
{
    private TrebllePayloadData $payload;

    protected function setUp(): void
    {
        $this->payload = new TrebllePayloadData(
            apiKey: 'test-api-key',
            sdkToken: 'test-sdk-token',
            sdkName: 'laravel',
            sdkVersion: 6.0,
            data: $this->buildData(),
        );
    }

    public function test_to_array_contains_required_keys(): void
    {
        $array = $this->payload->toArray();

        $this->assertArrayHasKey('api_key', $array);
        $this->assertArrayHasKey('sdk_token', $array);
        $this->assertArrayHasKey('sdk', $array);
        $this->assertArrayHasKey('version', $array);
        $this->assertArrayHasKey('data', $array);
    }

    public function test_to_array_maps_field_names_correctly(): void
    {
        $array = $this->payload->toArray();

        $this->assertSame('test-api-key', $array['api_key']);
        $this->assertSame('test-sdk-token', $array['sdk_token']);
        $this->assertSame('laravel', $array['sdk']);
        $this->assertSame(6.0, $array['version']);
    }

    public function test_data_is_json_serializable(): void
    {
        $array = $this->payload->toArray();

        $json = json_encode($array);

        $this->assertIsString($json);
        $this->assertJson($json);
    }

    public function test_json_encodes_to_correct_top_level_structure(): void
    {
        $json = json_encode($this->payload->toArray());
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('api_key', $decoded);
        $this->assertArrayHasKey('sdk_token', $decoded);
        $this->assertArrayHasKey('sdk', $decoded);
        $this->assertArrayHasKey('version', $decoded);
        $this->assertArrayHasKey('data', $decoded);
    }

    public function test_data_contains_required_sub_keys(): void
    {
        $json = json_encode($this->payload->toArray());
        $decoded = json_decode($json, true);

        $data = $decoded['data'];

        $this->assertArrayHasKey('server', $data);
        $this->assertArrayHasKey('language', $data);
        $this->assertArrayHasKey('request', $data);
        $this->assertArrayHasKey('response', $data);
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_request_contains_required_fields(): void
    {
        $json = json_encode($this->payload->toArray());
        $decoded = json_decode($json, true);

        $request = $decoded['data']['request'];

        $this->assertArrayHasKey('timestamp', $request);
        $this->assertArrayHasKey('url', $request);
        $this->assertArrayHasKey('ip', $request);
        $this->assertArrayHasKey('method', $request);
        $this->assertArrayHasKey('headers', $request);
        $this->assertArrayHasKey('body', $request);
    }

    public function test_response_contains_required_fields(): void
    {
        $json = json_encode($this->payload->toArray());
        $decoded = json_decode($json, true);

        $response = $decoded['data']['response'];

        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('size', $response);
        $this->assertArrayHasKey('load_time', $response);
        $this->assertArrayHasKey('body', $response);
        $this->assertArrayHasKey('headers', $response);
    }

    public function test_server_contains_os_with_required_fields(): void
    {
        $json = json_encode($this->payload->toArray());
        $decoded = json_decode($json, true);

        $server = $decoded['data']['server'];

        $this->assertArrayHasKey('os', $server);
        $this->assertArrayHasKey('name', $server['os']);
        $this->assertArrayHasKey('release', $server['os']);
        $this->assertArrayHasKey('architecture', $server['os']);
    }

    private function buildData(): Data
    {
        return new Data(
            server: new Server(
                ip: '127.0.0.1',
                timezone: 'UTC',
                software: 'nginx/1.25.0',
                protocol: 'HTTP/1.1',
                os: new Os(name: 'Linux', release: '5.15.0', architecture: 'x86_64'),
            ),
            language: new Language(name: 'php', version: PHP_VERSION),
            request: new Request(
                timestamp: '2026-04-16 10:48:56',
                url: 'http://localhost/api/users',
                ip: '::1',
                user_agent: 'TestAgent/1.0',
                method: 'GET',
                headers: ['host' => 'localhost'],
                query: [],
                body: [],
                route_path: 'api/users',
            ),
            response: new Response(
                code: 200,
                size: 55,
                load_time: 12.5,
                body: ['message' => 'ok'],
                headers: ['content-type' => 'application/json'],
            ),
            errors: [],
        );
    }
}
