<?php

declare(strict_types=1);

namespace Treblle\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Treblle\Providers\TreblleServiceProvider;

class PackageTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutExceptionHandling();
    }

    /**
     * @param Application $app
     * @return array<int,ServiceProvider>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TreblleServiceProvider::class,
        ];
    }
}
