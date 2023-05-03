<?php

declare(strict_types=1);

namespace Treblle;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
     * @return Response
     * @throws ConfigurationException|TreblleApiException
     */
    public static function log(Endpoint $endpoint, Data $data): Response
    {
        if (empty($apiKey = config('treblle.api_key'))) {
            throw ConfigurationException::noApiKey();
        }

        $response = Http::withHeaders(
            headers: ['X-API-KEY' => $apiKey],
        )->withUserAgent(
            userAgent: 'Treblle\Laravel/' . Treblle::VERSION,
        )->acceptJson()->asJson()->post(
            url: $endpoint->value,
            data: $data->__toArray(),
        );

        if ($response->failed()) {
            throw new TreblleApiException(
                message: $response->reason(),
                previous: $response->toException(),
            );
        }

        return $response;
    }
}
