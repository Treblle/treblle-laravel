<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\Helpers\SensitiveDataMasker;

final class SensitiveDataMaskerTest extends TestCase
{
    private SensitiveDataMasker $masker;

    protected function setUp(): void
    {
        $this->masker = new SensitiveDataMasker(['password', 'secret', 'api_key', 'credit_score']);
    }

    public function test_masks_exact_field_match(): void
    {
        $result = $this->masker->mask(['password' => 'hunter2']);

        $this->assertSame(str_repeat('*', 7), $result['password']);
    }

    public function test_masks_case_insensitively(): void
    {
        $result = $this->masker->mask(['PASSWORD' => 'hunter2', 'Secret' => 'mysecret']);

        $this->assertSame(str_repeat('*', 7), $result['PASSWORD']);
        $this->assertSame(str_repeat('*', 8), $result['Secret']);
    }

    public function test_does_not_mask_unlisted_fields(): void
    {
        $result = $this->masker->mask(['username' => 'alice', 'email' => 'alice@example.com']);

        $this->assertSame('alice', $result['username']);
        $this->assertSame('alice@example.com', $result['email']);
    }

    public function test_masks_bearer_authorization_header(): void
    {
        $result = $this->masker->mask(['authorization' => 'Bearer some-jwt-token']);

        $this->assertStringStartsWith('Bearer ', $result['authorization']);
        $this->assertSame('Bearer ' . str_repeat('*', mb_strlen('some-jwt-token')), $result['authorization']);
    }

    public function test_masks_basic_authorization_header(): void
    {
        $result = $this->masker->mask(['authorization' => 'Basic dXNlcjpwYXNz']);

        $this->assertSame('Basic ' . str_repeat('*', mb_strlen('dXNlcjpwYXNz')), $result['authorization']);
    }

    public function test_masks_unknown_authorization_scheme(): void
    {
        $result = $this->masker->mask(['authorization' => 'Token abc123']);

        $this->assertSame(str_repeat('*', mb_strlen('Token abc123')), $result['authorization']);
    }

    public function test_masks_x_api_key_header(): void
    {
        $result = $this->masker->mask(['x-api-key' => 'super-secret-key']);

        $this->assertSame(str_repeat('*', mb_strlen('super-secret-key')), $result['x-api-key']);
    }

    public function test_replaces_base64_images(): void
    {
        $result = $this->masker->mask(['avatar' => 'data:image/png;base64,iVBORw0KGgoAAAANS']);

        $this->assertSame('base64 encoded images are too big to process', $result['avatar']);
    }

    public function test_does_not_replace_non_image_base64(): void
    {
        $value = 'data:application/pdf;base64,JVBERi0xLjQ=';
        $result = $this->masker->mask(['attachment' => $value]);

        $this->assertSame($value, $result['attachment']);
    }

    public function test_masks_recursively_in_nested_arrays(): void
    {
        $result = $this->masker->mask([
            'user' => [
                'name' => 'Alice',
                'password' => 's3cr3t',
                'profile' => [
                    'secret' => 'hidden',
                ],
            ],
        ]);

        $this->assertSame('Alice', $result['user']['name']);
        $this->assertSame(str_repeat('*', 6), $result['user']['password']);
        $this->assertSame(str_repeat('*', 6), $result['user']['profile']['secret']);
    }

    public function test_returns_empty_array_unchanged(): void
    {
        $result = $this->masker->mask([]);

        $this->assertSame([], $result);
    }

    public function test_passes_through_non_string_values(): void
    {
        $result = $this->masker->mask(['count' => 42, 'active' => true, 'ratio' => 1.5]);

        $this->assertSame(42, $result['count']);
        $this->assertSame(true, $result['active']);
        $this->assertSame(1.5, $result['ratio']);
    }

    public function test_star_produces_correct_length(): void
    {
        $masker = new SensitiveDataMasker();

        $this->assertSame('***', $masker->star('abc'));
        $this->assertSame('**********', $masker->star('0123456789'));
    }

    public function test_no_fields_configured_leaves_data_intact(): void
    {
        $masker = new SensitiveDataMasker([]);
        $result = $masker->mask(['password' => 'plain', 'secret' => 'visible']);

        $this->assertSame('plain', $result['password']);
        $this->assertSame('visible', $result['secret']);
    }

    public function test_integer_keys_are_handled(): void
    {
        $result = $this->masker->mask([0 => 'value0', 1 => 'value1']);

        $this->assertSame('value0', $result[0]);
        $this->assertSame('value1', $result[1]);
    }
}
