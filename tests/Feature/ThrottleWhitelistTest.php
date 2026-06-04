<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ThrottleWhitelistTest extends TestCase
{
    private const TEST_ROUTE = '/test-throttle';

    // Test-only IP addresses (RFC 5737 documentation range — not routable)
    private const TRUSTED_IP_1 = '192.0.2.1';
    private const TRUSTED_IP_2 = '192.0.2.2';
    private const TRUSTED_IP_3 = '192.0.2.3';
    private const UNTRUSTED_IP = '198.51.100.1';
    private const ATTACKER_IP = '203.0.113.99';

    // Private ALB-like peer IP (REMOTE_ADDR) used to simulate requests arriving
    // through the AWS load balancer.
    private const ALB_REMOTE_ADDR = '10.0.1.50';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['throttle:60,1'])->get(self::TEST_ROUTE, function () {
            return response()->json(['status' => 'ok']);
        });
    }

    /**
     * @group throttle_whitelist
     * @test
     */
    public function whitelisted_ip_bypasses_rate_limit()
    {
        config(['app.ip_trusted_no_rate_limit' => self::TRUSTED_IP_1]);

        $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
            'REMOTE_ADDR' => self::TRUSTED_IP_1,
        ]);

        $response->assertStatus(200);
        $this->assertFalse(
            $response->headers->has('X-RateLimit-Limit'),
            'Whitelisted IP should not have rate limit headers'
        );
    }

    /**
     * @group throttle_whitelist
     * @test
     */
    public function non_whitelisted_ip_is_rate_limited()
    {
        config(['app.ip_trusted_no_rate_limit' => self::TRUSTED_IP_1]);

        $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
            'REMOTE_ADDR' => self::UNTRUSTED_IP,
        ]);

        $response->assertStatus(200);
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit'),
            'Non-whitelisted IP should have rate limit headers'
        );
    }

    /**
     * @group throttle_whitelist
     * @test
     */
    public function empty_whitelist_applies_rate_limiting_to_all()
    {
        config(['app.ip_trusted_no_rate_limit' => '']);

        $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
            'REMOTE_ADDR' => self::TRUSTED_IP_1,
        ]);

        $response->assertStatus(200);
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit'),
            'Empty whitelist should rate limit all IPs'
        );
    }

    /**
     * @group throttle_whitelist
     * @test
     */
    public function multiple_ips_in_whitelist()
    {
        $whitelist = implode(', ', [self::TRUSTED_IP_1, self::TRUSTED_IP_2, self::TRUSTED_IP_3]);
        config(['app.ip_trusted_no_rate_limit' => $whitelist]);

        foreach ([self::TRUSTED_IP_1, self::TRUSTED_IP_2, self::TRUSTED_IP_3] as $ip) {
            $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
                'REMOTE_ADDR' => $ip,
            ]);

            $response->assertStatus(200);
            $this->assertFalse(
                $response->headers->has('X-RateLimit-Limit'),
                "Whitelisted IP {$ip} should not have rate limit headers"
            );
        }

        $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
            'REMOTE_ADDR' => self::UNTRUSTED_IP,
        ]);

        $response->assertStatus(200);
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit'),
            'IP not in whitelist should have rate limit headers'
        );
    }

    /**
     * Behind the AWS ALB the upstream client may appear on the left and the ALB
     * appends the real proxy IP (live.bible.is) on the right. TrustProxies='*' does
     * NOT rewrite the raw HTTP_X_FORWARDED_FOR server var, so the middleware reads
     * exactly what is injected here.
     *
     * @group throttle_whitelist
     * @test
     */
    public function whitelisted_proxy_in_rightmost_xff_bypasses_rate_limit()
    {
        config(['app.ip_trusted_no_rate_limit' => self::TRUSTED_IP_1]);

        $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
            'REMOTE_ADDR'          => self::ALB_REMOTE_ADDR,
            'HTTP_X_FORWARDED_FOR' => self::UNTRUSTED_IP . ', ' . self::TRUSTED_IP_1,
        ]);

        $response->assertStatus(200);
        $this->assertFalse(
            $response->headers->has('X-RateLimit-Limit'),
            'Whitelisted proxy as right-most X-Forwarded-For entry should bypass rate limiting'
        );
    }

    /**
     * Common case: the proxy did not forward an upstream client, so the ALB appends
     * only the proxy IP as the single X-Forwarded-For entry.
     *
     * @group throttle_whitelist
     * @test
     */
    public function whitelisted_proxy_as_single_xff_entry_bypasses_rate_limit()
    {
        config(['app.ip_trusted_no_rate_limit' => self::TRUSTED_IP_1]);

        $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
            'REMOTE_ADDR'          => self::ALB_REMOTE_ADDR,
            'HTTP_X_FORWARDED_FOR' => self::TRUSTED_IP_1,
        ]);

        $response->assertStatus(200);
        $this->assertFalse(
            $response->headers->has('X-RateLimit-Limit'),
            'Whitelisted proxy as the only X-Forwarded-For entry should bypass rate limiting'
        );
    }

    /**
     * A client cannot bypass throttling by spoofing a whitelisted IP in
     * X-Forwarded-For: the AWS ALB appends the real observed source on the right,
     * so the left-most (client-controlled) value is never used for the match.
     *
     * @group throttle_whitelist
     * @test
     */
    public function spoofed_xff_does_not_bypass_rate_limit()
    {
        config(['app.ip_trusted_no_rate_limit' => self::TRUSTED_IP_1]);

        $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
            'REMOTE_ADDR'          => self::ALB_REMOTE_ADDR,
            'HTTP_X_FORWARDED_FOR' => self::TRUSTED_IP_1 . ', ' . self::ATTACKER_IP,
        ]);

        $response->assertStatus(200);
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit'),
            'Spoofed left-most X-Forwarded-For must NOT bypass rate limiting'
        );
    }

    /**
     * Without X-Forwarded-For (e.g. direct/local request) the whitelist matches
     * against REMOTE_ADDR.
     *
     * @group throttle_whitelist
     * @test
     */
    public function falls_back_to_remote_addr_without_xff()
    {
        config(['app.ip_trusted_no_rate_limit' => self::TRUSTED_IP_1]);

        $response = $this->call('GET', self::TEST_ROUTE, [], [], [], [
            'REMOTE_ADDR' => self::TRUSTED_IP_1,
        ]);

        $response->assertStatus(200);
        $this->assertFalse(
            $response->headers->has('X-RateLimit-Limit'),
            'Without X-Forwarded-For the whitelist should match REMOTE_ADDR'
        );
    }
}
