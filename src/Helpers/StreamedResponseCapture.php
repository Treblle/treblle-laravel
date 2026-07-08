<?php

declare(strict_types=1);

namespace Treblle\Laravel\Helpers;

/**
 * Accumulates the body of a streamed response as it is sent to the client.
 *
 * A Symfony StreamedResponse produces its body from a callback during send, so
 * Response::getContent() returns false and Treblle has nothing to capture in
 * terminate(). This holder is shared (by reference) between the wrapped stream
 * callback (which tees each chunk into append()) and the response data provider
 * (which reads getContent() when building the payload).
 *
 * Accumulation is capped to protect memory on long-running streams; once the cap
 * is reached further chunks are dropped and the capture is flagged as truncated.
 */
final class StreamedResponseCapture
{
    /** Matches the 2MB response body limit enforced in LaravelResponseDataProvider. */
    private const DEFAULT_LIMIT = 2 * 1024 * 1024;

    private string $content = '';

    private bool $truncated = false;

    public function __construct(
        private readonly int $limit = self::DEFAULT_LIMIT,
    ) {
    }

    public function append(string $chunk): void
    {
        if ($this->truncated) {
            return;
        }

        $remaining = $this->limit - strlen($this->content);

        if (strlen($chunk) >= $remaining) {
            $this->content .= substr($chunk, 0, max($remaining, 0));
            $this->truncated = true;

            return;
        }

        $this->content .= $chunk;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }
}
