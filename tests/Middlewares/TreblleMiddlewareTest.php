<?php

declare(strict_types=1);

namespace Treblle\Tests\Middlewares;

use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class TreblleMiddlewareTest extends TestCase
{
    /**
     * @var TreblleMiddleware
     */
    private $treblleMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treblleMiddleware = new TreblleMiddleware();
    }

    public function testRequestWithNullValueIsMaskedCorrectly(): void
    {
        $requestWithNullField = [
            'cc' => null,
            'otherValue' => 'something',
            'password' => '1234',
        ];

        $maskedRequest = $this->treblleMiddleware->maskFields($requestWithNullField);

        $this->assertEquals($maskedRequest, [
            'cc' => null, // Should be left as null value
            'otherValue' => 'something',
            'password' => '****', // Should be masked
        ]);
    }

    public function testResponseExceptionsAreReportedAsError()
    {
        $response = new JsonResponse(['name' => 'treblle']);
        $response->withException($exception = new Exception('Some exception'));

        $this->treblleMiddleware->prepareResponseData($response);

        $payload = $this->treblleMiddleware->getPayload();
        $this->assertArrayHasKey('errors', $payload['data']);
        $this->assertEquals($payload['data']['errors'], [[
            'source' => 'onException',
            'type' => 'UNHANDLED_EXCEPTION',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]]);
    }

    public function testWhitelistedExceptionsAreNotReportedAsErrors(): void
    {
        $response = new JsonResponse(['name' => 'treblle']);
        // \Illuminate\Validation\ValidationException::class is a default entry in 'ignore_exceptions' config
        $response->withException(ValidationException::withMessages(['key' => 'Error message']));

        $this->treblleMiddleware->prepareResponseData($response);

        $payload = $this->treblleMiddleware->getPayload();
        $this->assertEmpty($payload['data']['errors']);
        $this->assertArrayHasKey('response', $payload['data']);
    }
}
