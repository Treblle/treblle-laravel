<?php

declare(strict_types=1);

namespace Treblle\Tests\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Tests\TestCase;

final class TreblleMiddlewareTest extends TestCase
{
    protected function newMiddleware(): TreblleMiddleware
    {
        return app()->make(
            abstract: TreblleMiddleware::class,
        );
    }

    /** @test */
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

    /** @test */
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
}
