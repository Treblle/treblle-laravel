<?php

declare(strict_types=1);

namespace Treblle\Laravel;

use Treblle\Laravel\DataTransferObject\Query;

/**
 * Collects SQL queries executed during a single request lifecycle.
 *
 * Registered as a scoped binding so it resets cleanly between requests
 * under both standard Laravel and Octane.
 */
final class QueryCollector
{
    private const LIMIT = 100;

    /** @var list<Query> */
    private array $queries = [];

    public function __construct()
    {
    }

    public function record(string $sql, float $time): void
    {
        if (count($this->queries) >= self::LIMIT) {
            return;
        }

        $this->queries[] = new Query(sql: $sql, time: round($time, 2));
    }

    /** @return list<Query> */
    public function all(): array
    {
        return $this->queries;
    }
}
