<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class UserDataAccess
{
    /**
     * Handle an incoming request.
     *
     * Returns 403 if the API key is not in the user data access whitelist.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $api_key = checkParam('key');

        if (!$this->isKeyWhitelisted($api_key)) {
            return response()->json([
                'error' => [
                    'message' => 'This API key is not authorized to access user data.',
                    'status_code' => Response::HTTP_FORBIDDEN,
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Check if the given API key is in the user data access whitelist.
     */
    private function isKeyWhitelisted(?string $api_key): bool
    {
        if (empty($api_key)) {
            return false;
        }

        $keys = config('auth.compat_users.api_keys.user_data_access');
 
        if ($keys === null || trim((string) $keys) === '') {
            return false;
        }

       
        $allowed_keys = array_map('trim', explode(',', $keys));

        return in_array($api_key, $allowed_keys, true);
    }
}
