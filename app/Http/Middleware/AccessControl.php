<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Auth\AuthenticationException;

use Closure;

class AccessControl
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $method
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, $type = '')
    {


        // get key from request
        // if SWITCH = true 
            // execute select on tables, 
            // return access_group in array (eg [18, 191, 193])

        // else SWITCH = false 
            // call IAM microservice endpoint to return access_groups from key

        // add access_group list to request

        return $next($request);
    }
}
