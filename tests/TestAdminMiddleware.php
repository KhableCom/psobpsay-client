<?php

namespace PsobPsay\Client\Tests;

use Closure;
use Illuminate\Http\Request;

class TestAdminMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->headers->get('X-Test-Admin') !== '1') {
            abort(403);
        }

        return $next($request);
    }
}
