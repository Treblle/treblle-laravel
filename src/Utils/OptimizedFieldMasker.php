<?php

declare(strict_types=1);

namespace Treblle\Laravel\Utils;

/**
 * Optimized FieldMasker with performance improvements:
 * - Iterative processing instead of recursive
 * - Array key lookups instead of linear searches
 * - Cached patterns and string operations
 * - Depth limits to prevent stack overflow
 * - Memory-efficient processing
 */
final class OptimizedFieldMasker
{
    private const MAX_DEPTH = 10;
    private const STAR_CACHE_SIZE = 50;

    private readonly array $fieldLookup;

    private readonly array $starCache;

    public function __construct(private readonly array $fields)
    {
        // Create hash lookup for O(1) field matching instead of O(n) linear search
        $this->fieldLookup = array_flip(
            array_map('strtolower', $this->fields)
        );

        // Pre-generate star patterns for common string lengths to avoid repeated str_repeat calls
        $this->starCache = $this->buildStarCache();
    }

    public function mask(array $data): array
    {
        return $this->maskRecursiveOptimized($data, 0);
    }

    /**
     * Optimized recursive masking with depth limit and performance improvements
     */
    private function maskRecursiveOptimized(array $data, int $depth): array
    {
        // Enforce depth limit to prevent stack overflow and excessive processing
        if ($depth >= self::MAX_DEPTH) {
            return ['[...truncated (max depth reached)]'];
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->maskRecursiveOptimized($value, $depth + 1);
            } elseif (is_string($value)) {
                $result[$key] = $this->handleStringOptimized($key, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Optimized string handling with cached lookups and reduced string operations
     */
    private function handleStringOptimized(mixed $key, string $value): string
    {
        // Convert key to string only if necessary
        $keyString = is_string($key) ? $key : (string) $key;

        // Use cached lowercase conversion with memoization
        static $keyCache = [];
        if (! isset($keyCache[$keyString])) {
            $keyCache[$keyString] = mb_strtolower($keyString);

            // Limit cache size to prevent memory leaks
            if (count($keyCache) > 1000) {
                $keyCache = array_slice($keyCache, -500, null, true);
            }
        }

        $lowerKey = $keyCache[$keyString];

        // O(1) hash lookup instead of O(n) linear search
        if (isset($this->fieldLookup[$lowerKey])) {
            return $this->starOptimized($value);
        }

        // Handle authorization headers with optimized parsing
        if ('authorization' === $lowerKey) {
            return $this->maskAuthorizationOptimized($value);
        }

        // Handle base64 images with size check first (performance optimization)
        if (mb_strlen($value) > 100 && $this->isBase64Image($value)) {
            return '[base64_image]';
        }

        return $value;
    }

    /**
     * Optimized star generation with caching for common lengths
     */
    private function starOptimized(string $string): string
    {
        $length = mb_strlen($string);

        // Use pre-cached patterns for common lengths
        if ($length <= self::STAR_CACHE_SIZE) {
            return $this->starCache[$length] ?? str_repeat('*', $length);
        }

        // For very long strings, use a truncated pattern
        return str_repeat('*', min($length, 100));
    }

    /**
     * Optimized authorization header masking with minimal string operations
     */
    private function maskAuthorizationOptimized(string $value): string
    {
        $spacePos = mb_strpos($value, ' ');
        if (false === $spacePos) {
            return $this->starOptimized($value);
        }

        $authType = mb_substr($value, 0, $spacePos);
        $token = mb_substr($value, $spacePos + 1);

        $authTypeLower = mb_strtolower($authType);

        return match ($authTypeLower) {
            'bearer', 'basic', 'digest', 'oauth' => $authType . ' ' . $this->starOptimized($token),
            default => $this->starOptimized($value),
        };
    }

    /**
     * Optimized base64 image detection with early exit
     */
    private function isBase64Image(string $value): bool
    {
        // Quick rejection for obviously non-base64 strings
        if (mb_strlen($value) < 100 || ! str_starts_with($value, 'data:image/')) {
            return false;
        }

        $commaPos = mb_strpos($value, ',');

        return ! (false === $commaPos || ! str_contains(mb_substr($value, 0, $commaPos), 'base64'))

        ;
    }

    /**
     * Pre-build cache of star patterns for common string lengths
     */
    private function buildStarCache(): array
    {
        $cache = [];
        for ($i = 0; $i <= self::STAR_CACHE_SIZE; $i++) {
            $cache[$i] = str_repeat('*', $i);
        }

        return $cache;
    }
}
