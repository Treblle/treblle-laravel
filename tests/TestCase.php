<?php

declare(strict_types=1);

namespace Treblle\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Treblle\TreblleServiceProvider;
use InvalidArgumentException;
use JsonException;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
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

    /**
     * @throws JsonException|InvalidArgumentException
     */
    protected function fixture(string $name): array
    {
        if (! file_exists($path = __DIR__."/Fixtures/$name.json")) {
            throw new InvalidArgumentException(
                message: "Cannot find fixture at $path",
            );
        }

        $contents = file_get_contents(
            filename: $path,
        );

        if (! $contents) {
            throw new InvalidArgumentException(
                message: "Contents of $name cannot be fetched.",
            );
        }

        return json_decode(
            json: $contents,
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
