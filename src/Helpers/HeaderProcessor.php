<?php

declare(strict_types=1);

namespace Treblle\Laravel\Helpers;

final class HeaderProcessor
{
    public static function process(array $headers): array
    {
        $excludedHeaders = config('treblle.excluded_headers', []);

        return collect($headers)
            ->transform(fn ($item) => collect($item)->first())
            ->reject(function ($value, $key) use ($excludedHeaders) {
                foreach ($excludedHeaders as $pattern) {
                    // Convert shell-style wildcards to regex if needed
                    $regex = self::convertPatternToRegex($pattern);
                    if (preg_match($regex, $key)) {
                        return true;
                    }
                }

                return false;
            })
            ->toArray();
    }

    private static function convertPatternToRegex(string $pattern): string
    {
        // If it's already a regex (starts and ends with delimiters), use as-is
        if (preg_match('/^\/.*\/[gimxsu]*$/', $pattern)) {
            return $pattern;
        }

        // Convert shell-style pattern to regex
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);

        return '/^' . $regex . '$/i';
    }
}
