<?php

declare(strict_types=1);

namespace Treblle\Tests\Middlewares;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Treblle\Jobs\ProcessRequest;
use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Tests\TestCase;

class TreblleMiddlewareTest extends TestCase
{
    public function testJobIsDispatched(): void
    {
        Bus::fake();

        (new TreblleMiddleware())->handle(
            request: Request::create(
                uri: 'test',
            ),
            next: fn () => new Response(),
        );

        Bus::assertDispatched(
            ProcessRequest::class,
        );
    }
}
