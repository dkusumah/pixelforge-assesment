<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDirectIndexAccess
{
    /**
     * Handle an incoming request.
     * Prevents clients from requesting /index.php directly in the URL path.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->server('REQUEST_URI', '');

        // If the client explicitly typed /index.php in the request URL
        if (preg_match('#^/index\.php([/?\s]|$)#i', $uri)) {
            abort(403, 'Direct access to index.php is forbidden. Please access the application via standard URLs.');
        }

        return $next($request);
    }
}
