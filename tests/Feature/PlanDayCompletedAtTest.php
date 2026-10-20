<?php

namespace Tests\Feature;

use App\Models\Plan\PlanDayComplete;
use App\Transformers\PlanDayPlaylistItemsTransformer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit coverage for the optional `completed_at` key on GET /plans/{plan_id} (v4_internal_plans.show), API task 103802.
 *
 * PlanDayComplete::formatCompletedAt() must give the format the plan's own created_at has in the same response
 * (Carbon's JSON form: UTC, six decimals, Z). PlanDayPlaylistItemsTransformer must leave the key off unless
 * include_completed_at is on, and when on give every day the key: the time for a completed day, null otherwise,
 * and never a time on a day whose `completed` is false.
 *
 * Runs without a database or the Laravel container. The transformer is built without its constructor, which reads
 * `v` from the request (core rule 16), and is fed plain objects in place of the Plan and PlanDay models.
 *
 * @group plans
 */
class PlanDayCompletedAtTest extends TestCase
{
    public static function formatProvider(): array
    {
        return [
            'a completion on dev (users_dev plan_days_completed)' => [1791920273, '2026-10-13T19:37:53.000000Z'],
            'one second after the epoch'                          => [1, '1970-01-01T00:00:01.000000Z'],
            'leap day'                                            => [1835481599, '2028-02-29T23:59:59.000000Z'],
            '11 PM on Oct 1 in Denver is Oct 2 in UTC'            => [1790917200, '2026-10-02T05:00:00.000000Z'],
            // UNIX_TIMESTAMP() of a NULL created_at is NULL and of a zero date is 0: no real time, so null, not 1970.
            'NULL created_at'                                     => [null, null],
            'zero date (0000-00-00 00:00:00)'                     => [0, null],
            'negative'                                            => [-1, null],
        ];
    }

    /**
     * @dataProvider formatProvider
     * @test
     */
    public function formatsUnixTimeAsUtcWithSixDecimals(?int $unix, ?string $expected)
    {
        $this->assertSame($expected, PlanDayComplete::formatCompletedAt($unix));
    }

    /**
     * gmdate() ignores PHP's default time zone, so a server not set to UTC cannot shift the value.
     *
     * @test
     */
    public function formatDoesNotDependOnPhpTimeZone()
    {
        $original = date_default_timezone_get();
        try {
            date_default_timezone_set('America/Denver');
            $this->assertSame('2026-10-13T19:37:53.000000Z', PlanDayComplete::formatCompletedAt(1791920273));
        } finally {
            date_default_timezone_set($original);
        }
    }

    /**
     * Without the flag no day has the key, even if a map of times were passed, and the day keeps today's shape.
     *
     * @test
     */
    public function flagOffLeavesTheKeyOff()
    {
        $result = $this->transform([
            'show_details' => false,
            'include_completed_at' => false,
            'completed_at_by_day' => [828 => '2026-10-13T19:37:53.000000Z'],
        ]);

        foreach ($result['days'] as $day) {
            $this->assertArrayNotHasKey('completed_at', $day);
        }
        $this->assertSame(['id', 'playlist_id', 'completed', 'playlist_is_empty'], array_keys($result['days'][0]));
    }

    /**
     * @test
     */
    public function flagOnGivesEveryDayTheKey()
    {
        $result = $this->transform(
            [
                'show_details' => false,
                'include_completed_at' => true,
                'completed_at_by_day' => [
                    828 => '2026-10-13T19:37:53.000000Z',
                    830 => '2026-10-14T08:00:00.000000Z',
                ],
            ]
        );
        $by_id = array_column($result['days'], null, 'id');

        // Completed, with a row: its own time.
        $this->assertSame('2026-10-13T19:37:53.000000Z', $by_id[828]['completed_at']);
        // Not completed, no row: null.
        $this->assertArrayHasKey('completed_at', $by_id[829]);
        $this->assertNull($by_id[829]['completed_at']);
        // Not completed although a row was read (un-completed between the two reads): null, never a date.
        $this->assertNull($by_id[830]['completed_at']);
        // Completed, but no row was read (completed between the two reads): null.
        $this->assertNull($by_id[831]['completed_at']);
        // The key sits after `completed`, before playlist_is_empty.
        $this->assertSame(
            ['id', 'playlist_id', 'completed', 'completed_at', 'playlist_is_empty'],
            array_keys($by_id[828])
        );
    }

    /**
     * No token: show() passes an empty map and every day reads null.
     *
     * @test
     */
    public function flagOnWithoutTimesGivesNullEverywhere()
    {
        $result = $this->transform(['show_details' => false, 'include_completed_at' => true, 'completed_at_by_day' => []]);

        foreach ($result['days'] as $day) {
            $this->assertArrayHasKey('completed_at', $day);
            $this->assertNull($day['completed_at']);
        }
    }

    /**
     * Four days: 828 and 831 completed, 829 and 830 not.
     */
    private function transform(array $params): array
    {
        $days = new Collection(array_map(function ($day) {
            return (object) ['id' => $day[0], 'playlist_id' => $day[0] + 9240, 'completed' => $day[1], 'playlist_is_empty' => false];
        }, [[828, true], [829, false], [830, false], [831, true]]));

        $plan = (object) [
            'id' => 176,
            'name' => 'Gospel in 30 Days',
            'thumbnail' => null,
            'featured' => true,
            'suggested_start_date' => '',
            'draft' => false,
            'created_at' => null,
            'updated_at' => null,
            'start_date' => '2026-10-13',
            'percentage_completed' => 10,
            'days' => $days,
            'total_users' => 490,
            'user' => (object) ['id' => 1920998, 'name' => 'FCBH Global Apps'],
        ];

        $transformer = (new ReflectionClass(PlanDayPlaylistItemsTransformer::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(PlanDayPlaylistItemsTransformer::class, 'params');
        $property->setAccessible(true);
        $property->setValue($transformer, $params + ['v' => 4, 'key' => '<KEY>']);

        $result = $transformer->transform($plan);
        $result['days'] = $result['days']->all();

        return $result;
    }
}
