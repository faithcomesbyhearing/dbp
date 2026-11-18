<?php

namespace Tests\Integration;

use App\Http\Controllers\Plan\PlanCompatibilityController;
use App\Models\Bible\Bible;
use App\Models\Bible\BibleFileset;
use App\Models\Plan\Plan;
use App\Models\Plan\PlanDay;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\User\AccessGroupKey;
use App\Models\User\Key;
use App\Services\Plans\PlanCompatibilityCalculator;
use App\Services\Plans\PlanCompatibilityService;
use App\Support\AccessGroupsCollection;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Coverage for POST /api/plans/compatibility (v4_internal_plans.compatibility), API task 102553, where it needs the
 * database: the playlist items (AC7), validation and meta.not_found (AC9), the user-data whitelist (AC10), visibility
 * (AC11) and an asked item of a deleted plan (AC24, revision 5); revision 6's old-format rules on the candidate rows
 * only (speed design section 4) and its time limit (AC4). The rules themselves are covered without a database by
 * tests/Feature/PlanCompatibilityCalculatorTest.php, tests/Feature/PlanCompatibilityInputTest.php and
 * tests/Feature/PlanCompatibilitySpeedTest.php.
 *
 * Seeds one plan of two days (MAT 1, and MAT 5:1-12) and, for the item-check limit, one plan of 2,001 items. Needs
 * the ENGESV Bible with its filesets and a key named test-key. The old-format test seeds two Bibles of its own
 * (ZZOLDFMT1, ZZOLDFMT2) with their filesets, and the time-limit test gives the key an access group when it has
 * none (a throwaway database); both remove what they add.
 *
 * The API key is sent only as a request header, never in the URL, and nothing that contains it is printed.
 *
 * Seeds data, so run it only against a throwaway test database, never a shared one.
 *
 * @group plans
 */
class PlanCompatibilityTest extends TestCase
{
    protected $key;
    protected $params;
    private $user_id;
    private $plan_ids = [];
    private $plan_id;
    private $item_ids = [];
    private $seeded_hash_ids = [];
    /** The access group linked to the key by the time-limit test, when the key had none; null otherwise. */
    private $seeded_key_group = null;
    private $seeded_group = false;

    private const OLD_FORMAT_BIBLES = ['ZZOLDFMT1', 'ZZOLDFMT2'];
    /** An access group made for the time-limit test only on a database that has none. */
    private const LOCAL_ACCESS_GROUP_ID = 990001;

