<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Auth\AuthenticationException;
use App\Models\User\AccessGroupKey;

use Closure;

class AccessControl
{
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

        // var_dump(config('services.iam.enabled'));
        if (config('services.iam.enabled')) {
            $access_group_ids = [];
        } else {
            $api_key = checkParam('key', true);
            $access_group_ids = AccessGroupKey::getAccessGroupIdsByApiKey($api_key);
        }

        // var_dump($api_key);
        // var_dump($access_group_ids);
        // exit();

        if (!empty($access_group_ids)) {
            $request->merge([
                'middleware_access_group_ids' => $access_group_ids
            ]);
        }
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
