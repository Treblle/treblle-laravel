<?php

declare(strict_types=1);

namespace Treblle\Middlewares;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LaravelMiddleware
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        // check the process.
        dd($this);
    }

    public function terminate(Request $request, JsonResponse|Response $response): void
    {
        dd('terminate');
    }
}
