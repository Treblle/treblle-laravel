<?php

declare(strict_types=1);

namespace Treblle\Tests\Middleware;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Treblle\Http\Endpoint;
use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Tests\TestCase;
use Treblle\Treblle;
use Treblle\Utils\DataObjects\Data;

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

    /** @test */
    public function it_will_not_log_if_config_is_not_ready(): void
    {
        Treblle::log(
            endpoint: Endpoint::PUNISHER,
            data: $this->newData(),
            projectId: 'test',
        );

        Http::assertNothingSent();
    }
}
