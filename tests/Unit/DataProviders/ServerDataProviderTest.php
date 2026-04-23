<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\DataProviders;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\DataProviders\ServerDataProvider;

final class ServerDataProviderTest extends TestCase
{
    public function test_returns_server_data(): void
    {
        $provider = new ServerDataProvider();
        $server = $provider->getServer();

        $serialized = $server->jsonSerialize();

        $this->assertArrayHasKey('ip', $serialized);
        $this->assertArrayHasKey('timezone', $serialized);
        $this->assertArrayHasKey('os', $serialized);
    }

    public function test_uses_server_addr_for_ip(): void
    {
        $_SERVER['SERVER_ADDR'] = '192.168.1.1';

        $provider = new ServerDataProvider();
        $server = $provider->getServer();
        $serialized = $server->jsonSerialize();

        $this->assertSame('192.168.1.1', $serialized['ip']);

        unset($_SERVER['SERVER_ADDR']);
    }

    public function test_falls_back_to_bogon_when_no_server_addr(): void
    {
        unset($_SERVER['SERVER_ADDR']);

        $provider = new ServerDataProvider();
        $server = $provider->getServer();
        $serialized = $server->jsonSerialize();

        $this->assertSame('bogon', $serialized['ip']);
    }

    public function test_timezone_matches_system(): void
    {
        $provider = new ServerDataProvider();
        $server = $provider->getServer();
        $serialized = $server->jsonSerialize();

        $this->assertSame(date_default_timezone_get(), $serialized['timezone']);
    }

    public function test_os_data_is_populated(): void
    {
        $provider = new ServerDataProvider();
        $server = $provider->getServer();
        $serialized = $server->jsonSerialize();

        $os = $serialized['os']->jsonSerialize();

        $this->assertNotEmpty($os['name']);
        $this->assertNotEmpty($os['release']);
        $this->assertNotEmpty($os['architecture']);
    }

    public function test_software_from_server_globals(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.25.0';

        $provider = new ServerDataProvider();
        $server = $provider->getServer();
        $serialized = $server->jsonSerialize();

        $this->assertSame('nginx/1.25.0', $serialized['software']);

        unset($_SERVER['SERVER_SOFTWARE']);
    }

    public function test_protocol_from_server_globals(): void
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/2';

        $provider = new ServerDataProvider();
        $server = $provider->getServer();
        $serialized = $server->jsonSerialize();

        $this->assertSame('HTTP/2', $serialized['protocol']);

        unset($_SERVER['SERVER_PROTOCOL']);
    }
}
