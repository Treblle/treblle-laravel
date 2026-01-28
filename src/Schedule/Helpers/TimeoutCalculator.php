<?php

declare(strict_types=1);

namespace Treblle\Laravel\Schedule\Helpers;

use Treblle\Laravel\Log\Logger;

/**
 * Timeout calculator for scheduled transmission to Treblle apis.
 *
 * Calculates timeout between two consecutive transmissions to Treblle apis
 * Purpose is to avoid continuous requests without time for sleep between them
 *
 * It will take into consideration
 * - Time needed to process all scheduled transmission
 * - Time needed for Treblle api to process all scheduled transmissions
 * - Time needed to delete all scheduled transmissions from database
 * - Adjustment for delay etc.
 * - Possibility to manually adjust timeout if needed
 *
 * Package user will be warned if total processing time exceeds configured frequency (to avoid scheduler delay by Laravel)
 */
final class TimeoutCalculator
{
    /**
     * @var int
     *          If calculated timeout is greater than this value, max timeout will be used
     */
    private const MAX_TIMEOUT = 5000000; // microseconds

    /**
     * @var int
     *          If calculated timeout is less than this value, min timeout will be used
     */
    private const MIN_TIMEOUT = 200000;

    /**
     * @var int
     *          Can be used to tweak timeout. Lower scaler means timeout increase
     */
    private const SCALER = 7;

    /**
     * @var float
     *            In seconds
     *            Time need for Treblle api's to process single request
     *            Current value is provisional
     */
    private const TREBLLE_API_PROCESSING_TIME = 0.2;

    /**
     * @var float
     *            In seconds
     *            Time need for single scheduled record to be deleted.
     *            Current value is provisional
     */
    private const SINGLE_RECORD_DELETION_TIME = 0.09;

    /**
     * @var float
     *            Correction to compensate for possible delay etc.
     *            Increases total processing time, which results in reduced timeout
     *            Current value is provisional
     */
    private const TOTAL_PROCESSING_TIME_DELTA = 0.5;

    /**
     * @var int
     *          Scheduler will run every $frequency minutes
     */
    private int $frequency;

    /**
     * @var int
     *          Size to be processes in single chunk
     */
    private int $batchSize;

    public function __construct(
        private readonly Logger $logger,
    ) {
        $this->batchSize = (int) config('treblle.schedule.batch_size');
        $this->frequency = ((int) config('treblle.schedule.frequency')) * 60;
    }

    /**
     * Calculates timeout
     * Takes in concentration min and max boundaries
     */
    public function calculateTimeout(int $totalRecords): int
    {
        $totalProcessingTime = $this->calculateTotalProcessingTime($totalRecords);

        $timeout = ($this->frequency / $totalProcessingTime) / self::SCALER;
        $this->determineOverhead($totalProcessingTime, $totalRecords, $timeout);
        $timeout = (int) ($timeout * 1000000); // convert to microseconds

        return min(max($timeout, self::MIN_TIMEOUT), self::MAX_TIMEOUT);
    }

    /**
     * Calculates total processing time for all records in treblle_schedules table
     */
    private function calculateTotalProcessingTime(int $totalRecords): float
    {
        $singleBatchDeletionTime = $this->batchSize * self::SINGLE_RECORD_DELETION_TIME;
        $totalDeletionTime = ($totalRecords / $this->batchSize) * $singleBatchDeletionTime;

        $totalTreblleApiProcessingTime = self::TREBLLE_API_PROCESSING_TIME * $totalRecords;

        return $totalDeletionTime + $totalTreblleApiProcessingTime + self::TOTAL_PROCESSING_TIME_DELTA;
    }

    /**
     * Compares total processing time with total timeout, with current frequency
     * If total needed time is greater than frequency, logs warning and suggest that frequency should be increased
     */
    private function determineOverhead(float $totalProcessingTime, int $totalRecords, $timeout): void
    {
        $totalTimeout = $totalRecords * $timeout;
        $processingTimeWithTimeout = $totalTimeout + $totalProcessingTime;
        // This dd line is intentionally left behind. Can be used to debug timeout if recalculating
        // dd($timeout, $totalTimeout, $processingTimeWithTimeout, $processingTimeWithTimeout / $this->frequency);

        $isOverhead = ($processingTimeWithTimeout / $this->frequency) > 1;

        if ($isOverhead) {
            $overheadAmount = $processingTimeWithTimeout - $this->frequency;

            $this->logger->logWarning(
                sprintf(
                    'Trebble scheduled frequency overhead detected. Configured frequency is every %d seconds,' .
                    ' but it will take %.02f seconds more to process all records. Please consider adjusting frequency',
                    $this->frequency,
                    $overheadAmount
                )
            );
        }
    }
}
