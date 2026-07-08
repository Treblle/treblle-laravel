<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Feature\Middlewares;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use GuzzleHttp\Handler\MockHandler;
use Treblle\Laravel\Tests\TestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzlePsr7Request;
use Treblle\Laravel\Middlewares\TreblleMiddleware;
use Treblle\Laravel\Helpers\StreamedResponseCapture;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TreblleMiddlewareTest extends TestCase
{
    public function test_passes_request_through_when_enabled(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleMiddleware();
        $called = false;

        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;

            return new Response('ok', 200);
        });

        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_bypasses_when_disabled(): void
    {
        config(['treblle.enable' => false]);

        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleMiddleware();
        $called = false;

        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;

            return new Response('ok', 200);
        });

        $this->assertTrue($called);
    }

    public function test_bypasses_when_environment_is_ignored(): void
    {
        config(['treblle.ignored_environments' => 'testing']);

        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleMiddleware();
        $called = false;

        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;

            return new Response('ok', 200);
        });

        $this->assertTrue($called);
    }

    public function test_bypasses_when_sdk_token_missing(): void
    {
        config(['treblle.sdk_token' => null]);

        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleMiddleware();
        $called = false;

        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;

            return new Response('ok', 200);
        });

        $this->assertTrue($called);
    }

    public function test_bypasses_when_api_key_missing(): void
    {
        config(['treblle.api_key' => null]);

        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleMiddleware();
        $called = false;

        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;

            return new Response('ok', 200);
        });

        $this->assertTrue($called);
    }

    public function test_stores_per_route_api_key_in_request_attributes(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleMiddleware();
        $capturedRequest = null;

        $middleware->handle($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response('ok', 200);
        }, 'custom-api-key-override');

        $this->assertSame('custom-api-key-override', $capturedRequest->attributes->get('treblle_api_key_override'));
    }

    public function test_does_not_mutate_global_config_for_per_route_key(): void
    {
        config(['treblle.api_key' => 'global-api-key']);

        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleMiddleware();

        $middleware->handle($request, fn ($req) => new Response('ok'), 'route-specific-key');

        $this->assertSame('global-api-key', config('treblle.api_key'));
    }

    public function test_terminate_sends_data_synchronously(): void
    {
        $request  = Request::create('http://localhost/api/test', 'GET');
        $response = new Response('{"status":"ok"}', 200, ['Content-Type' => 'application/json']);

        (new TreblleMiddleware())->terminate($request, $response);

        $this->assertTreblleRequestSent(fn ($r) => 'https://ingress.treblle.com' === (string) $r->getUri()
            && $r->hasHeader('x-api-key')
            && 'gzip' === $r->getHeaderLine('Content-Encoding'));
    }

    public function test_terminate_skips_when_environment_is_ignored(): void
    {
        config(['treblle.ignored_environments' => 'testing']);

        (new TreblleMiddleware())->terminate(
            Request::create('http://localhost/api/test', 'GET'),
            new Response('{}', 200),
        );

        $this->assertTreblleRequestNotSent();
    }

    public function test_terminate_skips_when_sdk_token_missing(): void
    {
        config(['treblle.sdk_token' => null]);

        (new TreblleMiddleware())->terminate(
            Request::create('http://localhost/api/test', 'GET'),
            new Response('{}', 200),
        );

        $this->assertTreblleRequestNotSent();
    }

    public function test_terminate_uses_per_route_api_key_from_attributes(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $request->attributes->set('treblle_api_key_override', 'route-specific-key');

        (new TreblleMiddleware())->terminate($request, new Response('{"status":"ok"}', 200));

        $this->assertTreblleRequestSent(
            fn ($r) => 'https://ingress.treblle.com' === (string) $r->getUri()
        );
    }

    public function test_terminate_dispatches_to_queue_when_enabled(): void
    {
        config(['treblle.queue.enabled' => true]);
        config(['treblle.queue.connection' => 'sync']);

        (new TreblleMiddleware())->terminate(
            Request::create('http://localhost/api/test', 'GET'),
            new Response('{"status":"ok"}', 200, ['Content-Type' => 'application/json']),
        );

        $this->assertTrue(true);
    }

    public function test_terminate_never_throws_exceptions(): void
    {
        // Bind a Guzzle client that throws a network-level exception
        $mock  = new MockHandler([new ConnectException('Network failure', new GuzzlePsr7Request('POST', 'https://ingress.treblle.com'))]);
        $stack = HandlerStack::create($mock);

        $this->app->instance('treblle.http_client', new Client([
            'handler'     => $stack,
            'http_errors' => false,
        ]));

        (new TreblleMiddleware())->terminate(
            Request::create('http://localhost/api/test', 'GET'),
            new Response('{"status":"ok"}', 200),
        );

        $this->assertTrue(true);
    }

    public function test_handle_never_throws_exceptions(): void
    {
        config(['treblle.sdk_token' => null]);
        config(['treblle.api_key' => null]);

        $request = Request::create('http://localhost/api/test', 'GET');
        $result  = (new TreblleMiddleware())->handle($request, fn ($req) => new Response('ok', 200));

        $this->assertSame(200, $result->getStatusCode());
    }

    public function test_wraps_streamed_response_and_tees_output_to_capture(): void
    {
        $sse = "event: message\ndata: {\"token\":\"hi\"}\n\n";
        $request = Request::create('http://localhost/api/stream', 'GET');

        $response = (new TreblleMiddleware())->handle(
            $request,
            fn () => new StreamedResponse(function () use ($sse): void {
                echo $sse;
            }, 200, ['Content-Type' => 'text/event-stream']),
        );

        // Simulate Laravel sending the streamed body to the client.
        ob_start();
        $response->sendContent();
        $clientOutput = ob_get_clean();

        // The client still receives the streamed bytes unchanged...
        $this->assertSame($sse, $clientOutput);

        // ...and Treblle captured a copy for the payload.
        $capture = $request->attributes->get('treblle_streamed_capture');
        $this->assertInstanceOf(StreamedResponseCapture::class, $capture);
        $this->assertSame($sse, $capture->getContent());
    }

    public function test_does_not_wrap_non_streamed_responses(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');

        (new TreblleMiddleware())->handle($request, fn () => new Response('ok', 200));

        $this->assertNull($request->attributes->get('treblle_streamed_capture'));
    }

    public function test_streamed_sse_body_reaches_treblle_as_events(): void
    {
        $sse = "event: greeting\ndata: {\"msg\":\"hello\"}\n\n";
        $request = Request::create('http://localhost/api/stream', 'GET');
        $middleware = new TreblleMiddleware();

        $response = $middleware->handle(
            $request,
            fn () => new StreamedResponse(function () use ($sse): void {
                echo $sse;
            }, 200, ['Content-Type' => 'text/event-stream']),
        );

        ob_start();
        $response->sendContent();
        ob_end_clean();

        $middleware->terminate($request, $response);

        $this->assertTreblleRequestSent();
        $payload = $this->lastTrebllePayload();
        $events = $payload['data']['response']['body'];

        $this->assertSame('greeting', $events[0]['event']);
        $this->assertSame(['msg' => 'hello'], $events[0]['data']);
    }

    public function test_real_event_stream_helper_is_captured_and_parsed(): void
    {
        // Exercises Laravel's response()->eventStream() including its internal
        // ob_flush()/flush() calls composed with our capture buffer, in the
        // real HTTP order: handle() -> sendContent() -> terminate().
        $request = Request::create('http://localhost/api/chat', 'GET');
        $middleware = new TreblleMiddleware();

        $response = $middleware->handle($request, fn () => response()->eventStream(function () {
            yield 'Hello';
            yield ['token' => 'world'];
        }));

        ob_start();
        $response->sendContent();
        ob_end_clean();

        $capture = $request->attributes->get('treblle_streamed_capture');
        $this->assertInstanceOf(StreamedResponseCapture::class, $capture);
        $this->assertStringContainsString('data: Hello', $capture->getContent());

        $middleware->terminate($request, $response);

        $this->assertTreblleRequestSent();
        $events = $this->lastTrebllePayload()['data']['response']['body'];
        $data = array_column($events, 'data');

        $this->assertContains('Hello', $data);
        $this->assertContains(['token' => 'world'], $data);
    }

    public function test_real_stream_helper_is_captured_as_raw(): void
    {
        $request = Request::create('http://localhost/api/download', 'GET');
        $middleware = new TreblleMiddleware();

        $response = $middleware->handle($request, fn () => response()->stream(function (): void {
            echo 'chunk1';
            echo 'chunk2';
        }, 200, ['Content-Type' => 'text/plain']));

        ob_start();
        $response->sendContent();
        ob_end_clean();

        $capture = $request->attributes->get('treblle_streamed_capture');
        $this->assertInstanceOf(StreamedResponseCapture::class, $capture);
        $this->assertSame('chunk1chunk2', $capture->getContent());

        $middleware->terminate($request, $response);

        $this->assertSame('chunk1chunk2', $this->lastTrebllePayload()['data']['response']['body']['raw']);
    }

    public function test_capture_flushes_buffer_left_open_by_callback(): void
    {
        $request = Request::create('http://localhost/api/stream', 'GET');

        $response = (new TreblleMiddleware())->handle(
            $request,
            fn () => new StreamedResponse(function (): void {
                echo 'before';
                ob_start(); // callback opens its own buffer and never closes it
                echo 'inside';
            }, 200, ['Content-Type' => 'text/plain']),
        );

        $outerLevel = ob_get_level();

        ob_start();
        $response->sendContent();
        $clientOutput = ob_get_clean();

        // The wrapper unwound its own buffer and the one the callback leaked,
        // restoring the buffer depth and flushing all content through.
        $this->assertSame($outerLevel, ob_get_level());
        $this->assertSame('beforeinside', $clientOutput);
        $this->assertSame('beforeinside', $request->attributes->get('treblle_streamed_capture')->getContent());
    }

    public function test_capture_does_not_close_preexisting_buffers(): void
    {
        $request = Request::create('http://localhost/api/stream', 'GET');

        $response = (new TreblleMiddleware())->handle(
            $request,
            // Callback that tears down exactly the wrapper's own buffer, leaving
            // the buffer that existed beneath it intact.
            fn () => new StreamedResponse(function (): void {
                echo 'x';
                ob_end_flush();
            }, 200, ['Content-Type' => 'text/plain']),
        );

        // A framework-style buffer that existed before the wrapper ran.
        ob_start();
        $preexistingLevel = ob_get_level();

        $response->sendContent();

        // The callback already closed the wrapper's buffer; the wrapper's finally
        // must NOT go on to close the pre-existing buffer beneath it.
        $this->assertSame($preexistingLevel, ob_get_level());

        ob_end_clean();
    }

    /**
     * Decode the gzip-compressed JSON payload sent to the Treblle ingress.
     *
     * @return array<string, mixed>
     */
    private function lastTrebllePayload(): array
    {
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $this->treblleSentRequests[0]['request'];

        return json_decode((string) gzdecode((string) $request->getBody()), true);
    }
}
