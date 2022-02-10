<?php

declare(strict_types=1);

namespace Treblle\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Treblle\TreblleServiceProvider;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutExceptionHandling();
    }

    protected function getPackageProviders($app): array
    {
        return [
            TreblleServiceProvider::class,
        ];
    }
}
