<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\QueryCollector;
use Treblle\Laravel\DataTransferObject\Query;

final class QueryCollectorTest extends TestCase
{
    private QueryCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new QueryCollector();
    }

    public function test_starts_empty(): void
    {
        $this->assertSame([], $this->collector->all());
    }

    public function test_records_a_query(): void
    {
        $this->collector->record('select * from users', 4.5);

        $queries = $this->collector->all();

        $this->assertCount(1, $queries);
        $this->assertInstanceOf(Query::class, $queries[0]);
        $this->assertSame(['sql' => 'select * from users', 'time' => 4.5], $queries[0]->jsonSerialize());
    }

    public function test_records_multiple_queries_in_order(): void
    {
        $this->collector->record('select * from users', 1.0);
        $this->collector->record('select * from orders', 2.0);
        $this->collector->record('select * from products', 3.0);

        $queries = $this->collector->all();

        $this->assertCount(3, $queries);
        $this->assertSame('select * from users', $queries[0]->jsonSerialize()['sql']);
        $this->assertSame('select * from orders', $queries[1]->jsonSerialize()['sql']);
        $this->assertSame('select * from products', $queries[2]->jsonSerialize()['sql']);
    }

    public function test_rounds_time_to_two_decimal_places(): void
    {
        $this->collector->record('select 1', 4.5678);

        $time = $this->collector->all()[0]->jsonSerialize()['time'];

        $this->assertSame(4.57, $time);
    }

    public function test_caps_at_100_queries(): void
    {
        for ($i = 0; $i < 110; $i++) {
            $this->collector->record("select {$i}", 1.0);
        }

        $this->assertCount(100, $this->collector->all());
    }

    public function test_queries_beyond_cap_are_silently_dropped(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->collector->record("select {$i}", 1.0);
        }

        // This 101st query must not appear
        $this->collector->record('select overflow', 9.99);

        $sqls = array_map(fn (Query $q) => $q->jsonSerialize()['sql'], $this->collector->all());

        $this->assertNotContains('select overflow', $sqls);
    }

    public function test_exactly_100_queries_are_accepted(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->collector->record("select {$i}", 1.0);
        }

        $this->assertCount(100, $this->collector->all());
    }
}
