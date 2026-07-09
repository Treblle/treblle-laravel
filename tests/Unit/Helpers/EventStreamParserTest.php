<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\Helpers\EventStreamParser;

final class EventStreamParserTest extends TestCase
{
    public function test_returns_empty_array_for_blank_input(): void
    {
        $this->assertSame([], EventStreamParser::parse(''));
        $this->assertSame([], EventStreamParser::parse("\n\n"));
    }

    public function test_parses_a_single_event_with_json_data(): void
    {
        $raw = "event: message\ndata: {\"token\":\"hi\"}\n\n";

        $events = EventStreamParser::parse($raw);

        $this->assertCount(1, $events);
        $this->assertSame('message', $events[0]['event']);
        $this->assertSame(['token' => 'hi'], $events[0]['data']);
    }

    public function test_parses_multiple_events(): void
    {
        $raw = "id: 1\nevent: greeting\ndata: hello\n\nid: 2\nevent: farewell\ndata: bye\n\n";

        $events = EventStreamParser::parse($raw);

        $this->assertCount(2, $events);
        $this->assertSame('1', $events[0]['id']);
        $this->assertSame('greeting', $events[0]['event']);
        $this->assertSame('hello', $events[0]['data']);
        $this->assertSame('2', $events[1]['id']);
        $this->assertSame('farewell', $events[1]['event']);
        $this->assertSame('bye', $events[1]['data']);
    }

    public function test_joins_multiline_data_with_newline(): void
    {
        $raw = "data: line one\ndata: line two\n\n";

        $events = EventStreamParser::parse($raw);

        $this->assertSame("line one\nline two", $events[0]['data']);
    }

    public function test_keeps_non_json_data_as_string(): void
    {
        $raw = "data: </stream>\n\n";

        $events = EventStreamParser::parse($raw);

        $this->assertSame('</stream>', $events[0]['data']);
    }

    public function test_ignores_comment_lines(): void
    {
        $raw = ": this is a keep-alive comment\ndata: payload\n\n";

        $events = EventStreamParser::parse($raw);

        $this->assertCount(1, $events);
        $this->assertSame('payload', $events[0]['data']);
    }

    public function test_parses_retry_as_integer(): void
    {
        $raw = "retry: 3000\ndata: x\n\n";

        $events = EventStreamParser::parse($raw);

        $this->assertSame(3000, $events[0]['retry']);
    }

    public function test_handles_crlf_line_endings(): void
    {
        $raw = "event: message\r\ndata: hi\r\n\r\n";

        $events = EventStreamParser::parse($raw);

        $this->assertCount(1, $events);
        $this->assertSame('message', $events[0]['event']);
        $this->assertSame('hi', $events[0]['data']);
    }

    public function test_strips_only_a_single_leading_space_after_colon(): void
    {
        $raw = "data:  two-leading-spaces\n\n";

        $events = EventStreamParser::parse($raw);

        $this->assertSame(' two-leading-spaces', $events[0]['data']);
    }
}
