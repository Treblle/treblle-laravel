<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Treblle\Laravel\QueryCollector;
use Treblle\Laravel\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Treblle\Laravel\Middlewares\TreblleMiddleware;

final class QueryCaptureTest extends TestCase
{
    public function test_collector_starts_empty(): void
    {
        $collector = $this->app->make(QueryCollector::class);

        $this->assertSame([], $collector->all());
    }

    public function test_query_executed_event_populates_collector(): void
    {
        $this->fireQueryEvent('select * from users where id = ?', 4.5);

        $queries = $this->app->make(QueryCollector::class)->all();

        $this->assertCount(1, $queries);
        $this->assertSame('select * from users where id = ?', $queries[0]->jsonSerialize()['sql']);
        $this->assertSame(4.5, $queries[0]->jsonSerialize()['time']);
    }

    public function test_multiple_query_events_are_all_collected(): void
    {
        $this->fireQueryEvent('select * from users', 1.0);
        $this->fireQueryEvent('select * from orders', 2.0);
        $this->fireQueryEvent('select * from products', 3.0);

        $this->assertCount(3, $this->app->make(QueryCollector::class)->all());
    }

    public function test_null_query_time_defaults_to_zero(): void
    {
        $connection = $this->createStub(\Illuminate\Database\Connection::class);
        event(new QueryExecuted('select 1', [], null, $connection));

        $queries = $this->app->make(QueryCollector::class)->all();

        $this->assertCount(1, $queries);
        $this->assertSame(0.0, $queries[0]->jsonSerialize()['time']);
    }

    public function test_queries_key_is_present_in_payload(): void
    {
        $this->fireQueryEvent('select * from users', 2.5);

        (new TreblleMiddleware())->terminate(
            Request::create('http://localhost/api/test', 'GET'),
            new Response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
        );

        $this->assertTreblleRequestSent(function ($request) {
            $payload = json_decode(gzdecode((string) $request->getBody()), true);

            return array_key_exists('queries', $payload['data']);
        });
    }

    public function test_queries_appear_in_sent_payload(): void
    {
        $this->fireQueryEvent('select * from users where id = ?', 4.5);
        $this->fireQueryEvent('select * from orders where user_id = ?', 1.23);

        (new TreblleMiddleware())->terminate(
            Request::create('http://localhost/api/test', 'GET'),
            new Response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
        );

        $this->assertTreblleRequestSent(function ($request) {
            $payload = json_decode(gzdecode((string) $request->getBody()), true);
            $queries = $payload['data']['queries'];

            return count($queries) === 2
                && $queries[0]['sql'] === 'select * from users where id = ?'
                && $queries[0]['time'] === 4.5
                && $queries[1]['sql'] === 'select * from orders where user_id = ?'
                && $queries[1]['time'] === 1.23;
        });
    }

    public function test_no_queries_produces_empty_array_in_payload(): void
    {
        (new TreblleMiddleware())->terminate(
            Request::create('http://localhost/api/test', 'GET'),
            new Response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
        );

        $this->assertTreblleRequestSent(function ($request) {
            $payload = json_decode(gzdecode((string) $request->getBody()), true);

            return $payload['data']['queries'] === [];
        });
    }

    public function test_collector_is_fresh_for_each_scoped_resolution(): void
    {
        $collector = $this->app->make(QueryCollector::class);
        $collector->record('select 1', 1.0);

        // Simulate a new request scope
        $this->app->forgetScopedInstances();

        $freshCollector = $this->app->make(QueryCollector::class);

        $this->assertCount(0, $freshCollector->all());
    }

    private function fireQueryEvent(string $sql, float $time): void
    {
        $connection = $this->createStub(\Illuminate\Database\Connection::class);
        event(new QueryExecuted($sql, [], $time, $connection));
    }
}
