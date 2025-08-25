<?php

declare(strict_types=1);

namespace Treblle\Laravel\Middlewares;

use Closure;
use Illuminate\Http\Request;

final class TreblleEarlyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('treblle_original_payload', $request->all());

        return $next($request);
    }
}
