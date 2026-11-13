<?php

namespace Tests\Feature;

use App\Http\Controllers\Plan\PlanCompatibilityController;
use App\Services\Plans\PlanCompatibilityService;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the DB-free inputs of POST /plans/compatibility (API task 102553):
 *  - PlanCompatibilityController::normalizeRequest(), the request check (AC9's validation, the strict flags, the
 *    de-duplication), with values as checkParam() returns them: null for absent, 0, "0", false or an empty list;
 *  - PlanCompatibilityController::itemsOfNoRequestedPlan(), which ignores an asked item of a requested plan that is
 *    deleted (revision 5, R24);
 *  - PlanCompatibilityService::filesetFromRow(), which marks Opus by the id or the codec tag (design section 12: the
 *    id suffix alone, the tag alone, both, neither);
 *  - the pieces built from bible_files and bible_verses rows, USX's and JSON's included (revision 5, R19).
 *
 * The 403 for a key off the user-data whitelist, unknown ids in meta.not_found, the item-check limit, an item in none
 * of the plans and visibility need the database: tests/Integration/PlanCompatibilityTest.php.
 *
 * Runs without a database or the Laravel container.
 *
 * @group plans
 */
class PlanCompatibilityInputTest extends TestCase
{
    private function check(array $raw) : array
    {
        return PlanCompatibilityController::normalizeRequest($raw + ['plan_ids' => [401], 'bible_ids' => ['ENGESV']]);
    }

    private function assertRejected(array $raw, string $message)
    {
        $errors = $this->check($raw)['errors'];
        $this->assertContains($message, $errors, implode(' | ', $errors));
    }

    public function testAValidRequestWithDefaults()
    {
        $result = $this->check([]);

        $this->assertSame([], $result['errors']);
        $this->assertSame([
            'plan_ids'              => [401],
            'bible_ids'             => ['ENGESV'],
            'playlist_item_ids'     => null,
            'exact_threshold'       => 0,
            'include_video'         => false,
            'include_opus'          => false,
            'allow_video_only'      => false,
            'allow_chapter_audio'   => false,
        ], $result['values']);
    }

    public function testMissingOrEmptyListsAreRejected()
    {
        // checkParam() returns null for a missing value and for an empty list alike.
        $this->assertRejected(['plan_ids' => null], 'plan_ids is required: a list of 1 to 20 plan ids.');
        $this->assertRejected(['bible_ids' => null], 'bible_ids is required: a list of 1 to 100 Bible ids.');
        $both = PlanCompatibilityController::normalizeRequest([]);
        $this->assertCount(2, $both['errors'], 'both problems in one message');
    }

    public function testListsMustBeLists()
    {
        $this->assertRejected(['plan_ids' => '401'], 'plan_ids must be a list of plan ids.');
        $this->assertRejected(['plan_ids' => 401], 'plan_ids must be a list of plan ids.');
        $this->assertRejected(['plan_ids' => ['a' => 401]], 'plan_ids must be a list of plan ids.');
        $this->assertRejected(['bible_ids' => 'ENGESV'], 'bible_ids must be a list of Bible ids.');
        $this->assertRejected(['bible_ids' => ['x' => 'ENGESV']], 'bible_ids must be a list of Bible ids.');
    }

    public function testPlanIdsAreWholeNumbersOfOneOrMore()
    {
        foreach ([0, -1, 1.5, '1.5', 'abc', '-3', '+3', ' 3', true, null, [], '99999999999999999999'] as $bad) {
            $this->assertRejected(['plan_ids' => [401, $bad]], 'Each plan id must be a whole number of 1 or more.');
        }
        $this->assertSame([401, 7], $this->check(['plan_ids' => ['401', 7]])['values']['plan_ids']);
    }

    public function testBibleIdsAreLettersAndDigitsUpToTwelve()
    {
        // Not ASCII: MySQL's accent-insensitive collation would read ÉNGESV (or full-width ＥＮＧＥＳＶ) as ENGESV.
        foreach (['ENG-ESV', 'ENG ESV', '', 'ENGESVABCDEFG', 401, null, ['ENGESV'], 'ÉNGESV', 'ＥＮＧＥＳＶ'] as $bad) {
            $this->assertRejected(['bible_ids' => ['ENGESV', $bad]], 'Each Bible id must be letters and digits, at most 12 characters.');
        }
        $this->assertSame(['ENGESVABCDEF', '1234'], $this->check(['bible_ids' => ['ENGESVABCDEF', '1234']])['values']['bible_ids']);
    }

