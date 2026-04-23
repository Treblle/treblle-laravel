<?php

declare(strict_types=1);

namespace Treblle\Laravel\DataProviders;

use Treblle\Laravel\DataTransferObject\Language;

final class LanguageDataProvider
{
    public function getLanguage(): Language
    {
        return new Language(
            name: 'php',
            version: PHP_VERSION,
        );
    }
}
