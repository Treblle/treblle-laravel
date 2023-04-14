<?php

declare(strict_types=1);

namespace Treblle\Tests\Middlewares;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Tests\TestCase;
use Illuminate\Config\Repository as Config;

class TreblleMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $container = Container::setInstance(new Container());

        Facade::setFacadeApplication($container);

        $container->singleton('config', fn () => $this->createConfig());

        parent::setUp();
    }

    public function getMiddleware(): TreblleMiddleware
    {
        return $this->app->make(
            abstract: TreblleMiddleware::class,
        );
    }

    public function testJobIsDispatched(): void
    {
        Http::fake();

        $this->getMiddleware()->handle(
            request: Request::create(
                uri: 'test',
            ),
            next: fn () => new Response(
                content: json_encode($this->fixture(
                    name: 'projects/create',
                ), JSON_THROW_ON_ERROR)
            ),
        );

        Http::assertSentCount(1);
    }

    public function testIgnoredUrlNotMonitored()
    {
        Http::fake();

        $this->getMiddleware()->handle(
            request: Request::create(
                uri: 'horizon',
            ),
            next: fn () => new Response(
                content: json_encode($this->fixture(
                    name: 'projects/create',
                ), JSON_THROW_ON_ERROR)
            ),
        );

        Http::assertNothingSent();
    }

    protected function createConfig(): Config
    {
        return new Config([
            'treblle' => [
                'ignore_urls' => [
                    'telescope*',
                    'horizon*',
                    'nova*'
                ],
            ],
        ]);
    }
}