    public function testRepeatedIdsCountOnce()
    {
        $values = $this->check(['plan_ids' => [401, '401', 402, 401], 'bible_ids' => ['ENGESV', 'engesv', 'NTONLY', 'EngEsv']])['values'];

        $this->assertSame([401, 402], $values['plan_ids']);
        $this->assertSame(['ENGESV', 'NTONLY'], $values['bible_ids'], 'the first spelling is kept');
    }

    public function testTheLimits()
    {
        $this->assertSame([], $this->check(['plan_ids' => range(1, 20), 'bible_ids' => ['ENGESV']])['errors']);
        $this->assertRejected(['plan_ids' => range(1, 21)], 'plan_ids takes at most 20 plan ids.');
        $this->assertSame([], $this->check(['plan_ids' => array_merge(range(1, 20), [20, '20'])])['errors'], 'repeats are not counted');

        $bibles = array_map(function ($n) {
            return 'B' . $n;
        }, range(1, 101));
        $this->assertSame([], $this->check(['bible_ids' => array_slice($bibles, 0, 100)])['errors']);
        $this->assertRejected(['bible_ids' => $bibles], 'bible_ids takes at most 100 Bible ids.');

        // 5 plans x 100 Bibles is the most pairs; one more Bible's worth is over.
        $this->assertSame([], $this->check(['plan_ids' => range(1, 5), 'bible_ids' => array_slice($bibles, 0, 100)])['errors']);
        $this->assertRejected(
            ['plan_ids' => range(1, 6), 'bible_ids' => array_slice($bibles, 0, 84)],
            'At most 500 plan and Bible pairs per call: 6 plans times 84 Bibles were sent.'
        );
        $this->assertSame([], $this->check(['plan_ids' => range(1, 20), 'bible_ids' => array_slice($bibles, 0, 25)])['errors']);
        $this->assertSame(1, count($this->check(['plan_ids' => range(1, 20), 'bible_ids' => array_slice($bibles, 0, 26)])['errors']));
    }

    public function testItemChecksCountEveryChapterAnItemSpans()
    {
        $items = [
            ['id' => 1, 'book_id' => 'MAT', 'chapter_start' => 5, 'chapter_end' => 5, 'verse_start' => '1', 'verse_end' => '12'],
            ['id' => 2, 'book_id' => 'MAT', 'chapter_start' => 1, 'chapter_end' => null, 'verse_start' => null, 'verse_end' => null],
            ['id' => 3, 'book_id' => 'MAT', 'chapter_start' => 4, 'chapter_end' => 3, 'verse_start' => null, 'verse_end' => null],
            ['id' => 4, 'book_id' => 'PSA', 'chapter_start' => 1, 'chapter_end' => 150, 'verse_start' => null, 'verse_end' => null],
        ];

        $this->assertSame(1 + 1 + 1 + 150, PlanCompatibilityController::itemChecks($items));
        $this->assertSame(0, PlanCompatibilityController::itemChecks([]));
    }

    public function testPlaylistItemIds()
    {
        $ids = function ($value) {
            return $this->check(['playlist_item_ids' => $value])['values']['playlist_item_ids'];
        };
        $this->assertSame([88231], $ids([88231]));
        $this->assertSame([88231, 88201], $ids(['88231', 88201, 88231]), 'repeats count once, the first place kept');
        $this->assertNull($ids(null), 'absent');
        $this->assertNull($ids([]), 'an empty list is the same as none');
        $this->assertSame(range(1, 20), $ids(array_merge(range(1, 20), range(1, 20))), '20 distinct ids');

        // playlistItemIdsAsSent() hands 0, "0" and false over as they are: none is a list.
        foreach ([0, '0', false, true, 88231, '88231', 'x', ['a' => 88231]] as $bad) {
            $this->assertRejected(['playlist_item_ids' => $bad], 'playlist_item_ids must be a list of playlist item ids.');
        }
        foreach ([[0], ['0'], [-4], ['x'], [2.5], [true], [['88231']], ['088231'], [88231, null]] as $bad) {
            $this->assertRejected(['playlist_item_ids' => $bad], 'Each playlist item id must be a whole number of 1 or more.');
        }
        $this->assertRejected(['playlist_item_ids' => range(1, 21)], 'playlist_item_ids takes at most 20 playlist item ids.');
    }

