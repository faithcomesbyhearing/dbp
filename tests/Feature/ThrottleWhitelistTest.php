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
}
