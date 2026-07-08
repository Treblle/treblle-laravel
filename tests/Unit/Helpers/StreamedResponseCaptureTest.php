<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\Helpers\StreamCaptureBudget;
use Treblle\Laravel\Helpers\StreamedResponseCapture;

final class StreamedResponseCaptureTest extends TestCase
{
    public function test_accumulates_appended_chunks(): void
    {
        $capture = new StreamedResponseCapture();
        $capture->append('foo');
        $capture->append('bar');

        $this->assertSame('foobar', $capture->getContent());
        $this->assertFalse($capture->isTruncated());
    }

    public function test_caps_content_at_limit_and_flags_truncation(): void
    {
        $capture = new StreamedResponseCapture(limit: 5);
        $capture->append('abc');
        $capture->append('defgh'); // only 'de' fits within the 5 byte limit

        $this->assertSame('abcde', $capture->getContent());
        $this->assertTrue($capture->isTruncated());
    }

    public function test_drops_chunks_after_truncation(): void
    {
        $capture = new StreamedResponseCapture(limit: 2);
        $capture->append('abcd');
        $capture->append('more');

        $this->assertSame('ab', $capture->getContent());
        $this->assertTrue($capture->isTruncated());
    }

    public function test_reserves_from_and_releases_to_the_budget_on_the_happy_path(): void
    {
        $budget = new StreamCaptureBudget(max: 100);
        $capture = new StreamedResponseCapture(budget: $budget);

        $capture->append('hello'); // 5 bytes
        $this->assertSame(5, $budget->used());
        $this->assertNull($capture->reason());

        $capture->releaseBudget();
        $this->assertSame(0, $budget->used()); // baseline restored
    }

    public function test_stops_capturing_when_the_budget_is_exhausted(): void
    {
        $budget = new StreamCaptureBudget(max: 4);
        $capture = new StreamedResponseCapture(budget: $budget);

        $capture->append('abcdef'); // 6 bytes > 4 budget → denied entirely

        $this->assertSame('', $capture->getContent());
        $this->assertTrue($capture->isTruncated());
        $this->assertSame('memory_budget', $capture->reason());
        $this->assertSame(0, $budget->used()); // nothing was reserved

        $capture->releaseBudget();
        $this->assertSame(0, $budget->used()); // baseline restored
    }

    public function test_partial_capture_then_budget_denial_keeps_reservation_symmetric(): void
    {
        $budget = new StreamCaptureBudget(max: 8);
        $capture = new StreamedResponseCapture(budget: $budget);

        $capture->append('abcde'); // 5 bytes reserved
        $capture->append('xyz1234'); // 7 bytes: 5 + 7 > 8 → denied, stops here

        $this->assertSame('abcde', $capture->getContent());
        $this->assertTrue($capture->isTruncated());
        $this->assertSame('memory_budget', $capture->reason());
        $this->assertSame(5, $budget->used()); // only the kept bytes are reserved

        $capture->releaseBudget();
        $this->assertSame(0, $budget->used()); // baseline restored — no drift
    }

    public function test_stream_limit_truncation_reserves_only_kept_bytes(): void
    {
        // The per-stream 2MB cap shortens the chunk; the budget must be charged
        // the shortened size, not the original — otherwise used/reserved diverge.
        $budget = new StreamCaptureBudget(max: 100);
        $capture = new StreamedResponseCapture(limit: 3, budget: $budget);

        $capture->append('abcdefghij'); // only 'abc' fits the 3 byte stream limit

        $this->assertSame('abc', $capture->getContent());
        $this->assertTrue($capture->isTruncated());
        $this->assertSame('stream_limit', $capture->reason());
        $this->assertSame(3, $budget->used()); // charged 3, not 10

        $capture->releaseBudget();
        $this->assertSame(0, $budget->used()); // baseline restored
    }

    public function test_two_streams_share_one_budget(): void
    {
        $budget = new StreamCaptureBudget(max: 6);

        $first = new StreamedResponseCapture(budget: $budget);
        $first->append('abcdef'); // fills the budget (6/6)
        $this->assertSame('abcdef', $first->getContent());

        $second = new StreamedResponseCapture(budget: $budget);
        $second->append('x'); // no room left → denied, monitored without a body
        $this->assertSame('', $second->getContent());
        $this->assertSame('memory_budget', $second->reason());

        // After the first stream ends, its budget frees up...
        $first->releaseBudget();
        $this->assertSame(0, $budget->used());

        // ...so a subsequent stream captures normally again — proving no leak.
        $third = new StreamedResponseCapture(budget: $budget);
        $third->append('yz');
        $this->assertSame('yz', $third->getContent());
        $this->assertFalse($third->isTruncated());
    }
}
