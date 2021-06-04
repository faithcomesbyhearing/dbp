<?php

namespace App\Http\Middleware;

use Closure;

/**
   * Paginates the bibles.all endpoint depending on api key 
   * (backward compat for bibleis and gideons)
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
*/
class BiblesAllApiKeyCompat
{
    public function handle($request, Closure $next)
    {   
        $compat_api_keys = config('auth.compat_users.api_keys');
        $compat_api_keys = explode(',', $compat_api_keys);

        if (in_array($request->key, $compat_api_keys)) {
            $request['limit'] = PHP_INT_MAX;
        }

        return $next($request);
    }
}