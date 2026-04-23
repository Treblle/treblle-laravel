<?php

declare(strict_types=1);

namespace Treblle\Laravel;

use Illuminate\Http\Request;

/**
 * Treblle runtime helper.
 *
 * Provides a clean API for attaching per-request metadata to the Treblle
 * payload without needing to interact with request attributes directly.
 *
 * Accessible via the Treblle facade:
 *   Treblle::meta('tenant_id', $user->tenant_id);
 *   Treblle::meta(['plan' => 'enterprise', 'version' => 'v2']);
 *
 * @package Treblle\Laravel
 */
final class Treblle
{
    public function __construct(private readonly Request $request)
    {
    }

    /**
     * Add one or more metadata key/value pairs to the current request's Treblle payload.
     *
     * Merges into any metadata already set — safe to call multiple times.
     *
     * @param string|array<string, mixed> $key  A single key or an associative array of key/value pairs.
     * @param mixed                       $value Value for the key (ignored when $key is an array).
     *
     * @example
     *   Treblle::meta('tenant_id', 'abc123');
     *   Treblle::meta(['plan' => 'enterprise', 'region' => 'us-east-1']);
     */
    public function meta(string|array $key, mixed $value = null): void
    {
        $existing = (array) $this->request->attributes->get('treblle_metadata', []);
        $incoming = is_array($key) ? $key : [$key => $value];

        $this->request->attributes->set('treblle_metadata', array_merge($existing, $incoming));
    }
}
