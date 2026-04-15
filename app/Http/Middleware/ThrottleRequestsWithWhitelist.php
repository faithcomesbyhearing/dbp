<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

class ThrottleRequestsWithWhitelist extends ThrottleRequests
{
    /**
     * Handle an incoming request.
     *
     * Bypasses rate limiting for IPs listed in IP_TRUSTED_NO_RATE_LIMIT.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        // Use REMOTE_ADDR (actual peer IP) instead of $request->ip() to prevent
        // X-Forwarded-For spoofing when TrustProxies is set to '*'.
        $peerIp = $request->server('REMOTE_ADDR');

        if ($this->isIpWhitelisted($peerIp)) {
            return $next($request);
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }

    /**
     * Check if the given IP address is in the trusted no-rate-limit whitelist.
     */
    private function isIpWhitelisted(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        $trusted_ips = config('app.ip_trusted_no_rate_limit');

        if ($trusted_ips === null || trim((string) $trusted_ips) === '') {
            return false;
        }

        $allowed_ips = array_map('trim', explode(',', $trusted_ips));

        return in_array($ip, $allowed_ips, true);
    }
}