    public function testExactThreshold()
    {
        $this->assertSame(0, $this->check(['exact_threshold' => null])['values']['exact_threshold']);
        $this->assertSame(25, $this->check(['exact_threshold' => 25])['values']['exact_threshold']);
        $this->assertSame(100, $this->check(['exact_threshold' => '100'])['values']['exact_threshold']);
        foreach ([101, -1, 2.5, '25%', 'abc', true, [25]] as $bad) {
            $this->assertRejected(['exact_threshold' => $bad], 'exact_threshold must be a whole number from 0 to 100.');
        }
    }

    public function testTheRemovedSectionAccommodationIsIgnoredLikeAnyUnknownField()
    {
        $result = $this->check(['section_accommodation' => true, 'something_else' => 'x']);

        $this->assertSame([], $result['errors']);
        $this->assertArrayNotHasKey('section_accommodation', $result['values']);
        $this->assertSame($this->check([])['values'], $result['values']);
    }

    public function testFlagsAreOnOnlyForTrue()
    {
        $flags = ['include_video', 'include_opus', 'allow_video_only', 'allow_chapter_audio'];
        foreach ([true, 'true', 'TRUE', 'True'] as $on) {
            $result = $this->check(array_fill_keys($flags, $on));
            $this->assertSame([], $result['errors']);
            foreach ($flags as $flag) {
                $this->assertTrue($result['values'][$flag], $flag);
            }
        }
        foreach ([null, false, 'false', '1', 1, 'yes', 'on', ['true'], ' true'] as $off) {
            $result = $this->check(array_fill_keys($flags, $off));
            $this->assertSame([], $result['errors'], 'anything but true is off, with no error');
            foreach ($flags as $flag) {
                $this->assertFalse($result['values'][$flag], $flag . ' ' . var_export($off, true));
            }
        }
    }

