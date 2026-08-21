<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // CORS is applied globally by Laravel's HandleCors middleware from
        // config/cors.php. Keep this legacy route alias as a compatibility
        // delegate, but never read environment variables at request time or overwrite the
        // cached global policy with a wildcard header.
        return $next($request);
    }
}
