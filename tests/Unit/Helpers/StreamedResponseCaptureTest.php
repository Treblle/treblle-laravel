<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
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
}
