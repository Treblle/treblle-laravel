<?php

declare(strict_types=1);

namespace Treblle\Tests\Jobs;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Treblle\Contracts\TreblleClientContract;
use Treblle\Core\Contracts\Masking\MaskingContract;
use Treblle\Core\DataObjects\Data;
use Treblle\Core\Http\Method;
use Treblle\Jobs\ProcessRequest;
use Treblle\Tests\TestCase;

class ProcessRequestTest extends TestCase
{
    private Request $request;

    private Response $response;

    private MaskingContract $masker;

    private ProcessRequest $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = Request::create(
            uri: 'https://api.treblle.com/test',
            method: Method::POST->value,
            content: json_encode(
                value: [
                    'foo' => 'bar',
                ],
                flags: JSON_THROW_ON_ERROR,
            ),
        );

        $this->response = new Response(
            content: json_encode(
                value: [
                    'foo' => 'bar',
                ],
            )
        );
        $this->masker = app()->make(
            abstract: MaskingContract::class,
        );

        $this->job = new ProcessRequest(
            request: $this->request,
            response: $this->response,
            loadTime: 0.005,
        );
    }

    public function testItCanBuildAPayload(): void
    {
        $this->assertInstanceOf(
            expected: Data::class,
            actual: $this->job->buildPayload(
                masker: $this->masker,
            ),
        );
    }

    public function testItCanSendTheRequest(): void
    {
        Http::fake();

        $this->job->handle(
            client: app()->make(
                abstract: TreblleClientContract::class,
            ),
            masker: $this->masker,
        );

        Http::assertSentCount(1);
    }
}
