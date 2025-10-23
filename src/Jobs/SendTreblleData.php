<?php

declare(strict_types=1);

namespace Treblle\Laravel\Jobs;

use Throwable;
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
     * Shared Guzzle client instance for connection pooling.
     * Reused across multiple job executions in the same worker process.
     *
     * @var Client|null
     */
    private static ?Client $client = null;

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
            // Reuse client instance for connection pooling (HTTP/2 + keep-alive)
            // Client is shared across all job executions in the same worker process
            if (null === self::$client) {
                self::$client = new Client([
                    'http_errors' => false,
                    'verify' => false,
                    'version' => 2.0, // Prefer HTTP/2, auto-fallback to HTTP/1.1
                ]);
            }

            // JSON encode and compress in one pass for better memory efficiency
            $jsonPayload = json_encode($this->payloadData->toArray());
            $compressedPayload = gzencode($jsonPayload, 6);

            $url = $this->getBaseUrl();

            // Log the payload being sent (only in debug mode)
            if ($this->payloadData->debug) {
                Log::info('Treblle: Sending payload', [
                    'url' => $url,
                    'api_key' => $this->payloadData->apiKey,
                    'sdk_token' => mb_substr($this->payloadData->sdkToken, 0, 10) . '...',
                    'payload_size' => mb_strlen($jsonPayload),
                    'compressed_size' => mb_strlen($compressedPayload),
                    'payload' => json_decode($jsonPayload, true), // Log as array for better readability
                ]);
            }

            $response = self::$client->request(
                'POST',
                $url,
                [
                    'connect_timeout' => 3,
                    'timeout' => 3,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Content-Encoding' => 'gzip',
                        'x-api-key' => $this->payloadData->sdkToken,
                        'Accept-Encoding' => 'gzip',
                    ],
                    'body' => $compressedPayload,
                ]
            );

            // Log the response (only in debug mode)
            if ($this->payloadData->debug) {
                Log::info('Treblle: Response received', [
                    'status_code' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'body' => $response->getBody()->getContents(),
                ]);
            }
        } catch (Throwable $throwable) {
            // Always log errors (important for troubleshooting)
            Log::error('Treblle: Failed to send data', [
                'error' => $throwable->getMessage(),
                'trace' => $this->payloadData->debug ? $throwable->getTraceAsString() : null,
            ]);

            if ($this->payloadData->debug) {
                throw $throwable;
            }

            // Let Laravel's queue system handle retry logic
            $this->fail($throwable);
        }
    }

    /**
     * Get the base URL for Treblle API.
     *
     * If a custom URL is provided, it will be used. Otherwise, a random
     * endpoint from the available Treblle servers is selected for load
     * balancing.
     *
     * @return string The Treblle API endpoint URL
     */
    private function getBaseUrl(): string
    {
        $urls = [
            'https://rocknrolla.treblle.com',
            'https://punisher.treblle.com',
            'https://sicario.treblle.com',
        ];

        return $this->payloadData->url ?? $urls[array_rand($urls)];
    }
}
