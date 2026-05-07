<?php

declare(strict_types=1);

namespace Treblle\Laravel\Tests\Unit\DataProviders;

use PHPUnit\Framework\TestCase;
use Treblle\Laravel\DataProviders\LanguageDataProvider;

final class LanguageDataProviderTest extends TestCase
{
    public function test_returns_php_language(): void
    {
        $provider = new LanguageDataProvider();
        $language = $provider->getLanguage();

        $serialized = $language->jsonSerialize();

        $this->assertSame('php', $serialized['name']);
    }

    public function test_returns_current_php_version(): void
    {
        $provider = new LanguageDataProvider();
        $language = $provider->getLanguage();

        $serialized = $language->jsonSerialize();

        $this->assertSame(PHP_VERSION, $serialized['version']);
    }
}
