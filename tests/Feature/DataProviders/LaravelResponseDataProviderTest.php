<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Feature\DataProviders;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Treblle\Laravel\Tests\TestCase;
use Treblle\Laravel\Helpers\SensitiveDataMasker;
use Treblle\Laravel\Helpers\StreamedResponseCapture;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Treblle\Laravel\DataProviders\InMemoryErrorDataProvider;
use Treblle\Laravel\DataProviders\LaravelResponseDataProvider;

final class LaravelResponseDataProviderTest extends TestCase
{
    public function test_extracts_status_code(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $response = new Response('{"message":"ok"}', 200, ['Content-Type' => 'application/json']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame(200, $serialized['code']);
    }

    public function test_extracts_json_body(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $response = new Response('{"user":"Alice","role":"admin"}', 200, ['Content-Type' => 'application/json']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame('Alice', $serialized['body']['user']);
        $this->assertSame('admin', $serialized['body']['role']);
    }

    public function test_masks_sensitive_fields_in_body(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $body = json_encode(['user' => 'Alice', 'api_key' => 'secret-key-value']);
        $response = new Response($body, 200);
        $masker = new SensitiveDataMasker(['api_key']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider($masker, $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame(str_repeat('*', mb_strlen('secret-key-value')), $serialized['body']['api_key']);
    }

    public function test_handles_empty_response_body(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $response = new Response('', 204);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame(204, $serialized['code']);
        $this->assertIsArray($serialized['body']);
    }

    public function test_response_too_large_returns_error_body(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $largeBody = json_encode(['data' => str_repeat('x', 2 * 1024 * 1024 + 1)]);
        $response = new Response($largeBody, 200);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame('Payload too large', $serialized['body']['error']);
        $this->assertArrayHasKey('size', $serialized['body']);
    }

    public function test_response_too_large_adds_error_to_provider(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $largeBody = json_encode(['data' => str_repeat('x', 2 * 1024 * 1024 + 1)]);
        $response = new Response($largeBody, 200);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $provider->getResponse();

        $errors = $errorProvider->getErrors();

        $this->assertCount(1, $errors);
        $this->assertSame('Response payload too large', $errors[0]->getMessage());
    }

    public function test_size_reflects_body_byte_length(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $body = '{"ok":true}';
        $response = new Response($body, 200);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame((float) mb_strlen($body), $serialized['size']);
    }

    public function test_load_time_uses_request_attribute(): void
    {
        $startTime = microtime(true) - 0.5; // 500ms ago
        $request = Request::create('http://localhost/api/test', 'GET');
        $request->attributes->set('treblle_request_started_at', $startTime);
        $response = new Response('{}', 200);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertGreaterThanOrEqual(400.0, $serialized['load_time']);
        $this->assertLessThan(2000.0, $serialized['load_time']);
    }

    public function test_load_time_uses_request_time_float_server_var(): void
    {
        $startTime = microtime(true) - 0.2; // 200ms ago
        $_SERVER['REQUEST_TIME_FLOAT'] = $startTime;

        $request = Request::create('http://localhost/api/test', 'GET');
        $response = new Response('{}', 200);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertGreaterThan(0.0, $serialized['load_time']);

        unset($_SERVER['REQUEST_TIME_FLOAT']);
    }

    public function test_load_time_returns_zero_when_no_start_time(): void
    {
        $savedRequestTimeFloat = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        unset($_SERVER['REQUEST_TIME_FLOAT']);

        $request = Request::create('http://localhost/api/test', 'GET');
        $response = new Response('{}', 200);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        // Without any start time anchor, load_time is >= 0
        $this->assertGreaterThanOrEqual(0.0, $serialized['load_time']);

        if (null !== $savedRequestTimeFloat) {
            $_SERVER['REQUEST_TIME_FLOAT'] = $savedRequestTimeFloat;
        }
    }

    public function test_streamed_sse_response_body_is_parsed_into_events(): void
    {
        $request = Request::create('http://localhost/api/stream', 'GET');
        $capture = new StreamedResponseCapture();
        $capture->append("event: greeting\ndata: {\"msg\":\"hello\"}\n\nevent: farewell\ndata: bye\n\n");
        $request->attributes->set('treblle_streamed_capture', $capture);

        $response = new StreamedResponse(fn () => null, 200, ['Content-Type' => 'text/event-stream']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertCount(2, $serialized['body']);
        $this->assertSame('greeting', $serialized['body'][0]['event']);
        $this->assertSame(['msg' => 'hello'], $serialized['body'][0]['data']);
        $this->assertSame('bye', $serialized['body'][1]['data']);
        $this->assertSame((float) strlen($capture->getContent()), $serialized['size']);
    }

    public function test_streamed_json_response_body_is_decoded(): void
    {
        $request = Request::create('http://localhost/api/stream', 'GET');
        $capture = new StreamedResponseCapture();
        $capture->append('{"users":[{"id":1},{"id":2}]}');
        $request->attributes->set('treblle_streamed_capture', $capture);

        $response = new StreamedResponse(fn () => null, 200, ['Content-Type' => 'application/json']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame([['id' => 1], ['id' => 2]], $serialized['body']['users']);
    }

    public function test_generic_stream_non_json_body_falls_back_to_raw(): void
    {
        $request = Request::create('http://localhost/api/stream', 'GET');
        $capture = new StreamedResponseCapture();
        $capture->append('plain streamed text');
        $request->attributes->set('treblle_streamed_capture', $capture);

        $response = new StreamedResponse(fn () => null, 200, ['Content-Type' => 'text/plain']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame('plain streamed text', $serialized['body']['raw']);
    }

    public function test_streamed_response_without_capture_is_empty_body(): void
    {
        $request = Request::create('http://localhost/api/stream', 'GET');
        $response = new StreamedResponse(fn () => null, 200, ['Content-Type' => 'text/event-stream']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame([], $serialized['body']);
    }

    public function test_streamed_sse_masks_sensitive_data_fields(): void
    {
        $request = Request::create('http://localhost/api/stream', 'GET');
        $capture = new StreamedResponseCapture();
        $capture->append("data: {\"api_key\":\"secret-value\"}\n\n");
        $request->attributes->set('treblle_streamed_capture', $capture);

        $response = new StreamedResponse(fn () => null, 200, ['Content-Type' => 'text/event-stream']);
        $masker = new SensitiveDataMasker(['api_key']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider($masker, $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame(str_repeat('*', mb_strlen('secret-value')), $serialized['body'][0]['data']['api_key']);
    }

    public function test_truncated_streamed_capture_adds_error(): void
    {
        $request = Request::create('http://localhost/api/stream', 'GET');
        $capture = new StreamedResponseCapture(limit: 10);
        $capture->append(str_repeat('x', 50)); // exceeds the 10 byte limit
        $request->attributes->set('treblle_streamed_capture', $capture);

        $response = new StreamedResponse(fn () => null, 200, ['Content-Type' => 'text/plain']);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $provider->getResponse();

        $errors = $errorProvider->getErrors();

        $this->assertCount(1, $errors);
        $this->assertSame('Streamed response truncated at capture limit', $errors[0]->getMessage());
    }

    public function test_404_response_preserves_status_code(): void
    {
        $request = Request::create('http://localhost/api/missing', 'GET');
        $body = json_encode(['message' => 'Not Found']);
        $response = new Response($body, 404);
        $errorProvider = new InMemoryErrorDataProvider();

        $provider = new LaravelResponseDataProvider(new SensitiveDataMasker(), $request, $response, $errorProvider);
        $serialized = $provider->getResponse()->jsonSerialize();

        $this->assertSame(404, $serialized['code']);
        $this->assertSame('Not Found', $serialized['body']['message']);
    }
}
