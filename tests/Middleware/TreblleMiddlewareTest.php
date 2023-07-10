<?php

declare(strict_types=1);

namespace Treblle\Tests\Middleware;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Psr\SimpleCache\InvalidArgumentException;
use Treblle\Exceptions\ConfigurationException;
use Treblle\Exceptions\TreblleApiException;
use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Tests\TestCase;

final class TreblleMiddlewareTest extends TestCase
{
    /**
     * @test
     * @return void
     */
    public function it_returns_a_response(): void
    {
        $request = new Request();
        $response = new Response();

        /**
         * @var TreblleMiddleware $middleware
         */
        $middleware = app()->make(
            abstract: TreblleMiddleware::class,
        );

        $middlewareResponse = $middleware->handle(
            request: $request,
            next: fn () => $response,
        );

        $this->assertInstanceOf(
            expected: Response::class,
            actual: $middlewareResponse,
        );
    }
}
