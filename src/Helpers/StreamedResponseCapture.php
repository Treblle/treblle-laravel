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

    /**
     * Bytes reserved from the shared budget so far. Must always equal the number
     * of bytes we handed to StreamCaptureBudget::tryReserve() and kept, so that
     * releaseBudget() returns exactly what was taken. Diverging here silently
     * drifts the process-wide budget (see releaseBudget()).
     */
    private int $reserved = 0;

    /** Why capture stopped growing: 'stream_limit' (2MB) or 'memory_budget'. */
    private ?string $reason = null;

    public function __construct(
        private readonly int $limit = self::DEFAULT_LIMIT,
        private readonly ?StreamCaptureBudget $budget = null,
    ) {
    }

    public function append(string $chunk): void
    {
        if ($this->truncated) {
            return;
        }

        // 1. Per-stream 2MB cap: shorten the chunk to what fits, if needed.
        $remaining = $this->limit - strlen($this->content);

        if (strlen($chunk) >= $remaining) {
            $chunk = substr($chunk, 0, max($remaining, 0));
            $this->truncated = true;
            $this->reason ??= 'stream_limit';
        }

        if ('' === $chunk) {
            return;
        }

        // 2. Shared memory budget: reserve exactly the (already-capped) size we
        // intend to keep. If it doesn't fit, stop capturing this stream's body.
        if (null !== $this->budget && ! $this->budget->tryReserve(strlen($chunk))) {
            $this->truncated = true;
            $this->reason ??= 'memory_budget';

            return;
        }

        $this->content .= $chunk;
        $this->reserved += strlen($chunk);
    }

    /**
     * Return this capture's reserved bytes to the shared budget. Idempotent so it
     * is safe to call once in the middleware's guaranteed finally block.
     */
    public function releaseBudget(): void
    {
        $this->budget?->release($this->reserved);
        $this->reserved = 0;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
