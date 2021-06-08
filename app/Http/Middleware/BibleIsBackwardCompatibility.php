<?php

namespace App\Http\Middleware;

use Closure;

/**
   * Paginates endpoints depending on api key
   * (backward compat for bibleis and gideons)
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
*/
class BibleIsBackwardCompatibility
{
    public function handle($request, Closure $next)
    {
        $compat_api_keys = config('auth.compat_users.api_keys');
        $compat_api_keys = explode(',', $compat_api_keys);
        $route_exists = isset($request->route()->action, $request->route()->action['as']);
        $key = checkParam('key');
        
        if ($route_exists && $request->route()->action['as'] === 'v4_bible.all') {
            if (in_array($key, $compat_api_keys)) {
                $request['limit'] = PHP_INT_MAX;
            }
        }
        
        return $next($request);
    }
}
