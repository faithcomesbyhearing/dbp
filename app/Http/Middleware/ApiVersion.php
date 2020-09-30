<?php

namespace App\Http\Middleware;

use Illuminate\Http\JsonResponse ;


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

        $version_name = explode('_', $request->route()->getName())[0];
        if (!($version_name === 'v2' || $version_name === 'v3' ||  $version_name === 'v4')) {
            return $next($request);
        }
        $routeV = substr($version_name, 1,1);

        if ($url_header = $request->header("v")) {
            $requestV = $url_header;
         } else if ($queryParam = $request->input("v")) {
             $requestV = $queryParam;
         } else {
            return $next($request); // TODO:
         }
         if ($routeV != $requestV) {
            return response("Not Found", 404);
         }

         // all good
        return $next($request);
    }
}
