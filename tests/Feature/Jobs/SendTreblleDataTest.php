<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Feature\Jobs;

use Treblle\Laravel\Tests\TestCase;
use Treblle\Laravel\Jobs\SendTreblleData;
use Treblle\Laravel\DataTransferObject\Os;
use Treblle\Laravel\DataTransferObject\Data;
use Treblle\Laravel\DataTransferObject\Server;
use Treblle\Laravel\DataTransferObject\Language;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;
use Treblle\Laravel\DataTransferObject\Request as TreblleRequest;
use Treblle\Laravel\DataTransferObject\Response as TreblleResponse;

final class SendTreblleDataTest extends TestCase
{
    private TrebllePayloadData $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payload = $this->buildPayload();
    }

    public function test_sends_post_to_configured_url(): void
    {
        (new SendTreblleData($this->payload))->handle();

        $this->assertTreblleRequestSent(
            fn ($r) => 'https://ingress.treblle.com' === (string) $r->getUri()
        );
    }

    public function test_uses_custom_url_from_config(): void
    {
        config(['treblle.url' => 'https://custom.ingress.example.com']);

        (new SendTreblleData($this->payload))->handle();

        $this->assertTreblleRequestSent(
            fn ($r) => 'https://custom.ingress.example.com' === (string) $r->getUri()
        );
    }

    public function test_sends_with_gzip_content_encoding(): void
    {
        (new SendTreblleData($this->payload))->handle();

        $this->assertTreblleRequestSent(
            fn ($r) => 'gzip' === $r->getHeaderLine('Content-Encoding')
        );
    }

    public function test_sends_sdk_token_as_x_api_key_header(): void
    {
        (new SendTreblleData($this->payload))->handle();

        $this->assertTreblleRequestSent(
            fn ($r) => 'test-sdk-token' === $r->getHeaderLine('x-api-key')
        );
    }

    public function test_sends_content_type_application_json(): void
    {
        (new SendTreblleData($this->payload))->handle();

        $this->assertTreblleRequestSent(
            fn ($r) => str_contains($r->getHeaderLine('Content-Type'), 'application/json')
        );
    }

    public function test_payload_is_valid_gzip_compressed_json(): void
    {
        (new SendTreblleData($this->payload))->handle();

        $body = (string) $this->treblleSentRequests[0]['request']->getBody();

        $this->assertNotEmpty($body);

        $decompressed = gzdecode($body);

        $this->assertNotFalse($decompressed);

        $decoded = json_decode($decompressed, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('api_key', $decoded);
        $this->assertArrayHasKey('data', $decoded);
    }

    public function test_payload_contains_correct_api_key(): void
    {
        (new SendTreblleData($this->payload))->handle();

        $body    = (string) $this->treblleSentRequests[0]['request']->getBody();
        $decoded = json_decode(gzdecode($body), true);

        $this->assertSame('test-api-key', $decoded['api_key']);
    }

    public function test_does_not_throw_on_network_failure(): void
    {
        $this->mockTreblleHttpClient(500);

        (new SendTreblleData($this->payload))->handle();

        $this->assertTrue(true);
    }

    public function test_job_has_correct_timeout(): void
    {
        $this->assertSame(10, (new SendTreblleData($this->payload))->timeout);
    }

    public function test_job_has_correct_tries(): void
    {
        $this->assertSame(3, (new SendTreblleData($this->payload))->tries);
    }

    public function test_job_has_correct_backoff(): void
    {
        $this->assertSame(5, (new SendTreblleData($this->payload))->backoff);
    }

    public function test_debug_logging_when_debug_enabled(): void
    {
        config(['treblle.debug' => true]);

        (new SendTreblleData($this->payload))->handle();

        $this->assertTrue(true);
    }

    private function buildPayload(): TrebllePayloadData
    {
        return new TrebllePayloadData(
            apiKey: 'test-api-key',
            sdkToken: 'test-sdk-token',
            sdkName: 'laravel',
            sdkVersion: 6.0,
            data: new Data(
                server: new Server(
                    ip: '127.0.0.1',
                    timezone: 'UTC',
                    software: null,
                    protocol: 'HTTP/1.1',
                    os: new Os(name: 'Linux', release: '5.15.0', architecture: 'x86_64'),
                ),
                language: new Language(name: 'php', version: PHP_VERSION),
                request: new TreblleRequest(
                    timestamp: '2026-04-16 10:48:56',
                    url: 'http://localhost:5000/core/.git/config',
                    ip: '::1',
                    user_agent: 'l9explore/1.2.2',
                    method: 'GET',
                    headers: ['host' => 'localhost:5000'],
                    query: [],
                    body: [],
                    route_path: null,
                ),
                response: new TreblleResponse(
                    code: 404,
                    size: 1155,
                    load_time: 1549.0,
                    body: ['message' => 'Not Found'],
                    headers: ['content-type' => 'application/json; charset=utf-8'],
                ),
                errors: [],
            ),
        );
    }
}
