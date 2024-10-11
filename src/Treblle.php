<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Support\Facades\Http;
use Treblle\Exceptions\ConfigurationException;
use Treblle\Http\Endpoint;
use Treblle\Utils\DataObjects\Data;

use function in_array;
use function is_string;

final class Treblle
{
    public const VERSION = '4.6.4';

    /**
     * Send request and response payload to Treblle for processing.
     *
     * @throws ConfigurationException
     */
    public static function log(null|string|Endpoint $endpoint, Data $data, ?string $projectId = null): void
    {
        $treblleConfig = (array) config('treblle');

        if ($treblleConfig['project_id'] === null || $treblleConfig['api_key'] === null) {
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

        if (null === $endpoint) {
            $finalEndpoint = Endpoint::random()->value;
        } else {
            $finalEndpoint = is_string($endpoint) ? $endpoint : $endpoint->value;
        }

        Http::withHeaders(
            headers: ['X-API-KEY' => $apiKey],
        )->withUserAgent(
            userAgent: 'Treblle\Laravel/'.self::VERSION,
        )->acceptJson()->asJson()->post(
            url: $finalEndpoint,
            data: $data,
        );
    }
}
