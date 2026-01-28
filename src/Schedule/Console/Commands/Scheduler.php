<?php

declare(strict_types=1);

namespace Treblle\Laravel\Schedule\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use Treblle\Laravel\Log\Logger;
use Treblle\Laravel\Client\Client;
use Illuminate\Database\Eloquent\Collection;
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Laravel\Schedule\Models\TreblleSchedule;
use Treblle\Laravel\Schedule\Helpers\TimeoutCalculator;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Treblle\Laravel\Schedule\Repositories\TreblleScheduleRepository;

/**
 * Command for transmitting scheduled payloads to Treblle api's.
 * It uses chunked data sets for better performance and less memory usage
 * It automatically cleans all send payloads, so database table will be clean after every run
 */
final class Scheduler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'treblle:scheduler:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transmits scheduled payloads';

    /**
     * Size to be processes in single chunk
     *
     * @var int
     */
    private int $batch_size;

    /**
     * Timeout between two consecutive transmissions
     *
     * @var int
     */
    private int $timeout;

    public function __construct(
        private readonly TreblleScheduleRepository $repository,
        private readonly Client $client,
        private readonly Logger $logger,
        private readonly TimeoutCalculator $timeoutCalculator,
    ) {
        parent::__construct();

        $this->batch_size = (int) config('treblle.schedule.batch_size');
    }

    /**
     * Executes console command
     * Fetches all stored payloads, in chunks, and transmits them to Treblle api's
     */
    public function handle(): int
    {
        $totalRecords = $this->repository->findTotalFoTransmission();

        if ($totalRecords > 0) {
            $this->timeout = $this->timeoutCalculator->calculateTimeout($totalRecords);

            $this->repository->processForTransmission($this->batch_size, [$this, 'transmit']);
        }

        return CommandAlias::SUCCESS;
    }

    /**
     * Transmits stored payloads
     */
    public function transmit(Collection $schedules): void
    {
        $processedIds = [];

        /** @var TreblleSchedule $schedule */
        foreach ($schedules as $schedule) {
            // Let's not bombard Treblle api's
            usleep($this->timeout);

            try {
                $this->client->send($schedule->payload);
            } catch (Throwable $e) {
                $this->logger->logException(sprintf('Could not sent scheduled Treblle transmission with id %d', $schedule->id), $e);

                continue;
            }

            $processedIds[] = $schedule->id;
        }

        $this->clean($processedIds);

    }

    /**
     * Deletes all sent payload records
     */
    private function clean(array $processedIds): void
    {
        try {
            $this->repository->cleanFor(ids: $processedIds);
        } catch (TreblleException $e) {
            $this->logger->logException($e->getMessage(), $e);
        }
    }
}
