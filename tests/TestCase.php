<?php

declare(strict_types=1);

namespace Treblle\Test;

use Treblle\TreblleServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

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
            TreblleServiceProvider::class
        ];
    }
}
