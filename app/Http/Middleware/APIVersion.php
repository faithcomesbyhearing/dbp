<?php

namespace App\Http\Middleware;

use Closure;

class APIVersion
{


    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $method
     * @return mixed
     *
     */
    public function handle($request, Closure $next)
    {
        $route = $request->route()->getName();
        $version_name = explode('_', $route)[0];

        if (!($version_name === 'v2' || $version_name === 'v3' ||  $version_name === 'v4')) {
            return $next($request);
        }
        $routeV = substr($version_name, 1, 1);

        if ($url_header = $request->header('v')) {
            $requestV = $url_header;
        } elseif ($queryParam = $request->input('v')) {
            $requestV = $queryParam;
        } else {
            return $next($request);
        }
        
        // for the version 2, it will strip everything after the first digit,
        // so that a request submitted with v=2.12.1 will be processed as v=2
        $request_v_array = explode('.', $requestV);

        if (\sizeof($request_v_array) > 1 && (int) $request_v_array[0] === 2) {
            $requestV = $request_v_array[0];
        }

        if ($routeV != $requestV) {
            $route_name = $requestV === '3' && $routeV === '2' ? $route : null;

            if (!\Route::has($route_name)) {
                return response('Not Found', 404);
            }
        }

        return $next($request);
    }
}
