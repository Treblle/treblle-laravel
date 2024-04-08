<?php

declare(strict_types=1);

namespace Treblle\Tests\Middleware;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Treblle\Http\Endpoint;
use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Tests\TestCase;
use Treblle\Treblle;

final class TreblleMiddlewareTest extends TestCase
{
    /**
     * @throws BindingResolutionException
     */
    protected function newMiddleware(): TreblleMiddleware
    {
        return app()->make(
            abstract: TreblleMiddleware::class,
        );
    }

    #[Test]
    public function it_returns_a_response(): void
    {
        $request = new Request();
        $response = new Response();

        $middleware = $this->newMiddleware();

        $middlewareResponse = $middleware->handle(
            request: $request,
            next: fn () => $response,
        );

        $this->assertInstanceOf(
            expected: Response::class,
            actual: $middlewareResponse,
        );
    }

    #[Test]
    public function it_adds_trace_id_to_response(): void
    {
        $request = new Request();
        $response = new Response();

        $middleware = $this->newMiddleware();

        $middlewareResponse = $middleware->handle(
            request: $request,
            next: fn () => $response,
        );

        $this->assertArrayHasKey(
            key: 'x-treblle-trace-id',
            array: $middlewareResponse->headers->all(),
        );
    }

    #[Test]
    public function it_will_not_log_if_config_is_not_ready(): void
    {
        Treblle::log(
            endpoint: Endpoint::PUNISHER,
            data: $this->newData(),
            projectId: 'test',
        );

        Http::assertNothingSent();
    }

    #[Test]
    public function logs_error_for_response_over_2mb()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Cannot send response over 2MB to Treblle.';
            });

        $largeContent = str_repeat('a', 2 * 1024 * 1024 + 1); // Just over 2MB
        $response = new Response($largeContent);

        $request = Request::create('/test', 'GET');
        $middleware = $this->newMiddleware();

        $middleware->terminate($request, $response);
    }

    #[Test]
    public function does_not_log_for_response_under_2mb(): void
    {
        Log::shouldReceive('error')
            ->never();

        $content = 'response content'; // Well under 2MB
        $response = new Response($content);

        $request = Request::create('/test', 'GET');
        $middleware = $this->newMiddleware();

        $middleware->terminate($request, $response);
    }
}
