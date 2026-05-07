<?php

declare(strict_types=1);

namespace Treblle\Laravel\Helpers;

/**
 * Masks sensitive data in request/response payloads.
 *
 * Handles field name matching (case-insensitive), authorization header masking,
 * base64 image detection, and recursive masking of nested arrays.
 */
final class SensitiveDataMasker
{
    /**
     * @var array<string, true> Lowercase field names as keys for O(1) lookup
     */
    private array $lowerFields = [];

    /**
     * @param list<string> $fields Field names to mask (e.g. 'password', 'api_key')
     */
    public function __construct(
        public array $fields = [],
    ) {
        foreach ($this->fields as $field) {
            $this->lowerFields[mb_strtolower($field)] = true;
        }
    }

    /**
     * Recursively masks sensitive data in the provided array.
     *
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    public function mask(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        $collector = [];

        foreach ($data as $key => $value) {
            $collector[$key] = match (true) {
                is_array($value) => $this->mask($value),
                is_string($value) => $this->handleString($key, $value),
                default => $value,
            };
        }

        return $collector;
    }

    public function star(string $string): string
    {
        return str_repeat('*', mb_strlen($string));
    }

    private function handleString(bool|float|int|string $key, string $value): string
    {
        if (! is_string($key)) {
            $key = (string) $key;
        }

        $lowerKey = mb_strtolower($key);

        if (isset($this->lowerFields[$lowerKey])) {
            return $this->star($value);
        }

        if ($this->isSensitiveHeader($lowerKey)) {
            return $this->maskAuthorization($value);
        }

        if ($this->isBase64Image($value)) {
            return 'base64 encoded images are too big to process';
        }

        return $value;
    }

    private function maskAuthorization(string $value): string
    {
        $parts = explode(' ', $value, 2);

        if (isset($parts[1])) {
            $authTypeLower = mb_strtolower($parts[0]);

            if (in_array($authTypeLower, ['bearer', 'basic', 'digest'], true)) {
                return $parts[0] . ' ' . $this->star($parts[1]);
            }
        }

        return $this->star($value);
    }

    private function isSensitiveHeader(string $key): bool
    {
        return in_array($key, ['authorization', 'x-api-key'], true);
    }

    private function isBase64Image(string $string): bool
    {
        return str_starts_with($string, 'data:image/') && str_contains($string, ';base64,');
    }
}
