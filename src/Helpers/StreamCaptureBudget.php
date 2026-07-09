<?php

declare(strict_types=1);

namespace Treblle\Laravel\Helpers;

/**
 * Process-wide memory budget shared by all in-flight stream captures.
 *
 * Each streamed response reserves bytes from this budget as its capture buffer
 * grows, and releases them when the stream ends. Once the budget is exhausted,
 * further streams are still monitored (status/headers/timing) but stop copying
 * their body — bounding total stream-capture memory regardless of how many
 * streams run concurrently (the risk under async runtimes such as Octane/Swoole).
 *
 * Bound as a container singleton so it is shared across the concurrent requests
 * of one Octane worker. Under FPM it resets per request (harmless), under
 * RoadRunner/FrankenPHP it persists per worker/thread (so reliable release is
 * essential — see StreamedResponseCapture::releaseBudget()).
 *
 * Soft limit: on a truly multithreaded runtime a race could nudge the counter
 * slightly over the max, which is harmless for a memory guard. Under Swoole
 * (cooperative, single-thread per worker) and the CLI it is exact.
 */
final class StreamCaptureBudget
{
    /** Total bytes allowed across all concurrent stream captures per process. */
    public const MAX = 32 * 1024 * 1024;

    private int $used = 0;

    /**
     * @param int $max Ceiling in bytes. Defaults to the hardcoded MAX; the
     *                 argument exists only as an internal test seam, not config.
     */
    public function __construct(
        private readonly int $max = self::MAX,
    ) {
    }

    /**
     * Reserve $bytes if they fit within the remaining budget.
     *
     * @return bool true if reserved, false if it would exceed the budget
     */
    public function tryReserve(int $bytes): bool
    {
        if ($this->used + $bytes > $this->max) {
            return false;
        }

        $this->used += $bytes;

        return true;
    }

    /**
     * Return previously reserved bytes to the budget (floored at zero).
     */
    public function release(int $bytes): void
    {
        $this->used = max(0, $this->used - $bytes);
    }

    /**
     * Bytes currently reserved across all live captures.
     */
    public function used(): int
    {
        return $this->used;
    }
}
