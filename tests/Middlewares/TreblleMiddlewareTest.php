<?php

declare(strict_types=1);

namespace Treblle\Tests\Middlewares;

use Treblle\Middlewares\TreblleMiddleware;
use Treblle\Tests\TestCase;

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
}
