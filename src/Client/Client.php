<?php

declare(strict_types=1);

namespace Treblle\Laravel\Client;

use Throwable;
use JsonException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use function Laravel\Prompts\confirm;

/**
 * Client class for sending http requests to Treblle apis.
 * Uses Laravel Http client, which is wrapper for GuzzleHttp client
 *
 * @package Treblle\Laravel\Client
 */
final class Client
{
    private bool $debugEnabled;

    public function __construct()
    {
        $this->debugEnabled = config('treblle.debug');
    }

    /**
     * Sends POST http request to Treblle api's
     * timeout 3 seconds
     * connection timeout 3 seconds
     * Logs request and response if debugging is enabled
     *
     * @param string $jsonPayload
     * @return void
     * @throws Throwable
     */
    public function send(string $jsonPayload): void
    {
        try {
            $decodedPayload = json_decode($jsonPayload, true, 512, JSON_THROW_ON_ERROR);
            $compressedPayload = $this->compressPayload($decodedPayload);

            $url = $this->getBaseUrl();

            // Log the payload being sent (only in debug mode)
            if ($this->debugEnabled) {
                Log::info('Treblle: Sending payload', [
                    'url' => $url,
                    'api_key' => $decodedPayload['api_key'],
                    'sdk_token' => mb_substr($decodedPayload['sdk_token'], 0, 10) . '...',
                    'payload_size' => mb_strlen($jsonPayload),
                    'compressed_size' => mb_strlen($compressedPayload),
                    'payload' => json_decode($jsonPayload, true), // Log as array for better readability
                ]);
            }

            // Use Laravel's HTTP client for better integration and testing
            $response = Http::timeout(3)
                ->connectTimeout(3)
                ->withoutVerifying()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Content-Encoding' => 'gzip',
                    'x-api-key' => $decodedPayload['sdk_token'],
                    'Accept-Encoding' => 'gzip',
                ])
                ->withBody($compressedPayload, 'application/json')
                ->post($url);

            // Log the response (only in debug mode)
            if ($this->debugEnabled) {
                Log::info('Treblle: Response received', [
                    'status_code' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $e) {
            // Always log errors (important for troubleshooting)
            Log::error('Treblle: Failed to send data', [
                'error' => $e->getMessage(),
                'trace' => $decodedPayload['debug'] ? $e->getTraceAsString() : null,
            ]);

            throw $e;
        }
    }

    /**
     * Encodes payload to JSON format, and compresses it to gzip
     *
     * @param array $decodedPayload
     * @return string
     * @throws JsonException
     */
    private function compressPayload(array $decodedPayload): string
    {
        $jsonPayload = json_encode($decodedPayload, JSON_THROW_ON_ERROR);

        return gzencode($jsonPayload);
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
