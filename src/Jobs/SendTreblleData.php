<?php

declare(strict_types=1);

namespace Treblle\Laravel\Jobs;

use Throwable;
use RuntimeException;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;

/**
 * Queue job for sending Treblle monitoring data asynchronously.
 *
 * Also called directly (handle() inline) for synchronous transmission,
 * keeping all HTTP transmission logic in one place.
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
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 10;

    public function __construct(
        private readonly TrebllePayloadData $payloadData,
    ) {
    }

    public function handle(): void
    {
        try {
            $jsonPayload = json_encode($this->payloadData->toArray());

            if (false === $jsonPayload) {
                throw new RuntimeException('Failed to JSON encode Treblle payload: ' . json_last_error_msg());
            }

            $compressedPayload = gzencode($jsonPayload, 6);

            if (false === $compressedPayload) {
                throw new RuntimeException('Failed to gzip compress Treblle payload');
            }

            $url = $this->getUrl();

            if (config('treblle.debug')) {
                Log::debug('Treblle: Sending payload', [
                    'url'              => $url,
                    'api_key'          => $this->payloadData->apiKey,
                    'sdk_token'        => mb_substr($this->payloadData->sdkToken, 0, 10) . '...',
                    'payload_size'     => mb_strlen($jsonPayload),
                    'compressed_size'  => mb_strlen($compressedPayload),
                    'payload'          => $this->payloadData->toArray(),
                ]);
            }

            // Free the uncompressed payload before the HTTP call
            unset($jsonPayload);

            /** @var Client $client */
            $client = app('treblle.http_client');

            $response = $client->post($url, [
                'body'    => $compressedPayload,
                'headers' => [
                    'Content-Type'     => 'application/json',
                    'Content-Encoding' => 'gzip',
                    'x-api-key'        => $this->payloadData->sdkToken,
                ],
            ]);

            if (config('treblle.debug')) {
                Log::debug('Treblle: Response received', [
                    'status_code' => $response->getStatusCode(),
                    'body'        => (string) $response->getBody(),
                ]);
            }
        } catch (Throwable $throwable) {
            if (config('treblle.debug')) {
                Log::error('Treblle: Failed to send data', [
                    'error' => $throwable->getMessage(),
                    'trace' => $throwable->getTraceAsString(),
                ]);
            }

            $this->fail($throwable);
        }
    }

    /**
     * Returns the Treblle ingress URL.
     *
     * Defaults to https://ingress.treblle.com. Override via TREBLLE_API_URL
     * for custom deployments or local testing.
     */
    private function getUrl(): string
    {
        return (string) config('treblle.url', 'https://ingress.treblle.com');
    }
}
