<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\DataTransferObject;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\DataTransferObject\Error;

final class ErrorTest extends TestCase
{
    public function test_getters_return_correct_values(): void
    {
        $error = new Error(
            message: 'Something went wrong',
            file: '/app/Controllers/UserController.php',
            line: 99,
            source: 'onException',
            type: 'UNHANDLED_EXCEPTION',
        );

        $this->assertSame('Something went wrong', $error->getMessage());
        $this->assertSame('/app/Controllers/UserController.php', $error->getFile());
        $this->assertSame(99, $error->getLine());
    }

    public function test_json_serialize_includes_all_fields(): void
    {
        $error = new Error(
            message: 'Test error',
            file: '/app/test.php',
            line: 10,
            source: 'onError',
            type: 'E_WARNING',
        );

        $serialized = $error->jsonSerialize();

        $this->assertSame('Test error', $serialized['message']);
        $this->assertSame('/app/test.php', $serialized['file']);
        $this->assertSame(10, $serialized['line']);
        $this->assertSame('onError', $serialized['source']);
        $this->assertSame('E_WARNING', $serialized['type']);
    }

    public function test_default_source_and_type(): void
    {
        $error = new Error(message: 'msg', file: '', line: 0);

        $serialized = $error->jsonSerialize();

        $this->assertSame('onError', $serialized['source']);
        $this->assertSame('UNHANDLED_EXCEPTION', $serialized['type']);
    }

    public function test_json_encodes_correctly(): void
    {
        $error = new Error(message: 'Test', file: '/foo.php', line: 1);
        $json = json_encode($error);

        $this->assertIsString($json);

        $decoded = json_decode($json, true);

        $this->assertSame('Test', $decoded['message']);
        $this->assertSame('/foo.php', $decoded['file']);
        $this->assertSame(1, $decoded['line']);
    }
}
