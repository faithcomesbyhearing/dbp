<?php

namespace Tests\Feature;

use App\Services\Plans\PlanCompatibilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The cache entries of POST /plans/compatibility's revision 6 (API task 102553, speed design AC5), through the app's
 * own cacheRemember() with the array store, and no database: every value a test reads is put in the cache first, so
 * no closure that would query runs. With app.env set to prod, cacheRemember() takes the path prod takes (get, then
 * the lock and put with the expiry), which off prod it never does.
 *
 * @group plans
 */
class PlanCompatibilityCacheTest extends TestCase
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

    public function testTheWeeklyExpiryGoesThroughCacheRememberOnTheProdPath()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 16:30:00', 'UTC'));
        $calls = 0;
        $remember = function () use (&$calls) {
            return cacheRemember('plan_compat_test', ['abc'], PlanCompatibilityService::cacheUntilMonday('k'), function () use (&$calls) {
                $calls++;

                return ['MAT' => [[1, 1, 1, 25]]];
            });
        };

        $this->assertSame(['MAT' => [[1, 1, 1, 25]]], $remember());
        $this->assertSame(['MAT' => [[1, 1, 1, 25]]], $remember(), 'read back from the cache');
        $this->assertSame(1, $calls, 'built once: the expiry is in the future, so the entry was kept');

        // Kept until the next Monday 17:00 UTC (plus its spread), gone after it.
        $key = generateCacheString('plan_compat_test', ['abc']);
        Carbon::setTestNow(Carbon::parse('2026-11-23 16:59:00', 'UTC'));
        $this->assertNotNull(Cache::get($key));
        Carbon::setTestNow(Carbon::parse('2026-11-23 18:00:00', 'UTC'));
        $this->assertNull(Cache::get($key));
    }

    public function testTheOldFormatVerdictIsReadFromItsOwnEntry()
    {
        $service = new PlanCompatibilityService();
        $superseded = new ReflectionMethod(PlanCompatibilityService::class, 'isSupersededOldFormat');

        $updated = '2026-11-01 10:00:00';
        Cache::put(generateCacheString('plan_compat_superseded', ['aaaaaaaaaaaa', $updated]), 1, 60);
        Cache::put(generateCacheString('plan_compat_superseded', ['bbbbbbbbbbbb', $updated]), 0, 60);

        $this->assertTrue($superseded->invoke($service, ['hash_id' => 'aaaaaaaaaaaa', 'updated_at' => $updated]), '1: the old-format rules drop it');
        $this->assertFalse($superseded->invoke($service, ['hash_id' => 'bbbbbbbbbbbb', 'updated_at' => $updated]), '0: kept (a cached 0 is a value, not a miss)');
        $this->assertSame(0, $service->counts()['old_format_checks'], 'no verdict worked out again');
        // The key carries updated_at (review L5-1): a changed row has its own entry, and the old verdict isn't read.
        Cache::put(generateCacheString('plan_compat_superseded', ['aaaaaaaaaaaa', '2026-11-18 09:00:00']), 0, 60);
        $this->assertFalse($superseded->invoke($service, ['hash_id' => 'aaaaaaaaaaaa', 'updated_at' => '2026-11-18 09:00:00']), 'the changed row\'s own verdict');
        $this->assertSame(0, $service->counts()['old_format_checks']);
    }

    public function testPiecesComeFromTheV2EntryOnly()
    {
        $service = new PlanCompatibilityService();
        $fileset = ['hash_id' => 'cccccccccccc', 'set_type_code' => 'text_plain', 'updated_at' => '2026-11-01 10:00:00'];
        // A value the old key holds (built before revision 6) is never read.
        Cache::put(generateCacheString('plan_compat_fileset', ['cccccccccccc', '2026-11-01 10:00:00']), ['MAT' => [[1, 1, 1, 26]]], 60);
        Cache::put(generateCacheString('plan_compat_fileset_v2', ['cccccccccccc', '2026-11-01 10:00:00']), ['MAT' => [[1, 1, 1, 25]]], 60);

        $this->assertSame(['MAT' => [[1, 1, 1, 25]]], $service->getPieces($fileset));
        $this->assertSame(['pieces' => 1, 'pieces_built' => 0, 'old_format_checks' => 0], $service->counts());
    }
}
