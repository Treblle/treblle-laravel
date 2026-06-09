<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\DataTransferObject;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\DataTransferObject\Query;

final class QueryTest extends TestCase
{
    public function test_json_serialize_returns_sql_and_time(): void
    {
        $query = new Query(sql: 'select * from users where id = ?', time: 4.5);

        $serialized = $query->jsonSerialize();

        $this->assertSame('select * from users where id = ?', $serialized['sql']);
        $this->assertSame(4.5, $serialized['time']);
    }

    public function test_json_serialize_contains_only_sql_and_time_keys(): void
    {
        $query = new Query(sql: 'select 1', time: 1.0);

        $this->assertSame(['sql', 'time'], array_keys($query->jsonSerialize()));
    }

    public function test_json_encodes_correctly(): void
    {
        $query = new Query(sql: 'insert into orders (user_id) values (?)', time: 12.34);
        $json = json_encode($query);

        $this->assertIsString($json);

        $decoded = json_decode($json, true);

        $this->assertSame('insert into orders (user_id) values (?)', $decoded['sql']);
        $this->assertSame(12.34, $decoded['time']);
    }

    public function test_time_is_preserved_as_float(): void
    {
        $query = new Query(sql: 'select 1', time: 0.5);

        $this->assertIsFloat($query->jsonSerialize()['time']);
    }
}
