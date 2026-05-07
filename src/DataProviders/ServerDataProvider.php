<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Treblle\Laravel\DataTransferObject\Os;
use Treblle\Laravel\DataTransferObject\Server;

final class ServerDataProvider
{
    // Os data never changes within a process — cache it to avoid three
    // php_uname() syscalls on every request (critical under Octane).
    private static ?Os $cachedOs = null;

    public function getServer(): Server
    {
        return new Server(
            ip: $_SERVER['SERVER_ADDR'] ?? 'bogon',
            timezone: date_default_timezone_get(),
            software: $_SERVER['SERVER_SOFTWARE'] ?? null,
            protocol: $_SERVER['SERVER_PROTOCOL'] ?? null,
            os: self::$cachedOs ??= new Os(
                name: php_uname('s'),
                release: php_uname('r'),
                architecture: php_uname('m'),
            ),
        );
    }
}
