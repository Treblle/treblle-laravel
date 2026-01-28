<?php

declare(strict_types=1);

namespace Treblle\Laravel\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Treblle\Laravel\Client\Facade\Client;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;

/**
 * Queue job for sending Treblle monitoring data asynchronously.
 *
 * @package Treblle\Laravel\Jobs
 */
final class SendTreblleData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 10;

    /**
     * Create a new job instance.
     *
     * @param TrebllePayloadData $payloadData The pre-extracted Treblle payload data
     */
    public function __construct(
        private readonly TrebllePayloadData $payloadData
    ) {
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle(): void
    {
        try {
            // JSON encode and compress in one pass for better memory efficiency
            $jsonPayload = json_encode($this->payloadData->toArray());

            Client::send($jsonPayload);
        } catch (Throwable $throwable) {
            if ($this->payloadData->debug) {
                throw $throwable;
            }

            // Let Laravel's queue system handle retry logic
            $this->fail($throwable);
        }
    }
}
