<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Auth\AuthenticationException;
use App\Models\User\AccessGroupKey;
use App\Services\IAMAPI\IAMAPIClientService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

use Closure;

class AccessControl
{
    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(IAMAPIClientService $iam_client)
    {
        $this->iam_client = $iam_client;
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
    public function handle($request, Closure $next)
    {
        $api_key = checkParam('key', true);

        if ($this->iam_client->isEnabled()) {
            try {
                $access_group_ids = $this->iam_client->getAccessGroupIdsByUserKey($api_key);
            } catch (\Exception $e) {
                \Log::channel('errorlog')->error($e->getMessage());
                return response('Service unavailable', HttpResponse::HTTP_SERVICE_UNAVAILABLE);
            }
        } else {
            $access_group_ids = AccessGroupKey::getAccessGroupIdsByApiKey($api_key);
        }

        if (!empty($access_group_ids)) {
            $request->merge([
                'middleware_access_group_ids' => $access_group_ids
            ]);
        }

        return $next($request);
    }
}
