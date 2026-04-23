<?php

declare(strict_types=1);

namespace Treblle\Laravel\Helpers;

/**
 * Filters HTTP headers based on exclusion patterns.
 *
 * Supports exact matching, wildcard patterns (X-Internal-*), and regex (/^X-Auth-/i).
 */
final class HeaderFilter
{
    private const MAX_CACHE_SIZE = 100;

    /** @var array<string, string> */
    private static array $regexCache = [];

    /**
     * @param array<string, mixed> $headers
     * @param list<string> $excludedHeaders
     * @return array<string, string>
     */
    public static function filter(array $headers, array $excludedHeaders = []): array
    {
        if (empty($headers) || empty($excludedHeaders)) {
            $processed = [];

            foreach ($headers as $key => $value) {
                $processed[$key] = is_array($value) ? (string) reset($value) : (string) $value;
            }

            return $processed;
        }

        $processed = [];

        foreach ($headers as $key => $value) {
            if (self::isExcluded($key, $excludedHeaders)) {
                continue;
            }

            $processed[$key] = is_array($value) ? (string) reset($value) : (string) $value;
        }

        return $processed;
    }

    private static function isExcluded(string $key, array $excludedHeaders): bool
    {
        foreach ($excludedHeaders as $pattern) {
            if (preg_match(self::convertPatternToRegex($pattern), $key)) {
                return true;
            }
        }

        return false;
    }

    private static function convertPatternToRegex(string $pattern): string
    {
        if (isset(self::$regexCache[$pattern])) {
            return self::$regexCache[$pattern];
        }

        // Already a regex
        if (preg_match('/^\/.*\/[gimxsu]*$/', $pattern)) {
            self::cachePattern($pattern, $pattern);

            return $pattern;
        }

        // Convert shell wildcards to regex
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);
        $compiled = '/^' . $regex . '$/i';

        self::cachePattern($pattern, $compiled);

        return $compiled;
    }

    private static function cachePattern(string $pattern, string $regex): void
    {
        if (count(self::$regexCache) >= self::MAX_CACHE_SIZE) {
            self::$regexCache = [];
        }

        self::$regexCache[$pattern] = $regex;
    }
}