    protected function setUp(): void
    {
        parent::setUp();

        if (!BibleFileset::where('id', 'ENGESVN2DA')->exists()) {
            $this->markTestSkipped('Needs the ENGESV Bible and its ENGESVN2DA fileset in the content database.');
        }

        $key = Key::where('name', 'test-key')->first();
        $this->key     = $key->key;
        $this->params  = ['v' => 4, 'key' => $this->key];
        $this->user_id = $key->user_id;

        // The route sits behind UserDataAccess, which only lets whitelisted keys through.
        config(['auth.compat_users.api_keys.user_data_access' => $this->key]);

        $this->plan_id = $this->createPlan('compatibility test plan', [
            ['MAT', 1, null, null],
            ['MAT', 5, '1', '12'],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->plan_ids as $plan_id) {
            // Playlists first, soft-deleted ones too: FK cascade removes playlist_items.
            Playlist::withTrashed()->where('plan_id', $plan_id)->forceDelete();
            Plan::withTrashed()->where('id', $plan_id)->forceDelete();
        }
        if ($this->seeded_hash_ids) {
            DB::connection('dbp')->table('bible_fileset_connections')->whereIn('hash_id', $this->seeded_hash_ids)->delete();
            DB::connection('dbp')->table('bible_filesets')->whereIn('hash_id', $this->seeded_hash_ids)->delete();
            DB::connection('dbp')->table('bibles')->whereIn('id', self::OLD_FORMAT_BIBLES)->delete();
        }
        if ($this->seeded_key_group !== null) {
            DB::connection('dbp_users')->table('access_group_api_keys')
                ->where('access_group_id', $this->seeded_key_group)
                ->where('key_id', Key::where('name', 'test-key')->value('id'))
                ->delete();
        }
        if ($this->seeded_group) {
            DB::connection('dbp')->table('access_groups')->where('id', self::LOCAL_ACCESS_GROUP_ID)->delete();
        }
        parent::tearDown();
    }

    /**
     * A plan with one day per item: [book, chapter, verse_start, verse_end].
     */
    private function createPlan(string $name, array $items): int
    {
        $plan = Plan::create([
            'user_id'              => $this->user_id,
            'name'                 => $name,
            'suggested_start_date' => now()->toDateString(),
            'draft'                => false,
        ]);
        $this->plan_ids[] = $plan->id;

        foreach ($items as $order => [$book, $chapter, $verse_start, $verse_end]) {
            $playlist = Playlist::create([
                'user_id' => $this->user_id,
                'name'    => $name . ' day ' . ($order + 1),
                'plan_id' => $plan->id,
                'draft'   => false,
            ]);
            PlanDay::create([
                'plan_id'      => $plan->id,
                'playlist_id'  => $playlist->id,
                'order_column' => $order + 1,
            ]);
            $item = PlaylistItems::create([
                'playlist_id'   => $playlist->id,
                'fileset_id'    => 'ENGESVN2DA',
                'book_id'       => $book,
                'chapter_start' => $chapter,
                'chapter_end'   => $chapter,
                'verse_start'   => $verse_start,
                'verse_end'     => $verse_end,
                'duration'      => 0,
                'verses'        => 0,
            ]);
            $this->item_ids[$plan->id][] = $item->id;
        }

        return $plan->id;
    }

    /**
     * Post a body; only the route name and the body's keys are echoed. An error status must come with an error body:
     * within one test the route keeps its controller, so after one error a good answer would still carry that status
     * (review of #1161).
     */
    private function postCompatibility(array $body, int $status = 200): array
    {
        echo "\nTesting: v4_internal_plans.compatibility " . json_encode(array_keys($body));
        $response = $this->withHeaders($this->params)->postJson(route('v4_internal_plans.compatibility'), $body);
        $response->assertStatus($status);
        $json = $response->json() ?? [];
        if ($status >= 400) {
            $this->assertSame($status, $json['error']['status_code'] ?? null, 'an error body, not a stale status');
        }

        return $json;
    }

    /** AC10 */
    public function testAKeyOffTheUserDataWhitelistIsForbidden()
    {
        config(['auth.compat_users.api_keys.user_data_access' => 'some-other-key']);

        $this->postCompatibility(['plan_ids' => [$this->plan_id], 'bible_ids' => ['ENGESV']], 403);
    }

    /** AC9 */
    public function testValidationAnswers422()
    {
        $this->postCompatibility(['bible_ids' => ['ENGESV']], 422);
        $this->postCompatibility(['plan_ids' => [], 'bible_ids' => ['ENGESV']], 422);
        $this->postCompatibility(['plan_ids' => $this->plan_id, 'bible_ids' => ['ENGESV']], 422);
        $this->postCompatibility(['plan_ids' => range(1, 21), 'bible_ids' => ['ENGESV']], 422);
        $this->postCompatibility(['plan_ids' => ['x'], 'bible_ids' => ['ENGESV']], 422);
        $this->postCompatibility(['plan_ids' => [$this->plan_id], 'bible_ids' => ['ENG-ESV']], 422);
        $this->postCompatibility(['plan_ids' => range(1, 6), 'bible_ids' => array_map(function ($n) {
            return 'ZZ' . $n;
        }, range(1, 84))], 422);
        $this->postCompatibility(['plan_ids' => [$this->plan_id], 'bible_ids' => ['ENGESV'], 'exact_threshold' => 101], 422);
        // checkParam() reads false and an empty list as absent; they are not a number (review of #1161).
        $this->postCompatibility(['plan_ids' => [$this->plan_id], 'bible_ids' => ['ENGESV'], 'exact_threshold' => false], 422);
        $this->postCompatibility(['plan_ids' => [$this->plan_id], 'bible_ids' => ['ENGESV'], 'exact_threshold' => []], 422);

        $error = $this->postCompatibility(['plan_ids' => ['x'], 'bible_ids' => ['ENG-ESV']], 422)['error'];
        $this->assertSame(422, $error['status_code']);
        $this->assertStringContainsString('plan id', $error['message']);
        $this->assertStringContainsString('Bible id', $error['message']);
    }

    /** AC9: the item-check limit is counted after the plans load */
    public function testTooManyItemChecksAnswers422()
    {
        // 2,001 items times 100 Bibles is 200,100 checks.
        $this->postCompatibility(['plan_ids' => [$this->bigPlan()], 'bible_ids' => $this->manyBibleIds(100)], 422);
    }

    /**
     * AC9: just under the limit. A test of its own, not a second call in the test above: within one test the route
     * keeps its controller, whose status code the first call's 422 would leave set (review of #1161).
     */
    public function testItemChecksJustUnderTheLimitAnswer200()
    {
        // 2,001 items times 99 Bibles is 198,099 checks.
        $this->postCompatibility(['plan_ids' => [$this->bigPlan()], 'bible_ids' => $this->manyBibleIds(99)], 200);
    }

    private function bigPlan() : int
    {
        return $this->createPlan('compatibility limit test plan', array_fill(0, 2001, ['MAT', 1, null, null]));
    }

    private function manyBibleIds(int $count) : array
    {
        return array_map(function ($n) {
            return 'ZZ' . $n;
        }, range(1, $count));
    }

    /** AC9 */
    public function testUnknownAndDeletedIdsGoToNotFound()
    {
        $deleted_plan_id = $this->createPlan('compatibility deleted test plan', [['MAT', 2, null, null]]);
        Plan::where('id', $deleted_plan_id)->delete();
        $unknown_plan_id = (int) Plan::withTrashed()->max('id') + 1000;

        $json = $this->postCompatibility([
            'plan_ids'  => [$this->plan_id, $deleted_plan_id, $unknown_plan_id],
            'bible_ids' => ['ENGESV', 'ZZNOBIBLE1', 'engesv'],
        ]);

        $this->assertSame([$deleted_plan_id, $unknown_plan_id], $json['meta']['not_found']['plan_ids']);
        $this->assertSame(['ZZNOBIBLE1'], $json['meta']['not_found']['bible_ids']);
        $this->assertCount(1, $json['data'], 'engesv repeats ENGESV');
        $entry = $json['data'][0];
        $this->assertSame([$this->plan_id, 'ENGESV'], [$entry['plan_id'], $entry['bible_id']]);
        $this->assertSame(['plan_id', 'bible_id', 'status', 'items', 'formats'], array_keys($entry));
        $this->assertSame(['text', 'audio'], array_keys($entry['formats']));
        $this->assertSame(['status', 'items', 'non_drama', 'drama'], array_keys($entry['formats']['audio']));
        $this->assertSame(2, $entry['items']['total']);
    }

    /** AC7 */
    public function testThePlaylistItems()
    {
        [$mat_1_id, $mat_5_id] = $this->item_ids[$this->plan_id];
        $other_plan_id = $this->createPlan('compatibility second test plan', [['PSA', 23, null, null]]);
        $psa_23_id = $this->item_ids[$other_plan_id][0];

        $json = $this->postCompatibility([
            'plan_ids'          => [$other_plan_id, $this->plan_id],
            'bible_ids'         => ['ENGESV'],
            'playlist_item_ids' => [$mat_5_id, $psa_23_id, $mat_1_id, $mat_5_id],
            'include_video'     => true,
        ]);

        $this->assertCount(2, $json['data']);
        // Each entry has the asked items in its own plan, in the order asked; the repeat counts once.
        $this->assertSame([$psa_23_id], array_column($json['data'][0]['playlist_items'], 'playlist_item_id'));
        $this->assertSame([$mat_5_id, $mat_1_id], array_column($json['data'][1]['playlist_items'], 'playlist_item_id'));
        foreach ($json['data'] as $entry) {
            $this->assertSame(['plan_id', 'bible_id', 'status', 'items', 'formats', 'playlist_items'], array_keys($entry));
            $this->assertArrayHasKey('video', $entry['formats']);
            foreach ($entry['playlist_items'] as $item) {
                $this->assertSame(['playlist_item_id', 'status', 'formats'], array_keys($item));
                $this->assertContains($item['status'], ['full', 'approximate', 'mismatch', 'incompatible_mismatch', 'incompatible_missing']);
                $this->assertSame(['text', 'audio', 'video'], array_keys($item['formats']));
                $this->assertSame(['status', 'non_drama', 'drama'], array_keys($item['formats']['audio']));
            }
        }
        // MAT 5:1-12 is in ENGESV's text. Audio outranks text: with its NT stream (cut at any verse) the item is full;
        // with only files per chapter visible to the key it is mismatch, the exact text keeping it playable.
        $mat_5 = $json['data'][1]['playlist_items'][0];
        $this->assertSame('exact', $mat_5['formats']['text']);
        $this->assertSame($mat_5['formats']['audio']['status'] === 'full' ? 'full' : 'mismatch', $mat_5['status']);

        // An empty list is the same as none.
        $none = $this->postCompatibility([
            'plan_ids'          => [$this->plan_id],
            'bible_ids'         => ['ENGESV'],
            'playlist_item_ids' => [],
        ]);
        $this->assertArrayNotHasKey('playlist_items', $none['data'][0]);

        // An id in none of the plans, or a malformed list.
        foreach ([[$mat_5_id], 0, [0], range(1, 21)] as $bad) {
            $this->postCompatibility([
                'plan_ids'          => [$other_plan_id],
                'bible_ids'         => ['ENGESV'],
                'playlist_item_ids' => $bad,
            ], 422);
        }
    }

    /** AC24 (R24): an asked item of a requested plan that is deleted is ignored, with no error */
    public function testAnItemOfADeletedRequestedPlanIsIgnored()
    {
        $deleted_plan_id = $this->createPlan('compatibility deleted item test plan', [['MAT', 2, null, null]]);
        $deleted_item_id = $this->item_ids[$deleted_plan_id][0];
        // As PlansController::destroy deletes a plan: its playlists soft-deleted, its days removed, the plan
        // soft-deleted.
        Playlist::where('plan_id', $deleted_plan_id)->delete();
        PlanDay::where('plan_id', $deleted_plan_id)->delete();
        Plan::where('id', $deleted_plan_id)->delete();
        $mat_1_id = $this->item_ids[$this->plan_id][0];

        $json = $this->postCompatibility([
            'plan_ids'          => [$this->plan_id, $deleted_plan_id],
            'bible_ids'         => ['ENGESV'],
            'playlist_item_ids' => [$deleted_item_id, $mat_1_id],
        ]);

        $this->assertSame([$deleted_plan_id], $json['meta']['not_found']['plan_ids']);
        $this->assertCount(1, $json['data']);
        $this->assertSame([$mat_1_id], array_column($json['data'][0]['playlist_items'], 'playlist_item_id'));

        // When its plan isn't requested, the item is a 422 like any item of no requested plan.
        $this->postCompatibility([
            'plan_ids'          => [$this->plan_id],
            'bible_ids'         => ['ENGESV'],
            'playlist_item_ids' => [$deleted_item_id],
        ], 422);
    }

    /** AC23 (R23): with allow_chapter_audio nothing is mismatch */
    public function testWithAllowChapterAudioNothingIsMismatch()
    {
        $json = $this->postCompatibility([
            'plan_ids'            => [$this->plan_id],
            'bible_ids'           => ['ENGESV'],
            'playlist_item_ids'   => $this->item_ids[$this->plan_id],
            'allow_chapter_audio' => true,
        ]);

        $body = json_encode($json['data']);
        $this->assertDoesNotMatchRegularExpression('/:"(incompatible_)?mismatch"/', $body);
        $this->assertDoesNotMatchRegularExpression('/"mismatch":[1-9]/', $body);
    }

    /** AC11: a fileset the key may not see never counts */
    public function testOnlyFilesetsTheKeyMaySeeCount()
    {
        $service = new PlanCompatibilityService();
        $groups = new AccessGroupsCollection(AccessGroupKey::getAccessGroupIdsByApiKey($this->key));

        $visible = array_column($service->getVisibleFilesets('ENGESV', $groups), 'hash_id');
        $this->assertNotEmpty($visible);

        // Every loaded fileset of the counted types: visible to the key exactly when isContentAvailable says so.
        $all = DB::connection('dbp')->table('bible_filesets')
            ->join('bible_fileset_connections', 'bible_fileset_connections.hash_id', 'bible_filesets.hash_id')
            ->where('bible_fileset_connections.bible_id', 'ENGESV')
            ->whereIn('bible_filesets.set_type_code', array_keys(PlanCompatibilityCalculator::FORMAT_BY_SET_TYPE))
            ->where('bible_filesets.content_loaded', true)
            ->where('bible_filesets.archived', false)
            ->pluck('bible_filesets.hash_id');
        foreach ($all as $hash_id) {
            $allowed = BibleFileset::where('bible_filesets.hash_id', $hash_id)->isContentAvailable($groups)->exists();
            if (!$allowed) {
                $this->assertNotContains($hash_id, $visible);
            }
        }
        foreach ($visible as $hash_id) {
            $this->assertTrue(BibleFileset::where('bible_filesets.hash_id', $hash_id)->isContentAvailable($groups)->exists());
        }

        // An access group no fileset is in: only what isContentAvailable lets every caller see (finding F1: a fileset
        // whose license group has no permission pattern), never anything else.
        $nobody = new AccessGroupsCollection([-1]);
        foreach (array_column($service->getVisibleFilesets('ENGESV', $nobody), 'hash_id') as $hash_id) {
            $this->assertTrue(BibleFileset::where('bible_filesets.hash_id', $hash_id)->isContentAvailable($nobody)->exists());
        }
    }

    /**
     * Revision 6 (speed design section 4): the old-format rules now run only on the candidate rows, and the list is
     * the one the full query with both rules gives. Seeds Bible ZZOLDFMT1 with an old six-character text that its
     * ten-character text replaces and one with no replacement, one whose replacement is archived, old 16 kbps audio
     * with and without its -opus16 copy, and a candidate that isn't visible; and ZZOLDFMT2, with nothing visible. No
     * license group, so every key may see them, as isContentAvailable() lets every caller see such a fileset.
     */
    public function testTheOldFormatRulesDropWhatTheFullQueryDrops()
    {
        $this->seedOldFormatBibles();

        $expected = [
            'ZZOLDAN1DA', 'ZZOLDAN1DA-opus16', 'ZZOLDAN_ET', 'ZZOLDB', 'ZZOLDBN1DA16', 'ZZOLDC',
        ];
        foreach ([new AccessGroupsCollection([-1]), new AccessGroupsCollection([-2, -3])] as $groups) {
            $visible = array_column((new PlanCompatibilityService())->getVisibleFilesets('ZZOLDFMT1', $groups), 'id');
            sort($visible);
            $this->assertSame($expected, $visible, 'ZZOLDA and ZZOLDAN1DA16 are replaced; ZZOLDD is not visible');

            // Today's builder, with both rules on the whole list (the query before revision 6).
            $full = Bible::find('ZZOLDFMT1')->filesetsWithoutMeta()
                ->isContentAvailable($groups)
                ->conditionToExcludeOldTextFormat()
                ->conditionToExcludeOldDA16Format()
                ->pluck('bible_filesets.id')
                ->sort()
                ->values()
                ->all();
            $this->assertSame($full, $visible, 'the same list as the full query');

            $this->assertSame([], (new PlanCompatibilityService())->getVisibleFilesets('ZZOLDFMT2', $groups));
        }

        // Only the candidates were checked: ZZOLDA, ZZOLDB, ZZOLDC, ZZOLDAN1DA16 and ZZOLDBN1DA16 (the array store off
        // prod keeps a verdict for a second, so a fresh service in a new second checks them again).
        sleep(2);
        $service = new PlanCompatibilityService();
        $service->getVisibleFilesets('ZZOLDFMT1', new AccessGroupsCollection([-4]));
        $this->assertSame(5, $service->counts()['old_format_checks']);
    }

    private function seedOldFormatBibles() : void
    {
        $db = DB::connection('dbp');
        // [id, set_type_code, set_size_code, content_loaded, archived, Bible]; row n gets hash_id zzoldfmt000n
        $filesets = [
            ['ZZOLDA', 'text_plain', 'NT', 1, 0, 'ZZOLDFMT1'],              // replaced by ZZOLDAN_ET
            ['ZZOLDAN_ET', 'text_plain', 'NT', 1, 0, 'ZZOLDFMT1'],
            ['ZZOLDB', 'text_plain', 'C', 1, 0, 'ZZOLDFMT1'],               // no ten-character text
            ['ZZOLDC', 'text_plain', 'OT', 1, 0, 'ZZOLDFMT1'],              // its replacement is archived
            ['ZZOLDCO_ET', 'text_plain', 'OT', 1, 1, 'ZZOLDFMT1'],
            ['ZZOLDD', 'text_plain', 'NT', 0, 0, 'ZZOLDFMT1'],              // a candidate the key can't see
            ['ZZOLDAN1DA16', 'audio', 'NT', 1, 0, 'ZZOLDFMT1'],             // replaced by ZZOLDAN1DA-opus16
            ['ZZOLDAN1DA-opus16', 'audio', 'NT', 1, 0, 'ZZOLDFMT1'],
            ['ZZOLDAN1DA', 'audio', 'NT', 1, 0, 'ZZOLDFMT1'],               // not a candidate
            ['ZZOLDBN1DA16', 'audio', 'NT', 1, 0, 'ZZOLDFMT1'],             // no -opus16 copy
            ['ZZOLDEN1DA', 'audio', 'NT', 1, 1, 'ZZOLDFMT2'],               // archived: nothing visible
        ];
        $hash_ids = array_map(function ($n) {
            return sprintf('zzoldfmt%04d', $n);
        }, array_keys($filesets));
        // Exactly the rows this seeds, in case a crashed run left them behind (review of #1164).
        $db->table('bible_fileset_connections')->whereIn('hash_id', $hash_ids)->delete();
        $db->table('bible_filesets')->whereIn('hash_id', $hash_ids)->delete();
        $db->table('bibles')->whereIn('id', self::OLD_FORMAT_BIBLES)->delete();
        $db->table('bibles')->insert([
            ['id' => 'ZZOLDFMT1', 'language_id' => 6414],
            ['id' => 'ZZOLDFMT2', 'language_id' => 6414],
        ]);
        foreach ($filesets as $n => [$id, $type, $size, $loaded, $archived, $bible_id]) {
            $hash_id = $hash_ids[$n];
            $this->seeded_hash_ids[] = $hash_id;
            $db->table('bible_filesets')->insert([
                'id' => $id, 'hash_id' => $hash_id, 'set_type_code' => $type, 'set_size_code' => $size, 'mode_id' => 1,
                'content_loaded' => $loaded, 'archived' => $archived,
            ]);
            $db->table('bible_fileset_connections')->insert(['hash_id' => $hash_id, 'bible_id' => $bible_id]);
        }
    }

    /** AC4 (revision 6): past the time limit, a call answers 503 with its own action, and logs that it timed out */
    public function testACallPastTheTimeLimitAnswers503WithItsOwnAction()
    {
        if (AccessGroupKey::getAccessGroupIdsByApiKey($this->key)->isEmpty()) {
            // AccessControl answers 422 for a key with no access group (a throwaway database). The link needs a real
            // group where the foreign key is enforced (review X1-5): an existing one, else one made for the test.
            $group_id = DB::connection('dbp')->table('access_groups')->min('id');
            if ($group_id === null) {
                DB::connection('dbp')->table('access_groups')->insert([
                    'id'          => self::LOCAL_ACCESS_GROUP_ID,
                    'name'        => 'local test group',
                    'description' => 'Throwaway local test database only',
                ]);
                $group_id = self::LOCAL_ACCESS_GROUP_ID;
                $this->seeded_group = true;
            }
            DB::connection('dbp_users')->table('access_group_api_keys')->insert([
                'access_group_id' => $group_id,
                'key_id'          => Key::where('name', 'test-key')->value('id'),
            ]);
            $this->seeded_key_group = (int) $group_id;
        }
        config(['plan_compat.time_limit' => -1]);
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            if ($event->message === 'plan_compatibility') {
                $logged[] = $event->context;
            }
        });

        $error = $this->postCompatibility(['plan_ids' => [$this->plan_id], 'bible_ids' => ['ENGESV']], 503)['error'];

        $this->assertSame(PlanCompatibilityController::TIME_LIMIT_ACTION, $error['action']);
        $this->assertSame(PlanCompatibilityController::TIME_LIMIT_MESSAGE, $error['message']);
        $this->assertCount(1, $logged, 'one log line per call');
        $this->assertTrue($logged[0]['timed_out']);
        $this->assertSame(0, $logged[0]['bibles_checked']);
        $this->assertSame(1, $logged[0]['plans']);
    }
}
