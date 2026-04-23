<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\RequestInterface;
use Treblle\Laravel\TreblleServiceProvider;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /** @var array<int, array{request: RequestInterface, response: mixed}> */
    protected array $treblleSentRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Always install a recording Guzzle mock so tests never hit the real
        // network and so $this->treblleSentRequests is always populated.
        $this->mockTreblleHttpClient();
    }

    protected function getPackageProviders($app): array
    {
        return [TreblleServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('treblle.sdk_token', 'test-sdk-token');
        $app['config']->set('treblle.api_key', 'test-api-key');
        $app['config']->set('treblle.enable', true);
        $app['config']->set('treblle.debug', false);
        $app['config']->set('treblle.ignored_environments', '');
        $app['config']->set('treblle.masked_fields', ['password', 'secret', 'api_key']);
        $app['config']->set('treblle.excluded_headers', []);
        $app['config']->set('treblle.url', 'https://ingress.treblle.com');
        $app['config']->set('treblle.queue.enabled', false);
        $app['config']->set('treblle.queue.connection', null);
        $app['config']->set('treblle.queue.queue', 'default');
    }

    /**
     * Bind a recording Guzzle mock to 'treblle.http_client'.
     *
     * Resets $this->treblleSentRequests. After the code under test runs,
     * inspect $this->treblleSentRequests[0]['request'] (a PSR-7 RequestInterface)
     * or use assertTreblleRequestSent() / assertTreblleRequestNotSent().
     */
    protected function mockTreblleHttpClient(int $status = 200, string $body = ''): void
    {
        $this->treblleSentRequests = [];

        $mock  = new MockHandler([new GuzzleResponse($status, [], $body)]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->treblleSentRequests));

        $this->app->instance('treblle.http_client', new Client([
            'handler'     => $stack,
            'http_errors' => false,
        ]));
    }

    /**
     * Assert that exactly one request was sent to the Treblle ingress.
     *
     * Optionally pass a callback that receives the PSR-7 RequestInterface and
     * returns true/false — works just like Http::assertSent().
     */
    protected function assertTreblleRequestSent(?callable $callback = null): void
    {
        $this->assertNotEmpty(
            $this->treblleSentRequests,
            'Expected a request to be sent to the Treblle ingress but none was recorded.'
        );

        if (null !== $callback) {
            /** @var RequestInterface $request */
            $request = $this->treblleSentRequests[0]['request'];
            $this->assertTrue($callback($request), 'Treblle request assertion failed.');
        }
    }

    /**
     * Assert that no request was sent to the Treblle ingress.
     */
    protected function assertTreblleRequestNotSent(): void
    {
        $this->assertEmpty(
            $this->treblleSentRequests,
            sprintf(
                'Expected no request to be sent to the Treblle ingress but %d request(s) were recorded.',
                count($this->treblleSentRequests)
            )
        );
    }
}
