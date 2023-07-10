<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Support\Facades\Http;
use Treblle\Exceptions\ConfigurationException;
use Treblle\Exceptions\TreblleApiException;
use Treblle\Http\Endpoint;
use Treblle\Utils\DataObjects\Data;

final class Treblle
{
    public const VERSION = '4.0.0';

    /**
     * Send request and response payload to Treblle for processing.
     *
     * @param Endpoint $endpoint
     * @param Data $data
     * @param string|null $projectId
     * @return void
     * @throws ConfigurationException|TreblleApiException
     */
    public static function log(Endpoint $endpoint, Data $data, string $projectId = null): void
    {
        $treblleConfig = config('treblle');

        if (is_null($treblleConfig)) {
            return;
        }

        /** @var string $appEnvironment */
        $appEnvironment = config('app.env', 'unknownEnvironment');

        /** @var string $ignoredEnvironments */
        $ignoredEnvironments = config('treblle.ignored_environments', '');

        $ignored = explode(',', $ignoredEnvironments);

        // Check if the application environment exists in the ignored environments.
        if (in_array($appEnvironment, $ignored, true)) {
            return;
        }

        $apiKey = config('treblle.api_key');
        $configProjectId = config('treblle.project_id');

        // Check if the API key has been set
        if (is_null($apiKey)) {
            throw ConfigurationException::noApiKey();
        }

        $data = array_merge(
            [
                'api_key' => $apiKey,
                'project_id' => $projectId ?? $configProjectId,
                'version' => self::VERSION,
                'sdk' => 'laravel',
            ],
            [
                'data' => $data->__toArray(),
            ]
        );

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
