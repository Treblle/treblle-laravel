<?php

declare(strict_types=1);

namespace Treblle\Laravel\Monitor\Services;

use Carbon\Carbon;
use Treblle\Laravel\Config\Validator;
use Treblle\Laravel\Jobs\SendTreblleData;
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Laravel\Factories\PayloadDataFactory;
use Treblle\Laravel\Monitor\DataTransferObjects\MonitoringData;

/**
 * Service for proving monitoring, for third party API's.
 *
 * This service can be used to monitor calls to third party API's called from the system
 * I does not include any request or response data coming from third party API's
 * I relais only on response http code and configuration from treblle config file
 *
 * It will monitor following:
 *  - Number of success or failed calls
 *  - Response status codes
 *  - Total execution time of the request
 *  - Activity of third party api's
 */
final class TreblleMonitoring
{
    private bool $queueEnabled;

    private ?string $queueConnection;

    private string $queueName;

    private ?int $monitoringStartTime = null;

    private ?int $monitoringTotalTime = null;

    public function __construct(
        private readonly Validator $configValidator
    ) {
        $this->queueEnabled = (bool) config('treblle.queue.enabled', false);
        $this->queueConnection = config('treblle.queue.connection');
        $this->queueName = config('treblle.queue.queue', 'default');
    }

    /**
     * Can be used before third party API call to monitor execution time
     */
    public function stopWatchStart(): void
    {
        $this->monitoringStartTime = Carbon::now()->timestamp;
    }

    /**
     * If stopWatchStart was used, it will calculate total execution time, else 0 is returned
     */
    public function stopWatchEnd(): self
    {
        if (null === $this->monitoringStartTime) {
            $this->monitoringTotalTime = 0;

            return $this;
        }

        $this->monitoringTotalTime = Carbon::now()->timestamp - $this->monitoringStartTime;

        $this->monitoringStartTime = null;

        return $this;
    }

    /**
     * Method to be called to monitor third party api
     * Transmits data to Treblle endpoints
     */
    public function monitor(string|int $statusCode, string|int $apiId, string|int $endpointId): void
    {
        try {
            $this->configValidator->validateEnvironment();
        } catch (TreblleException) {
            return;
        }

        try {
            $this->configValidator->validateKeys();
        } catch (TreblleException) {
            return;
        }

        if ($this->queueEnabled) {
            $this->dispatchToQueue($statusCode, $apiId, $endpointId);

            return;
        }

        $this->sendSync();
    }

    /**
     * Uses Laravel Queue channels to transmit third party API's monitoring data
     */
    private function dispatchToQueue(string|int $statusCode, string|int $apiId, string|int $endpointId): void
    {
        try {
            $monitoringPayloadData = PayloadDataFactory::create(PayloadDataFactory::MONITORING);
        } catch (TreblleException) {
            return;
        }

        $monitoringPayloadData->setData(new MonitoringData(
            statusCode: is_int($statusCode) ? $statusCode : (int) $statusCode,
            time: Carbon::now()->timestamp,
            duration: $this->getMonitoringTotalTime(),
            apiId: is_string($apiId) ? $apiId : (string) $apiId,
            endpointId: is_string($endpointId) ? $endpointId : (string) $endpointId,
            config: config('treblle.monitoring') ?? [],
        ));

        // Create and dispatch job with serializable data
        $job = new SendTreblleData($monitoringPayloadData);

        if ($this->queueConnection) {
            $job->onConnection($this->queueConnection);
        }

        $job->onQueue($this->queueName);

        dispatch($job);
    }

    private function getMonitoringTotalTime(): int
    {
        $totalTime = $this->monitoringTotalTime;

        $this->monitoringTotalTime = null;

        return $totalTime;
    }

    /**
     * Transmits theirs party aPI's monitoring data synchronously
     *
     * NOTE: Not implemented at this time. It requires modification of treblle-php sdk
     */
    private function sendSync(): void {}
}
