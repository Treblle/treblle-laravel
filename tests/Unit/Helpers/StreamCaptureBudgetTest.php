<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\Helpers\StreamCaptureBudget;

final class StreamCaptureBudgetTest extends TestCase
{
    public function test_reserves_within_the_limit(): void
    {
        $budget = new StreamCaptureBudget(max: 100);

        $this->assertTrue($budget->tryReserve(40));
        $this->assertTrue($budget->tryReserve(60));
        $this->assertSame(100, $budget->used());
    }

    public function test_denies_a_reservation_that_would_exceed_the_limit(): void
    {
        $budget = new StreamCaptureBudget(max: 100);
        $budget->tryReserve(90);

        $this->assertFalse($budget->tryReserve(20));
        // A denied reservation must not change the counter.
        $this->assertSame(90, $budget->used());
    }

    public function test_release_frees_capacity(): void
    {
        $budget = new StreamCaptureBudget(max: 100);
        $budget->tryReserve(100);

        $this->assertFalse($budget->tryReserve(1));

        $budget->release(50);

        $this->assertSame(50, $budget->used());
        $this->assertTrue($budget->tryReserve(50));
    }

    public function test_release_floors_at_zero(): void
    {
        $budget = new StreamCaptureBudget(max: 100);
        $budget->tryReserve(10);

        $budget->release(999);

        $this->assertSame(0, $budget->used());
    }

    public function test_default_ceiling_is_the_hardcoded_constant(): void
    {
        $budget = new StreamCaptureBudget();

        $this->assertTrue($budget->tryReserve(StreamCaptureBudget::MAX));
        $this->assertFalse($budget->tryReserve(1));
    }
}
