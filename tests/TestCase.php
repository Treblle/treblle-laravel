<?php

declare(strict_types=1);

namespace Tests;

use Treblle\TreblleServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
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
