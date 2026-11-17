<?php

namespace Tests\Feature;

use App\Http\Controllers\Plan\PlanCompatibilityController;
use App\Models\Bible\BibleFileset;
use App\Services\Plans\PlanCompatibilityCalculator;
use App\Services\Plans\PlanCompatibilityService;
use App\Support\AccessGroupsCollection;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit coverage for revision 6 of POST /plans/compatibility (API task 102553, the speed design in
 * dbp-designs/102553-compatibility-speed), without a database or the Laravel container:
 *  - which filesets the old-format rules could drop (T1), and a tripwire on the rules this restates;
 *  - the weekly cache expiry (AC5);
 *  - the time limit, with a fake clock (AC4, P2);
 *  - the log line's fields (AC3).
 * The cache entries through cacheRemember() are in PlanCompatibilityCacheTest (it needs the container), and A3 and
 * the plain text's last verse (AC6, AC7) in PlanCompatibilityCalculatorTest.
 *
 * @group plans
 */
class PlanCompatibilitySpeedTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // T1: the candidates for the old-format rules

    private static function candidate(string $id, string $set_type_code) : bool
    {
        return PlanCompatibilityService::isOldFormatCandidate(['id' => $id, 'set_type_code' => $set_type_code]);
    }

    public function testOnlyAnOldSixCharacterTextOrTwelveCharacterAudioIsACandidate()
    {
        $this->assertTrue(self::candidate('ENGESV', 'text_plain'), 'the old six-character text');
        $this->assertTrue(self::candidate('ENGESVN1DA16', 'audio'), 'old 16 kbps audio');
        $this->assertTrue(self::candidate('ENGESVN2DA16', 'audio_drama'));
        // The DA16 rule looks at the length, not the suffix: any twelve-character audio is a candidate.
        $this->assertTrue(self::candidate('ENGESVN1DA24', 'audio'));

        $this->assertFalse(self::candidate('ENGESVN_ET', 'text_plain'), 'the ten-character text');
        $this->assertFalse(self::candidate('ENGESVX', 'text_plain'));
        $this->assertFalse(self::candidate('ENGES', 'text_plain'));
        $this->assertFalse(self::candidate('ENGESV', 'text_usx'), 'only text_plain');
        $this->assertFalse(self::candidate('ENGESV', 'text_format'));
        $this->assertFalse(self::candidate('ENGESV', 'audio'), 'six characters is not audio\'s length');
        $this->assertFalse(self::candidate('ENGESVN1DA', 'audio'));
        $this->assertFalse(self::candidate('ENGESVN1DA-opus16', 'audio'), 'the newer copy itself');
        $this->assertFalse(self::candidate('ENGESVN1SA16', 'audio_stream'), 'only audio and audio_drama');
        $this->assertFalse(self::candidate('ENGESVN2SA16', 'audio_drama_stream'));
        $this->assertFalse(self::candidate('ENGESVP2DV12', 'video_stream'));
        $this->assertFalse(self::candidate('', ''));
        $this->assertFalse(PlanCompatibilityService::isOldFormatCandidate([]), 'a row without its columns');
    }

    public function testTheCandidateTestReadsTypesAndLengthsAsMysqlDoes()
    {
        // MySQL compares the types without regard to case or trailing spaces (utf8mb4_unicode_ci, PAD SPACE).
        $this->assertTrue(self::candidate('ENGESV', 'TEXT_PLAIN'));
        $this->assertTrue(self::candidate('ENGESV', 'Text_Plain '));
        $this->assertTrue(self::candidate('ENGESVN1DA16', 'Audio_Drama'));
        // CHAR_LENGTH counts characters, not bytes.
        $this->assertTrue(self::candidate('ÉNGESV', 'text_plain'), 'six characters, seven bytes');
        $this->assertFalse(self::candidate('ÉNGES', 'text_plain'), 'five characters, six bytes');
        $this->assertTrue(self::candidate('ÉNGESVN1DA16', 'audio'), 'twelve characters, thirteen bytes');
        $this->assertFalse(self::candidate('ÉNGESVN1DA1', 'audio'), 'eleven characters, twelve bytes');
        // CHAR_LENGTH counts a trailing space.
        $this->assertFalse(self::candidate('ENGESV ', 'text_plain'));
    }

    public function testTheCandidateTestUsesTheModelsLengths()
    {
        $this->assertTrue(self::candidate(str_repeat('A', BibleFileset::OLD_TEXT_PLAIN_FILESET_LENGTH), BibleFileset::TYPE_TEXT_PLAIN));
        $this->assertTrue(self::candidate(str_repeat('A', BibleFileset::V1_AUDIO_16_KBPS_FILESET_LENGTH), BibleFileset::TYPE_AUDIO));
        $this->assertTrue(self::candidate(str_repeat('A', BibleFileset::V1_AUDIO_16_KBPS_FILESET_LENGTH), BibleFileset::TYPE_AUDIO_DRAMA));
    }

    /**
     * A tripwire. isOldFormatCandidate() restates the first condition the old-format scopes put on the fileset itself
     * (reference/findings.md F44). When a scope's source no longer holds that condition, this fails: check whether the
     * candidate test must change with it, then update the patterns here. The patterns also accept the refactor of
     * PR 1162 (Task-70030, merged into preview: CHAR_LENGTH_ID_FORMAT and a short-circuit on the same conditions).
     */
    public function testTheOldFormatScopesStillPutTheConditionsTheCandidateTestRestates()
    {
        foreach (self::oldFormatTripwires(self::methodSource(BibleFileset::class, 'subqueryConditionToExcludeOldTextFormat')
            . self::methodSource(BibleFileset::class, 'scopeConditionToExcludeOldTextFormat')
            . self::methodSource(BibleFileset::class, 'scopeConditionToExcludeOldDA16Format')) as $label => $holds) {
            $this->assertTrue($holds, $label);
        }
    }

    /**
     * The conditions isOldFormatCandidate() restates, looked for in the scopes' source (whitespace collapsed).
     *
     * @return array label => whether the source holds it
     */
    public static function oldFormatTripwires(string $source) : array
    {
        $source = preg_replace('/\s+/', ' ', $source);
        $length = function (string $constant) {
            return '/CHAR_LENGTH[^;]*?\$from_table ?\)\), \'=\', self::' . $constant . '/';
        };

        return [
            'text: only text_plain' => str_contains($source, "where('bfctext.set_type_code', BibleFileset::TYPE_TEXT_PLAIN)"),
            'text: the same type as the fileset' => str_contains($source, "whereColumn('bfctext.set_type_code', '=', \$from_table.'.set_type_code')"),
            'text: an id of OLD_TEXT_PLAIN_FILESET_LENGTH' => preg_match($length('OLD_TEXT_PLAIN_FILESET_LENGTH'), $source) === 1,
            'text: the scope uses that subquery' => str_contains($source, 'subqueryConditionToExcludeOldTextFormat($subquery'),
            'audio: only audio and audio_drama' => str_contains($source, "whereIn('bfcaudio.set_type_code', [BibleFileset::TYPE_AUDIO, BibleFileset::TYPE_AUDIO_DRAMA])"),
            'audio: the same type as the fileset' => str_contains($source, "whereColumn('bfcaudio.set_type_code', '=', \$from_table.'.set_type_code')"),
            'audio: an id of V1_AUDIO_16_KBPS_FILESET_LENGTH' => preg_match($length('V1_AUDIO_16_KBPS_FILESET_LENGTH'), $source) === 1,
        ];
    }

    private static function methodSource(string $class, string $method) : string
    {
        $reflection = new ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // AC5: the weekly expiry

    private static function untilMonday(string $now, string $key = 'plan_compat_fileset_v2:abc123') : Carbon
    {
        Carbon::setTestNow(Carbon::parse($now));

        return PlanCompatibilityService::cacheUntilMonday($key);
    }

    private static function spread(string $key) : int
    {
        return crc32($key) % 3600;
    }

    public function testTheWeeklyExpiryIsTheNextMondayAtFiveUtc()
    {
        $key = 'plan_compat_fileset_v2:abc123';
        $spread = self::spread($key);
        // 2026-11-16 is a Monday.
        $cases = [
            '2026-11-16 15:59:00 UTC' => '2026-11-16 17:00:00', // 61 minutes left: the same day
            '2026-11-16 16:00:00 UTC' => '2026-11-16 17:00:00', // an hour left exactly
            '2026-11-16 16:01:00 UTC' => '2026-11-23 17:00:00', // 59 minutes left: the next week
            '2026-11-16 17:00:00 UTC' => '2026-11-23 17:00:00',
            '2026-11-16 23:30:00 UTC' => '2026-11-23 17:00:00',
            '2026-11-16 00:00:00 UTC' => '2026-11-16 17:00:00',
            '2026-11-17 09:00:00 UTC' => '2026-11-23 17:00:00', // Tuesday
            '2026-11-22 23:59:59 UTC' => '2026-11-23 17:00:00', // Sunday
            '2026-11-22 12:00:00 America/Denver' => '2026-11-23 17:00:00', // Sunday in Denver, Sunday 19:00 UTC
            '2026-11-16 09:30:00 America/Denver' => '2026-11-23 17:00:00', // 16:30 UTC: under an hour left
        ];
        foreach ($cases as $now => $expected) {
            $expiry = self::untilMonday($now, $key);
            $this->assertSame($expected, $expiry->copy()->subSeconds($spread)->format('Y-m-d H:i:s'), $now);
        }
    }

    public function testTheWeeklyExpiryIsAnIlluminateCarbonInUtc()
    {
        $expiry = self::untilMonday('2026-11-18 10:00:00 America/Denver');

        // cacheRememberByKey() takes an Illuminate\Support\Carbon (Helpers.php).
        $this->assertInstanceOf(Carbon::class, $expiry);
        $this->assertSame('UTC', $expiry->getTimezone()->getName());
    }

    public function testTheTimeOfYearChangesNothing()
    {
        $default = date_default_timezone_get();
        date_default_timezone_set('America/Denver');
        try {
            $spread = self::spread('k');
            // The US change of 2026-11-01 (Sunday) and the European one of 2026-10-25 (Sunday); and in March.
            $cases = [
                '2026-10-31 12:00:00' => '2026-11-02 17:00:00',
                '2026-11-01 01:30:00' => '2026-11-02 17:00:00',
                '2026-10-24 22:00:00' => '2026-10-26 17:00:00',
                '2026-03-08 03:30:00' => '2026-03-09 17:00:00',
            ];
            foreach ($cases as $now => $expected) {
                $expiry = self::untilMonday($now, 'k');
                $this->assertSame($expected, $expiry->copy()->subSeconds($spread)->format('Y-m-d H:i:s'), $now);
                $this->assertSame('UTC', $expiry->getTimezone()->getName());
            }
        } finally {
            date_default_timezone_set($default);
        }
    }

    public function testEachKeyGetsItsOwnSecondWithinTheHour()
    {
        $now = '2026-11-18 10:00:00 UTC';
        $base = Carbon::parse('2026-11-23 17:00:00', 'UTC');

        $seconds = [];
        foreach (range(1, 200) as $n) {
            $offset = $base->diffInSeconds(self::untilMonday($now, 'plan_compat_superseded:hash' . $n), false);
            $this->assertGreaterThanOrEqual(0, $offset);
            $this->assertLessThan(3600, $offset);
            $seconds[] = $offset;
        }
        $this->assertGreaterThan(150, count(array_unique($seconds)), 'spread over the hour, not one second');
        $this->assertEquals(self::untilMonday($now, 'same key'), self::untilMonday($now, 'same key'), 'the same key, the same second');
    }

    public function testTheWeeklyExpiryIsNeverLessThanAnHourAway()
    {
        // Every 7 minutes for a week (Repository::put() deletes an entry whose time is up).
        $start = Carbon::parse('2026-11-16 00:00:00', 'UTC');
        for ($minutes = 0; $minutes < 7 * 24 * 60; $minutes += 7) {
            $now = $start->copy()->addMinutes($minutes);
            Carbon::setTestNow($now);
            $seconds = $now->diffInSeconds(PlanCompatibilityService::cacheUntilMonday('k' . $minutes), false);
            $this->assertGreaterThanOrEqual(3600, $seconds, $now->toDateTimeString());
            $this->assertLessThanOrEqual(7 * 86400 + 2 * 3600, $seconds, $now->toDateTimeString());
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // AC4: the time limit

    /**
     * A service with no database: every Bible but ZZNONE has one plain text with MAT 1, and the Bibles asked for are
     * recorded in $asked. No declared return type, so the anonymous class's own property stays visible.
     */
    private static function service()
    {
        return new class extends PlanCompatibilityService {
            public array $asked = [];

            public function getVisibleFilesets(string $bible_id, AccessGroupsCollection $access_group_ids) : array
            {
                $this->asked[] = $bible_id;

                return $bible_id === 'ZZNONE' ? [] : [[
                    'hash_id' => 'h' . $bible_id, 'id' => $bible_id, 'set_type_code' => 'text_plain',
                    'set_size_code' => 'NT', 'segmentation_type' => null, 'updated_at' => '', 'is_opus' => false,
                ]];
            }

            public function getPieces(array $fileset) : array
            {
                return ['MAT' => [[1, 1, 1, 25]]];
            }
        };
    }

    private static function checkBibles(PlanCompatibilityService $service, array $bible_ids, array $times, float $limit = 60) : array
    {
        $plans = [7 => [['id' => 1, 'book_id' => 'MAT', 'chapter_start' => 1, 'chapter_end' => 1, 'verse_start' => null, 'verse_end' => null]]];
        $clock = function () use (&$times) {
            return array_shift($times);
        };

        return PlanCompatibilityController::checkBibles(
            $service,
            new PlanCompatibilityCalculator(),
            $plans,
            $bible_ids,
            new AccessGroupsCollection([1]),
            null,
            1000.0,
            $limit,
            $clock
        );
    }

    public function testACallPastTheTimeLimitStopsBeforeTheNextBible()
    {
        $service = self::service();
        // The clock is read before each Bible: 0 s, 30 s, then 60.5 s in.
        $answer = self::checkBibles($service, ['ENGA', 'ENGB', 'ENGC', 'ENGD'], [1000.0, 1030.0, 1060.5, 1061.0]);

        $this->assertTrue($answer['timed_out']);
        $this->assertSame(['ENGA', 'ENGB'], $service->asked, 'ENGC and ENGD are never looked at');
        $this->assertSame(['ENGA', 'ENGB'], array_column($answer['results'], 0));
        $this->assertSame(2, $answer['checked']);
    }

    public function testACallWithinTheTimeLimitChecksEveryBible()
    {
        $service = self::service();
        // 60 s in is not past the limit.
        $answer = self::checkBibles($service, ['ENGA', 'ZZNONE', 'ENGC'], [1000.0, 1059.0, 1060.0]);

        $this->assertFalse($answer['timed_out']);
        $this->assertSame(['ENGA', 'ZZNONE', 'ENGC'], $service->asked);
        $this->assertSame(['ENGA', 'ENGC'], array_column($answer['results'], 0));
        $this->assertSame(['ZZNONE'], $answer['not_found']);
        $this->assertSame(3, $answer['checked']);
        $this->assertSame(2, $answer['filesets']);
        $this->assertSame('full', $answer['results'][0][1][7]['status']);
    }

    public function testACallAlreadyPastTheLimitChecksNoBible()
    {
        $service = self::service();
        $answer = self::checkBibles($service, ['ENGA'], [1000.0], -1);

        $this->assertTrue($answer['timed_out']);
        $this->assertSame([], $service->asked);
        $this->assertSame(0, $answer['checked']);
    }

    public function testTheTimeLimitIsSixtySecondsWithItsOwnAnswer()
    {
        $config = require __DIR__ . '/../../config/plan_compat.php';
        $this->assertSame(60, $config['time_limit']);
        $this->assertSame('plan_compatibility_time_limit', PlanCompatibilityController::TIME_LIMIT_ACTION);
        $this->assertSame('The compatibility check took too long. Send fewer Bibles at a time.', PlanCompatibilityController::TIME_LIMIT_MESSAGE);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // AC3: the log line

    public function testTheLogLineHoldsCountsAndTimesOnly()
    {
        $plans = [101 => [['id' => 1], ['id' => 2]], 102 => [['id' => 3]]];
        $answer = ['results' => [], 'not_found' => ['ZZNONE'], 'checked' => 4, 'filesets' => 21, 'timed_out' => false];
        $counts = ['pieces' => 22, 'pieces_built' => 5, 'old_format_checks' => 2];

        $this->assertSame([
            'plans'             => 2,
            'items'             => 3,
            'bibles_asked'      => 6,
            'bibles_checked'    => 4,
            'bibles_found'      => 3,
            'filesets'          => 21,
            'pieces'            => 22,
            'pieces_built'      => 5,
            'pieces_cached'     => 17,
            'old_format_checks' => 2,
            'elapsed_ms'        => 1235,
            'timed_out'         => false,
        ], PlanCompatibilityController::logContext($plans, 6, $answer, $counts, 1.2346));

        // No plan id, key, user or token, as a value or a name.
        $context = PlanCompatibilityController::logContext($plans, 6, ['timed_out' => true] + $answer, $counts, 61.0);
        $this->assertTrue($context['timed_out']);
        $this->assertSame([], array_intersect(array_values($context), [101, 102]));
        $this->assertSame([], preg_grep('/plan_id|key|user|token/', array_keys($context)));
    }
}
