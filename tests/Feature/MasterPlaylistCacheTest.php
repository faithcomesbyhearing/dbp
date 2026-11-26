<?php

namespace Tests\Feature;

use App\Http\Controllers\Bible\MasterStreamController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Playlist responses (stream_master_index) stay cached for 12 hours only on HTTP 200.
 * An error is stored for a few seconds (H-20 / 104169). app.env is prod so cacheRememberByKey
 * takes the memcached path: get, then the lock and put with the expiry.
 */
class MasterPlaylistCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'prod', 'cache.default' => 'array']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testASuccessfulPlaylistStaysCachedForTwelveHours()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', 'UTC'));
        $calls = 0;
        $playlist = response("#EXTM3U\n", 200);

        $remember = function () use (&$calls, $playlist) {
            return $this->remember(function () use (&$calls, $playlist) {
                $calls++;

                return $playlist;
            });
        };

        $this->assertSame(200, $remember()->getStatusCode());
        $this->assertSame(200, $remember()->getStatusCode());
        $this->assertSame(1, $calls, 'a 200 is built once');

        Carbon::setTestNow(Carbon::parse('2026-11-18 21:59:00', 'UTC'));
        $this->assertNotNull(Cache::get($this->key()), 'still cached inside 12 hours');

        Carbon::setTestNow(Carbon::parse('2026-11-18 22:00:01', 'UTC'));
        $this->assertNull(Cache::get($this->key()), 'gone after 12 hours');
    }

    public function testAPlaylistErrorIsNotServedFromTheCacheAfterAFewSeconds()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', 'UTC'));
        $calls = 0;

        $remember = function () use (&$calls) {
            return $this->remember(function () use (&$calls) {
                $calls++;

                return response(['error' => 'playlist failed'], 500);
            });
        };

        $this->assertSame(500, $remember()->getStatusCode());
        $this->assertSame(500, $remember()->getStatusCode());
        $this->assertSame(1, $calls, 'the error is reused for those few seconds');

        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:06', 'UTC'));
        $this->assertNull(Cache::get($this->key()), 'the stored 500 is gone after a few seconds');

        $this->assertSame(500, $remember()->getStatusCode());
        $this->assertSame(2, $calls, 'the next request asks biblebrain-services again');
    }

    private function remember(\Closure $callback)
    {
        return cacheRememberByKey(
            $this->key(),
            now()->addHours(12),
            $callback,
            [MasterStreamController::class, 'masterPlaylistCacheTtl']
        );
    }

    private function key(): string
    {
        return generateCacheString('stream_master_index', ['FS', 'MAT', 1, null, null]);
    }
}
