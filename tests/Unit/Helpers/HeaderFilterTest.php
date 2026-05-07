<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\Helpers\HeaderFilter;

final class HeaderFilterTest extends TestCase
{
    public function test_returns_all_headers_when_no_exclusions(): void
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $result = HeaderFilter::filter($headers, []);

        $this->assertSame($headers, $result);
    }

    public function test_excludes_exact_match(): void
    {
        $headers = ['Authorization' => 'Bearer token', 'Content-Type' => 'application/json'];
        $result = HeaderFilter::filter($headers, ['Authorization']);

        $this->assertArrayNotHasKey('Authorization', $result);
        $this->assertArrayHasKey('Content-Type', $result);
    }

    public function test_excludes_case_insensitively(): void
    {
        $headers = ['authorization' => 'Bearer token'];
        $result = HeaderFilter::filter($headers, ['Authorization']);

        $this->assertArrayNotHasKey('authorization', $result);
    }

    public function test_excludes_wildcard_prefix(): void
    {
        $headers = [
            'X-Internal-Id' => 'abc',
            'X-Internal-Secret' => 'xyz',
            'Content-Type' => 'application/json',
        ];
        $result = HeaderFilter::filter($headers, ['X-Internal-*']);

        $this->assertArrayNotHasKey('X-Internal-Id', $result);
        $this->assertArrayNotHasKey('X-Internal-Secret', $result);
        $this->assertArrayHasKey('Content-Type', $result);
    }

    public function test_excludes_wildcard_suffix(): void
    {
        $headers = ['X-Session-Token' => 'abc', 'X-Auth-Token' => 'xyz', 'Content-Type' => 'text/html'];
        $result = HeaderFilter::filter($headers, ['*-Token']);

        $this->assertArrayNotHasKey('X-Session-Token', $result);
        $this->assertArrayNotHasKey('X-Auth-Token', $result);
        $this->assertArrayHasKey('Content-Type', $result);
    }

    public function test_excludes_regex_pattern(): void
    {
        $headers = ['X-Api-Key' => 'secret', 'X-Auth-Token' => 'abc', 'Accept' => '*/*'];
        $result = HeaderFilter::filter($headers, ['/^x-(api|auth)-/i']);

        $this->assertArrayNotHasKey('X-Api-Key', $result);
        $this->assertArrayNotHasKey('X-Auth-Token', $result);
        $this->assertArrayHasKey('Accept', $result);
    }

    public function test_flattens_array_header_values(): void
    {
        $headers = ['Accept' => ['application/json', 'text/html']];
        $result = HeaderFilter::filter($headers, []);

        $this->assertSame('application/json', $result['Accept']);
    }

    public function test_returns_empty_when_headers_empty(): void
    {
        $result = HeaderFilter::filter([], ['Authorization']);

        $this->assertSame([], $result);
    }

    public function test_multiple_exclusion_patterns_applied(): void
    {
        $headers = [
            'Authorization' => 'Bearer token',
            'Cookie' => 'session=abc',
            'Content-Type' => 'application/json',
        ];
        $result = HeaderFilter::filter($headers, ['Authorization', 'Cookie']);

        $this->assertArrayNotHasKey('Authorization', $result);
        $this->assertArrayNotHasKey('Cookie', $result);
        $this->assertArrayHasKey('Content-Type', $result);
    }
}
