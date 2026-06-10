<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class UserDataAuditLog
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Perform actions after the response has been sent to the browser.
     * Logging here adds zero latency to the API response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        $api_key = checkParam('key');
        $masked_key = $api_key ? substr($api_key, 0, 8) . '...' : 'none';

        $user_id = $request->route('user_id')
            ?? $request->route('playlist_id')
            ?? $request->route('plan_id')
            ?? $request->input('user_id');

        Log::channel('user_data_access')->info('user_data_access', [
            'api_key'     => $masked_key,
            'endpoint'    => $request->path(),
            'method'      => $request->method(),
            'target_id'   => $user_id,
            'status_code' => $response->getStatusCode(),
            'ip'          => $request->ip(),
        ]);
    }
}
