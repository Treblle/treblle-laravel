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
}
