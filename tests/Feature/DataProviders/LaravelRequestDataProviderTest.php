<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Feature\DataProviders;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Treblle\Laravel\Tests\TestCase;
use Treblle\Laravel\Helpers\SensitiveDataMasker;
use Treblle\Laravel\DataProviders\LaravelRequestDataProvider;

final class LaravelRequestDataProviderTest extends TestCase
{
    public function test_extracts_basic_request_data(): void
    {
        $request = Request::create('http://localhost/api/users', 'GET');
        $masker = new SensitiveDataMasker();
        $provider = new LaravelRequestDataProvider($masker, $request);

        $result = $provider->getRequest();
        $serialized = $result->jsonSerialize();

        $this->assertSame('http://localhost/api/users', $serialized['url']);
        $this->assertSame('GET', $serialized['method']);
        $this->assertNotEmpty($serialized['timestamp']);
    }

    public function test_timestamp_is_utc_format(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);

        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $serialized['timestamp']);
    }

    public function test_extracts_query_parameters(): void
    {
        $request = Request::create('http://localhost/api/users?page=1&limit=10', 'GET');
        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);

        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertSame(['page' => '1', 'limit' => '10'], $serialized['query']);
    }

    public function test_extracts_post_body(): void
    {
        $request = Request::create('http://localhost/api/users', 'POST', ['name' => 'Alice', 'email' => 'alice@example.com']);
        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);

        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertSame('Alice', $serialized['body']['name']);
        $this->assertSame('alice@example.com', $serialized['body']['email']);
    }

    public function test_masks_sensitive_fields_in_body(): void
    {
        $request = Request::create('http://localhost/api/login', 'POST', ['email' => 'alice@example.com', 'password' => 'secret123']);
        $masker = new SensitiveDataMasker(['password']);
        $provider = new LaravelRequestDataProvider($masker, $request);

        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertSame(str_repeat('*', 9), $serialized['body']['password']);
        $this->assertSame('alice@example.com', $serialized['body']['email']);
    }

    public function test_normalizes_file_uploads(): void
    {
        $request = Request::create('http://localhost/api/upload', 'POST');
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $request->files->set('attachment', $file);

        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);
        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertSame('document.pdf', $serialized['body']['attachment']['name']);
        $this->assertSame('pdf', $serialized['body']['attachment']['extension']);
        $this->assertArrayHasKey('size', $serialized['body']['attachment']);
        $this->assertArrayHasKey('mime_type', $serialized['body']['attachment']);
    }

    public function test_normalizes_multiple_file_uploads(): void
    {
        $request = Request::create('http://localhost/api/upload', 'POST');
        $files = [
            UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg'),
            UploadedFile::fake()->create('b.jpg', 60, 'image/jpeg'),
        ];
        $request->files->set('images', $files);

        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);
        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertIsArray($serialized['body']['images']);
        $this->assertCount(2, $serialized['body']['images']);
        $this->assertSame('a.jpg', $serialized['body']['images'][0]['name']);
        $this->assertSame('b.jpg', $serialized['body']['images'][1]['name']);
    }

    public function test_uses_early_captured_payload_when_available(): void
    {
        $request = Request::create('http://localhost/api/users', 'POST', ['original' => 'data']);
        $request->attributes->set('treblle_original_payload', ['original' => 'captured-before-transform']);

        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);
        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertSame('captured-before-transform', $serialized['body']['original']);
    }

    public function test_request_body_too_large_returns_error(): void
    {
        // Create a body that exceeds 2MB
        $largeString = str_repeat('x', 2 * 1024 * 1024 + 1);
        $request = Request::create('http://localhost/api/upload', 'POST', ['data' => $largeString]);

        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);
        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertSame('Payload too large', $serialized['body']['error']);
        $this->assertArrayHasKey('size', $serialized['body']);
    }

    public function test_ip_falls_back_to_bogon(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET');
        // IP will be set by Request::create defaults
        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);
        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertNotEmpty($serialized['ip']);
    }

    public function test_excluded_headers_are_filtered(): void
    {
        config(['treblle.excluded_headers' => ['X-Internal-*']]);

        $request = Request::create('http://localhost/api/test', 'GET');
        $request->headers->set('X-Internal-Secret', 'shh');
        $request->headers->set('Content-Type', 'application/json');

        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);
        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertArrayNotHasKey('x-internal-secret', $serialized['headers']);
        $this->assertArrayHasKey('content-type', $serialized['headers']);

        config(['treblle.excluded_headers' => []]);
    }

    public function test_user_agent_extracted(): void
    {
        $request = Request::create('http://localhost/api/test', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'MyClient/1.0']);
        $provider = new LaravelRequestDataProvider(new SensitiveDataMasker(), $request);
        $serialized = $provider->getRequest()->jsonSerialize();

        $this->assertSame('MyClient/1.0', $serialized['user_agent']);
    }
}
