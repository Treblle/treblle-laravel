<?php

declare(strict_types=1);

namespace Treblle\Laravel\Console;

use Throwable;
use RuntimeException;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use GuzzleHttp\Exception\ConnectException;
use Treblle\Laravel\TreblleServiceProvider;
use Treblle\Laravel\DataTransferObject\Data;
use Treblle\Laravel\DataProviders\ServerDataProvider;
use Treblle\Laravel\DataProviders\LanguageDataProvider;
use Treblle\Laravel\DataTransferObject\TrebllePayloadData;
use Treblle\Laravel\DataTransferObject\Request as TreblleRequest;
use Treblle\Laravel\DataTransferObject\Response as TreblleResponse;

/**
 * Artisan command to verify Treblle configuration and connectivity.
 *
 * Checks that credentials are set, builds a synthetic test payload, and
 * sends it to the configured ingress endpoint — giving immediate feedback
 * that the integration is working end-to-end.
 *
 * @package Treblle\Laravel\Console
 */
final class TestCommand extends Command
{
    protected $signature = 'treblle:test';

    protected $description = 'Verify your Treblle configuration and send a test payload to the ingress';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=blue;options=bold>Treblle — Configuration Check</>');
        $this->line(str_repeat('─', 45));
        $this->newLine();

        $sdkToken = (string) config('treblle.sdk_token', '');
        $apiKey   = (string) config('treblle.api_key', '');

        $this->checkCredential('SDK Token ', 'TREBLLE_SDK_TOKEN', $sdkToken);
        $this->checkCredential('API Key   ', 'TREBLLE_API_KEY', $apiKey);
        $this->checkMonitoringEnabled();
        $this->checkEnvironment();

        $this->newLine();

        if (! $sdkToken || ! $apiKey) {
            $this->error('  Fix the missing credentials above, then re-run this command.');
            $this->newLine();

            return self::FAILURE;
        }

        $url = config('treblle.url') ?: 'https://ingress.treblle.com';

        $this->line("  Sending test payload to <fg=cyan>{$url}</> ...");
        $this->newLine();

        try {
            $payload     = $this->buildTestPayload($apiKey, $sdkToken);
            $jsonPayload = json_encode($payload->toArray());

            if (false === $jsonPayload) {
                throw new RuntimeException('Failed to JSON encode test payload: ' . json_last_error_msg());
            }

            $compressed = gzencode($jsonPayload, 6);

            if (false === $compressed) {
                throw new RuntimeException('Failed to gzip compress test payload');
            }

            /** @var Client $client */
            $client = app('treblle.http_client');

            $start    = hrtime(true);
            $response = $client->post($url, [
                'body'    => $compressed,
                'headers' => [
                    'Content-Type'     => 'application/json',
                    'Content-Encoding' => 'gzip',
                    'x-api-key'        => $sdkToken,
                ],
            ]);
            $elapsed = (int) ((hrtime(true) - $start) / 1e6);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->line("  <fg=green>✓</> Payload accepted — HTTP {$statusCode} in {$elapsed}ms");
                $this->newLine();
                $this->line('  <fg=green;options=bold>Treblle is configured correctly.</>');
            } else {
                $this->line("  <fg=yellow>!</> Unexpected response — HTTP {$statusCode} in {$elapsed}ms");
                $this->line("  <fg=yellow>!</> Body: " . mb_substr((string) $response->getBody(), 0, 200));
                $this->newLine();
                $this->warn('  Payload was sent but the ingress returned an unexpected status code.');
                $this->warn('  Check that your TREBLLE_SDK_TOKEN and TREBLLE_API_KEY are correct.');
            }

            $this->newLine();

            return $statusCode >= 200 && $statusCode < 300 ? self::SUCCESS : self::FAILURE;
        } catch (ConnectException $e) {
            $this->line("  <fg=red>✗</> Could not connect to {$url}");
            $this->line("  <fg=red>  " . $e->getMessage() . '</> ');
            $this->newLine();
            $this->error('  Check your network connection and TREBLLE_API_URL setting.');
            $this->newLine();

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->line('  <fg=red>✗</> Unexpected error: ' . $e->getMessage());
            $this->newLine();

            return self::FAILURE;
        }
    }

    private function checkCredential(string $label, string $envKey, string $value): void
    {
        if ('' !== $value) {
            $masked = '****' . mb_substr($value, -4);
            $this->line("  {$label}  <fg=green>✓</> Set ({$masked})");
        } else {
            $this->line("  {$label}  <fg=red>✗</> Not set — add {$envKey} to your .env file");
        }
    }

    private function checkMonitoringEnabled(): void
    {
        $enabled = (bool) config('treblle.enable', true);

        if ($enabled) {
            $this->line('  Monitoring   <fg=green>✓</> Enabled');
        } else {
            $this->line('  Monitoring   <fg=yellow>!</> Disabled (TREBLLE_ENABLE=false) — set to true to resume monitoring');
        }
    }

    private function checkEnvironment(): void
    {
        $env     = app()->environment();
        $ignored = array_map('trim', explode(',', (string) config('treblle.ignored_environments', '')));
        $skipped = in_array($env, array_filter($ignored), true);

        if ($skipped) {
            $this->line("  Environment  <fg=yellow>!</> <fg=yellow>{$env}</> is in ignored_environments — requests won't be monitored");
        } else {
            $this->line("  Environment  <fg=green>✓</> <fg=green>{$env}</> (not ignored)");
        }
    }

    private function buildTestPayload(string $apiKey, string $sdkToken): TrebllePayloadData
    {
        return new TrebllePayloadData(
            apiKey: $apiKey,
            sdkToken: $sdkToken,
            sdkName: TreblleServiceProvider::SDK_NAME,
            sdkVersion: TreblleServiceProvider::SDK_VERSION,
            data: new Data(
                server: (new ServerDataProvider())->getServer(),
                language: (new LanguageDataProvider())->getLanguage(),
                request: new TreblleRequest(
                    timestamp: gmdate('Y-m-d H:i:s'),
                    url: url('/treblle-test'),
                    ip: '127.0.0.1',
                    user_agent: 'Treblle Test Command (php artisan treblle:test)',
                    method: 'GET',
                    headers: ['Content-Type' => 'application/json'],
                    query: [],
                    body: [],
                    route_path: '/treblle-test',
                ),
                response: new TreblleResponse(
                    code: 200,
                    size: 2,
                    load_time: 0.0,
                    body: [],
                    headers: ['Content-Type' => 'application/json'],
                ),
                errors: [],
                metadata: [
                    'source'      => 'treblle:test',
                    'sdk_version' => TreblleServiceProvider::SDK_VERSION,
                ],
            ),
        );
    }
}
