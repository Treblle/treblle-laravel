<?php

declare(strict_types=1);

namespace Treblle\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use JsonException;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Treblle\TreblleServiceProvider;
use Treblle\Utils\DataObjects\Data;
use Treblle\Utils\DataObjects\Error;
use Treblle\Utils\DataObjects\Language;
use Treblle\Utils\DataObjects\OS;
use Treblle\Utils\DataObjects\Request;
use Treblle\Utils\DataObjects\Response;
use Treblle\Utils\DataObjects\Server;
use Treblle\Utils\Http\Method;
use function time;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutExceptionHandling();
    }

    /**
     * @param Application $app
     *
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

    protected function newData(): Data
    {
        return new Data(
            server: new Server(
                ip: '127.0.0.1',
                timezone: 'Europe/London',
                software: 'Nginx',
                signature: 'test',
                protocol: '2.0',
                os: new OS(
                    name: 'Arch',
                    release: '1.0.0',
                    architecture: 'arm',
                ),
                encoding: 'gzip',
            ),
            language: new Language(
                name: 'PHP',
                version: '8.3',
                expose_php: 'true',
                display_errors: 'true',
            ),
            request: new Request(
                timestamp: (string) time(),
                ip: '127.0.0.1',
                url: '/',
                route_path: 'test',
                user_agent: 'Test_Suite',
                method: Method::GET,
                headers: [],
                body: [],
                raw: [],
            ),
            response: new Response(
                headers: [],
                code: 200,
                size: 1,
                load_time: 0.000001,
                body: [],
            ),
            errors: [],
        );
    }
}
