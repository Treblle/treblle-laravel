<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\DataProviders;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\DataTransferObject\Error;
use Treblle\Laravel\DataProviders\InMemoryErrorDataProvider;

final class InMemoryErrorDataProviderTest extends TestCase
{
    public function test_starts_empty(): void
    {
        $provider = new InMemoryErrorDataProvider();

        $this->assertSame([], $provider->getErrors());
    }

    public function test_adds_error(): void
    {
        $provider = new InMemoryErrorDataProvider();
        $error = new Error(message: 'Something broke', file: '/app/foo.php', line: 42);

        $provider->addError($error);

        $this->assertCount(1, $provider->getErrors());
        $this->assertSame($error, $provider->getErrors()[0]);
    }

    public function test_caps_at_25_errors(): void
    {
        $provider = new InMemoryErrorDataProvider();

        for ($i = 0; $i < 30; $i++) {
            $provider->addError(new Error(message: "Error {$i}", file: '', line: $i));
        }

        $this->assertCount(25, $provider->getErrors());
    }

    public function test_errors_after_cap_are_silently_dropped(): void
    {
        $provider = new InMemoryErrorDataProvider();

        for ($i = 0; $i < 25; $i++) {
            $provider->addError(new Error(message: "Error {$i}", file: '', line: $i));
        }

        $provider->addError(new Error(message: 'This should be dropped', file: '', line: 99));

        $this->assertCount(25, $provider->getErrors());
        $this->assertSame('Error 24', $provider->getErrors()[24]->getMessage());
    }
}
