<?php

declare(strict_types=1);

namespace Treblle\Laravel\Helpers;

/**
 * Parses a raw Server-Sent Events (text/event-stream) body into structured events.
 *
 * Follows the SSE wire format: events are separated by a blank line, each event
 * is a set of `field: value` lines (id, event, data, retry), lines starting with
 * a colon are comments, and multiple `data:` lines are joined with a newline.
 * Each event's data is JSON-decoded when it is valid JSON, otherwise kept as a
 * string.
 *
 * @phpstan-type StreamedEvent array{id?: string, event?: string, retry?: int, data: mixed}
 */
final class EventStreamParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function parse(string $raw): array
    {
        if ('' === trim($raw)) {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $blocks = preg_split('/\n\n+/', $normalized) ?: [];

        $events = [];

        foreach ($blocks as $block) {
            $event = self::parseBlock($block);

            if (null !== $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseBlock(string $block): ?array
    {
        $id = null;
        $eventName = null;
        $retry = null;
        $dataLines = [];
        $hasField = false;

        foreach (explode("\n", $block) as $line) {
            if ('' === $line || str_starts_with($line, ':')) {
                continue; // blank line or comment
            }

            $hasField = true;

            [$field, $value] = self::splitLine($line);

            switch ($field) {
                case 'id':
                    $id = $value;

                    break;
                case 'event':
                    $eventName = $value;

                    break;
                case 'retry':
                    $retry = ctype_digit($value) ? (int) $value : null;

                    break;
                case 'data':
                    $dataLines[] = $value;

                    break;
            }
        }

        if (! $hasField) {
            return null;
        }

        $event = [];

        if (null !== $id) {
            $event['id'] = $id;
        }

        if (null !== $eventName) {
            $event['event'] = $eventName;
        }

        if (null !== $retry) {
            $event['retry'] = $retry;
        }

        $event['data'] = self::decodeData(implode("\n", $dataLines));

        return $event;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitLine(string $line): array
    {
        $colon = strpos($line, ':');

        if (false === $colon) {
            // A line with no colon is a field name with an empty value.
            return [$line, ''];
        }

        $field = substr($line, 0, $colon);
        $value = substr($line, $colon + 1);

        // A single leading space after the colon is stripped per the SSE spec.
        if (str_starts_with($value, ' ')) {
            $value = substr($value, 1);
        }

        return [$field, $value];
    }

    private static function decodeData(string $data): mixed
    {
        if ('' === $data) {
            return '';
        }

        $decoded = json_decode($data, true);

        return JSON_ERROR_NONE === json_last_error() ? $decoded : $data;
    }
}
