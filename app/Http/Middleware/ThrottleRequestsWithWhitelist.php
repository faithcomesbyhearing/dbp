<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $peerIp = $this->resolvePeerIp($request);

        if ($this->isIpWhitelisted($peerIp)) {
            return $next($request);
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }

    /**
     * Resolve the client IP used for whitelist matching.
     *
     * The API runs behind an AWS Application Load Balancer, so REMOTE_ADDR is the
     * ALB's private IP, not the upstream (live.bible.is) proxy. The ALB appends the
     * IP of the connection it received to the END of X-Forwarded-For, so the
     * right-most entry is the address the ALB actually observed — a value a client
     * cannot forge (anything a client puts in X-Forwarded-For is pushed left when the
     * ALB appends the real source). We deliberately use this right-most entry instead
     * of $request->ip(), which returns the spoofable left-most entry because
     * TrustProxies is set to '*'. When X-Forwarded-For is absent (e.g. local/direct
     * requests) we fall back to REMOTE_ADDR.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request.
     * @return string|null The resolved client IP address, or null if it cannot be determined.
     */
    private function resolvePeerIp(Request $request): ?string
    {
        $forwarded = $request->server('HTTP_X_FORWARDED_FOR');

        if (!empty($forwarded)) {
            $entries = explode(',', $forwarded);
            $edge_ip = trim(end($entries)); // right-most entry = appended by the AWS ALB

            if (filter_var($edge_ip, FILTER_VALIDATE_IP) !== false) {
                return $edge_ip;
            }
        }

        return $request->server('REMOTE_ADDR');
    }

    /**
     * Check if the given IP address is in the trusted no-rate-limit whitelist.
     *
     * @param string|null $ip The IP address to check.
     * @return bool True if the IP is whitelisted, false otherwise.
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