    public function testAllowVideoOnlyWithoutIncludeVideoIsNoError()
    {
        // R26 (Sam, 2026-11-13): ignored, with no 422. The calculator then has no video to count
        // (PlanCompatibilityCalculatorTest::testAllowVideoOnlyWorksItemByItem).
        $result = $this->check(['allow_video_only' => true]);

        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['values']['allow_video_only']);
        $this->assertFalse($result['values']['include_video']);
    }

    public function testAnItemOfARequestedPlanThatIsDeletedIsIgnored()
    {
        // Asked ids in none of the loaded plans: 5 (of plan 40, requested and deleted), 6 (of plan 41, not
        // requested), 7 (no such item), 8 (of plan 30, requested and loaded: an item Plan Details leaves out) and 9 (of
        // plan 99, requested and unknown: no plan has that id, so this can't happen, but it would be ignored too).
        $plan_id_by_item = [5 => 40, 6 => 41, 8 => 30, 9 => 99];

        $this->assertSame(
            [6, 7, 8],
            PlanCompatibilityController::itemsOfNoRequestedPlan([5, 6, 7, 8, 9], $plan_id_by_item, [40, 99])
        );
        $this->assertSame([7, 6], PlanCompatibilityController::itemsOfNoRequestedPlan([7, 5, 6], $plan_id_by_item, [40]), 'the order asked');
        $this->assertSame([], PlanCompatibilityController::itemsOfNoRequestedPlan([5], $plan_id_by_item, [40]));
        $this->assertSame([5], PlanCompatibilityController::itemsOfNoRequestedPlan([5], $plan_id_by_item, []), 'plan 40 not requested');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The service's row mapping

    private static function row(string $id, $has_opus_codec_tag) : array
    {
        return [
            'hash_id'            => 'abc123def456',
            'id'                 => $id,
            'set_type_code'      => 'audio',
            'set_size_code'      => 'NT',
            'segmentation_type'  => 'chapter',
            'updated_at'         => '2026-11-04 10:00:00',
            'has_opus_codec_tag' => $has_opus_codec_tag,
        ];
    }

    public function testOpusIsFoundByTheIdOrTheCodecTag()
    {
        // MySQL answers EXISTS with 1 or 0; with emulated prepares, "1" or "0".
        $this->assertTrue(PlanCompatibilityService::filesetFromRow(self::row('ENGESVN1DA-opus16', 0))['is_opus'], 'the id suffix alone');
        $this->assertTrue(PlanCompatibilityService::filesetFromRow(self::row('ENGESVN1DA-opus16', '0'))['is_opus']);
        $this->assertTrue(PlanCompatibilityService::filesetFromRow(self::row('BJMWBTP1DA', 1))['is_opus'], 'the codec tag alone');
        $this->assertTrue(PlanCompatibilityService::filesetFromRow(self::row('BJMWBTP1DA', '1'))['is_opus']);
        $this->assertTrue(PlanCompatibilityService::filesetFromRow(self::row('ENGESVO2DA-OPUS64', 1))['is_opus'], 'both, any case');
        $this->assertFalse(PlanCompatibilityService::filesetFromRow(self::row('ENGESVN1DA', 0))['is_opus'], 'neither');
        $this->assertFalse(PlanCompatibilityService::filesetFromRow(self::row('ENGESVN1DA', '0'))['is_opus']);
        $this->assertFalse(PlanCompatibilityService::filesetFromRow(self::row('ENGESVN1DA', null))['is_opus']);
    }

    public function testAFilesetRowKeepsWhatTheCallNeeds()
    {
        $this->assertSame([
            'hash_id'           => 'abc123def456',
            'id'                => 'ENGESVN1DA',
            'set_type_code'     => 'audio',
            'set_size_code'     => 'NT',
            'segmentation_type' => 'chapter',
            'updated_at'        => '2026-11-04 10:00:00',
            'is_opus'           => false,
        ], PlanCompatibilityService::filesetFromRow(self::row('ENGESVN1DA', 0)));
    }

    public function testPiecesFromFileRows()
    {
        $rows = [
            // A chapter file: chapter_end and verse_end null.
            ['book_id' => 'MAT', 'chapter_start' => 1, 'chapter_end' => null, 'verse_start' => '1', 'verse_end' => null, 'verse_sequence' => 1],
            // A section stored with leading zeros, spanning two chapters.
            ['book_id' => 'LUK', 'chapter_start' => 7, 'chapter_end' => 8, 'verse_start' => '036', 'verse_end' => '003', 'verse_sequence' => 36],
            // No verse_sequence: the leading number of verse_start.
            ['book_id' => 'LUK', 'chapter_start' => 8, 'chapter_end' => 8, 'verse_start' => '4b', 'verse_end' => '15', 'verse_sequence' => null],
            // A backwards chapter_end, and a verse_end of 0.
            ['book_id' => 'MRK', 'chapter_start' => 3, 'chapter_end' => 2, 'verse_start' => '1', 'verse_end' => '0', 'verse_sequence' => 1],
            // No chapter: skipped.
            ['book_id' => 'MRK', 'chapter_start' => null, 'chapter_end' => null, 'verse_start' => '1', 'verse_end' => null, 'verse_sequence' => 1],
            // text_usx, as on dev: one row per book, from its first chapter to its last, verse_end null.
            ['book_id' => 'GEN', 'chapter_start' => 1, 'chapter_end' => 50, 'verse_start' => '1', 'verse_end' => null, 'verse_sequence' => 1],
            // text_json, as on dev: one row per chapter, chapter_end and verse_end null (some verse_start '001').
            ['book_id' => 'EXO', 'chapter_start' => 2, 'chapter_end' => null, 'verse_start' => '001', 'verse_end' => null, 'verse_sequence' => 1],
        ];

        $this->assertSame([
            'MAT' => [[1, 1, 1, null]],
            'LUK' => [[7, 36, 8, 3], [8, 4, 8, 15]],
            'MRK' => [[3, 1, 3, null]],
            'GEN' => [[1, 1, 50, null]],
            'EXO' => [[2, 1, 2, null]],
        ], PlanCompatibilityService::piecesFromFileRows($rows));
    }

    public function testPiecesFromVerseRows()
    {
        $rows = [
            ['book_id' => 'MAT', 'chapter' => 1, 'verse_min' => 1, 'verse_max' => 25],
            ['book_id' => 'MAT', 'chapter' => '2', 'verse_min' => '1', 'verse_max' => '23'],
        ];

        $this->assertSame(
            ['MAT' => [[1, 1, 1, 25], [2, 1, 2, 23]]],
            PlanCompatibilityService::piecesFromVerseRows($rows)
        );
    }
}
