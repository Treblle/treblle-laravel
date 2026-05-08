<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Feature\Middlewares;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Treblle\Laravel\Tests\TestCase;
use Treblle\Laravel\Middlewares\TreblleEarlyMiddleware;

final class TreblleEarlyMiddlewareTest extends TestCase
{
    public function test_captures_request_payload_in_attributes(): void
    {
        $request = Request::create('http://localhost/api/users', 'POST', ['name' => 'Alice', 'email' => 'alice@example.com']);
        $middleware = new TreblleEarlyMiddleware();
        $passedThrough = false;

        $middleware->handle($request, function ($req) use (&$passedThrough) {
            $passedThrough = true;

            return response('ok');
        });

        $this->assertTrue($passedThrough);
        $this->assertTrue($request->attributes->has('treblle_original_payload'));

        $payload = $request->attributes->get('treblle_original_payload');

        $this->assertSame('Alice', $payload['name']);
        $this->assertSame('alice@example.com', $payload['email']);
    }

    public function test_passes_request_through_unchanged(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleEarlyMiddleware();
        $receivedRequest = null;

        $middleware->handle($request, function ($req) use (&$receivedRequest) {
            $receivedRequest = $req;

            return response('ok');
        });

        $this->assertSame($request, $receivedRequest);
    }

    public function test_captures_empty_body(): void
    {
        $request = Request::create('http://localhost/api/ping', 'GET');
        $middleware = new TreblleEarlyMiddleware();

        $middleware->handle($request, fn ($req) => response('ok'));

        $this->assertSame([], $request->attributes->get('treblle_original_payload'));
    }

    public function test_returns_next_response(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $middleware = new TreblleEarlyMiddleware();
        $expectedResponse = response('hello', 200);

        $result = $middleware->handle($request, fn ($req) => $expectedResponse);

        $this->assertSame($expectedResponse, $result);
    }

    public function test_captures_file_metadata_in_payload(): void
    {
        $request = Request::create('http://localhost/api/upload', 'POST', ['title' => 'Report']);
        $file = UploadedFile::fake()->create('report.pdf', 200, 'application/pdf');
        $request->files->set('attachment', $file);

        $middleware = new TreblleEarlyMiddleware();
        $middleware->handle($request, fn ($req) => response('ok'));

        $payload = $request->attributes->get('treblle_original_payload');

        $this->assertSame('Report', $payload['title']);
        $this->assertSame('report.pdf', $payload['attachment']['name']);
        $this->assertSame('pdf', $payload['attachment']['extension']);
        $this->assertArrayHasKey('size', $payload['attachment']);
        $this->assertArrayHasKey('mime_type', $payload['attachment']);
    }

    public function test_captures_multiple_file_uploads(): void
    {
        $request = Request::create('http://localhost/api/upload', 'POST');
        $files = [
            UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg'),
            UploadedFile::fake()->create('b.jpg', 60, 'image/jpeg'),
        ];
        $request->files->set('images', $files);

        $middleware = new TreblleEarlyMiddleware();
        $middleware->handle($request, fn ($req) => response('ok'));

        $payload = $request->attributes->get('treblle_original_payload');

        $this->assertIsArray($payload['images']);
        $this->assertCount(2, $payload['images']);
        $this->assertSame('a.jpg', $payload['images'][0]['name']);
        $this->assertSame('b.jpg', $payload['images'][1]['name']);
    }
}
