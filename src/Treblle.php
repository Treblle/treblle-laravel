<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Treblle\Core\Http\Endpoint;
use Treblle\Exceptions\ConfigurationException;
use Treblle\Exceptions\TreblleApiException;
use Treblle\Utils\DataObjects\Data;

final class Treblle
{
    public const VERSION = '4.0.0';

    /**
     * Send request and response payload to Treblle for processing.
     *
     * @param Endpoint $endpoint
     * @param Data $data
     * @return void
     * @throws ConfigurationException|TreblleApiException
     */
    public static function log(Endpoint $endpoint, Data $data, string $projectId = null): void
    {
        // Check if the application environment exists in the ignored environments.
        if (in_array(config('app.env'), explode(',', config('treblle.ignored_environments')), true)) {
            return;
        }

        // Check if the API key has been set
        if (empty($apiKey = config('treblle.api_key'))) {
            throw ConfigurationException::noApiKey();
        }

        $data = array_merge([
            'api_key' => $apiKey,
            'project_id' => $projectId ?? config('treblle.project_id'),
            'version' => self::VERSION,
            'sdk' => 'laravel',
        ], ['data' => $data->__toArray()]);

        $response = Http::withHeaders(
            headers: ['X-API-KEY' => $apiKey],
        )->withUserAgent(
            userAgent: 'Treblle\Laravel/' . self::VERSION,
        )->acceptJson()->asJson()->post(
            url: $endpoint->value,
            data: $data,
        );

        if ($response->failed()) {
            throw new TreblleApiException(
                message: $response->reason(),
                previous: $response->toException(),
            );
        }
    }
}
