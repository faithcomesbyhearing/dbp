<?php

namespace Tests\Integration;

use App\Models\Plan\Plan;
use App\Models\Plan\PlanDay;
use App\Models\Plan\PlanLocalization;
use App\Models\Plan\UserPlan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\User\Key;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

/**
 * Coverage for GET /api/plans/{plan_id}?localized=true (v4_internal_plans.show), API task 104292: Plan Details shown
 * in the app language through plans_localization, with the same row the plan list (GET /api/plans?localized=true,
 * task 102556) uses for the same parameters.
 *
 * With `localized=true`, `iso` (required) and the optional `alt_locale` are the app locale. The row used is the exact
 * (iso, alt_locale) row, else the (iso, NULL) row, else the lowest-id row in iso, whatever the plan's featured or draft
 * state. `name` is the row's, `thumbnail` the row's unless it has none, and two keys follow `thumbnail`:
 * `description` (the row's, or null) and `localization` ({id, iso, alt_locale, bible_id}, or null). A plan with no row
 * in the language keeps its own name and thumbnail with both keys null. Without the flag (any other value too) the
 * response is what it was.
 *
 * Seeds, in language codes no language uses (so no other row can match):
 *  - plan P, featured, two days with one item each: rows (qqz, NULL) with a description and no thumbnail,
 *    (qqz, qq-CA), and (qqy, NULL);
 *  - plan Q, not featured: rows (qqz, qq-CA), then (qqz, qq-BE), so qqz alone falls to the lowest id, qq-CA;
 *  - plan N, not featured, no rows.
 * The test-key user owns all three and has them in its plans.
 *
 * Seeds data, so run it only against a throwaway test database, never a shared one: P would show on the Discover
 * list while the test runs. The DB-free parts (the transformer's keys and order) are in
 * tests/Feature/PlanShowLocalizedTest.php; the parse and the scope's SQL in tests/Feature/PlanLocalizedListTest.php.
 *
 * Every request that ends in an error status runs in its own test method: replyWithError() sets the controller's
 * status code, and the controller instance is reused by later requests in the same test.
 */
class PlanShowLocalizedTest extends TestCase
{
    private const ISO = 'qqz';
    private const OTHER_ISO = 'qqy';
    private const UNUSED_ISO = 'qqx';

    protected $key;
    protected $params;
    private $user_id;
    private $key_user;
    private $project_id;
    private $created_role_id;
    private $plan_ids = [];
    private $p;
    private $q;
    private $n;
    private $rows = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::connection('dbp_users')->hasTable('plans_localization')) {
            $this->markTestSkipped('dbp_users.plans_localization does not exist on this database (PBI 103276).');
        }
        if (PlanLocalization::whereIn('iso', [self::ISO, self::OTHER_ISO, self::UNUSED_ISO])->exists()) {
            $this->markTestSkipped('The database already holds localizations in the test codes qqz/qqy/qqx.');
        }

        $key = Key::where('name', 'test-key')->first();
        $this->key      = $key->key;
        $this->params   = ['v' => 4, 'key' => $this->key];
        $this->user_id  = $key->user_id;
        $this->key_user = $key->user;

        // v4_internal_plans.show and .index sit behind UserDataAccess, which only lets whitelisted keys through.
        config(['auth.compat_users.api_keys.user_data_access' => $this->key]);

        // compareProjects() requires the test-key user to share a project with itself. projects.id is smallint
        // unsigned and not auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create(['id' => $this->project_id, 'name' => 'localized plan details test project']);
        // Role declares no $fillable; forceCreate() bypasses mass-assignment protection.
        $role = Role::where('slug', 'developer')->first();
        if (!$role) {
            $role = Role::forceCreate(['name' => 'developer', 'slug' => 'developer', 'description' => 'Developer']);
            $this->created_role_id = $role->id;
        }
        ProjectMember::create([
            'project_id' => $this->project_id,
            'user_id'    => $this->user_id,
            'role_id'    => $role->id,
            'token'      => unique_random('dbp_users.project_members', 'token', 12),
        ]);

        $this->p = $this->seedPlan('zz localized details test P', 'p.png', true, 2);
        $this->q = $this->seedPlan('zz localized details test Q', 'q.png', false, 0);
        $this->n = $this->seedPlan('zz localized details test N', 'n.png', false, 0);

        $this->rows['p_qqz']    = $this->seedLocalization($this->p, self::ISO, null, 'Bravo', null, 'Bravo description', 'ENGESV');
        $this->rows['p_qqz_ca'] = $this->seedLocalization($this->p, self::ISO, 'qq-CA', 'Alpha CA', 'p-ca.png', null, null);
        $this->rows['p_qqy']    = $this->seedLocalization($this->p, self::OTHER_ISO, null, 'Other', 'p-other.png', null, 'ENGNIV');
        $this->rows['q_qqz_ca'] = $this->seedLocalization($this->q, self::ISO, 'qq-CA', 'Charlie CA', 'q-ca.png', null, null);
        $this->rows['q_qqz_be'] = $this->seedLocalization($this->q, self::ISO, 'qq-BE', 'Charlie BE', 'q-be.png', 'Charlie description', null);
    }

    protected function tearDown(): void
    {
        foreach ($this->plan_ids as $plan_id) {
            // Playlists first: FK cascade removes playlist_items. The plan's cascade removes plan_days, user_plans and
            // its localizations.
            Playlist::where('plan_id', $plan_id)->forceDelete();
            Plan::withTrashed()->where('id', $plan_id)->forceDelete();
        }
        ProjectMember::where('project_id', $this->project_id)->delete();
        Project::withTrashed()->where('id', $this->project_id)->forceDelete();
        if ($this->created_role_id) {
            Role::where('id', $this->created_role_id)->delete();
        }
        parent::tearDown();
    }

    /**
     * A plan owned by the test user and in its plans (not started), with $day_count days of one playlist item each.
     */
    private function seedPlan(string $name, string $thumbnail, bool $featured, int $day_count): int
    {
        $plan = Plan::forceCreate([
            'user_id'              => $this->user_id,
            'name'                 => $name,
            'thumbnail'            => $thumbnail,
            'suggested_start_date' => now()->toDateString(),
            'featured'             => $featured,
            'draft'                => false,
        ]);
        $this->plan_ids[] = $plan->id;

        for ($order = 1; $order <= $day_count; $order++) {
            $playlist = Playlist::create([
                'user_id' => $this->user_id,
                'name'    => $name . ' day ' . $order,
                'plan_id' => $plan->id,
                'draft'   => false,
            ]);
            PlanDay::create([
                'plan_id'      => $plan->id,
                'playlist_id'  => $playlist->id,
                'order_column' => $order,
            ]);
            PlaylistItems::create([
                'playlist_id'   => $playlist->id,
                'fileset_id'    => 'ENGESVN2DA',
                'book_id'       => 'MAT',
                'chapter_start' => $order,
                'chapter_end'   => $order,
                'verse_start'   => 1,
                'duration'      => 0,
            ]);
        }

        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $plan->id,
            'percentage_completed' => 0,
        ]);

        return $plan->id;
    }

    private function seedLocalization(
        int $plan_id,
        string $iso,
        ?string $alt_locale,
        string $name,
        ?string $thumbnail,
        ?string $description,
        ?string $bible_id
    ): int {
        return PlanLocalization::create([
            'plan_id'     => $plan_id,
            'iso'         => $iso,
            'alt_locale'  => $alt_locale,
            'name'        => $name,
            'thumbnail'   => $thumbnail,
            'description' => $description,
            'bible_id'    => $bible_id,
        ])->id;
    }

    /**
     * Call Plan Details. `key` and `v` travel only as headers (checkParam reads headers first), so the URL holds no
     * secret; only the route name and the query parameters are echoed.
     */
    private function fetch(int $plan_id, array $extra, bool $as_token_user = true)
    {
        if ($as_token_user) {
            // Bypass the APIToken middleware's api_token requirement; compareProjects() still runs.
            $this->actingAs($this->key_user, 'tokens');
        }

        echo "\nTesting: v4_internal_plans.show " . json_encode(['plan_id' => $plan_id] + $extra);
        return $this->withHeaders($this->params)->get(route('v4_internal_plans.show', ['plan_id' => $plan_id] + $extra));
    }

    /** The response after a status check, without letting the key reach the test output. */
    private function expectStatus($response, int $status)
    {
        try {
            $response->assertStatus($status);
        } catch (ExpectationFailedException $e) {
            throw new ExpectationFailedException(str_replace($this->key, '<KEY>', $e->getMessage()));
        }

        return $response;
    }

    private function show(int $plan_id, array $extra = [], bool $as_token_user = true): array
    {
        return $this->expectStatus($this->fetch($plan_id, $extra, $as_token_user), 200)->json();
    }

    private function localized(int $plan_id, array $extra = [], bool $as_token_user = true): array
    {
        return $this->show($plan_id, $extra + ['localized' => 'true', 'iso' => self::ISO], $as_token_user);
    }

    /** The plan list, localized, as the token user (My Plans) or for Discover; the seeded plans by id. */
    private function listed(bool $featured, array $locale): array
    {
        if (!$featured) {
            $this->actingAs($this->key_user, 'tokens');
        }
        $filters = ['featured' => $featured ? 'true' : 'false', 'localized' => 'true', 'limit' => 1000] + $locale;
        echo "\nTesting: v4_internal_plans.index " . json_encode($filters);
        $body = $this->expectStatus($this->withHeaders($this->params)->get(route('v4_internal_plans.index', $filters)), 200)->json();

        $plans = [];
        foreach ($body['data'] as $plan) {
            if (in_array((int) $plan['id'], $this->plan_ids, true)) {
                $plans[(int) $plan['id']] = $plan;
            }
        }

        return $plans;
    }

    private function localizationOf(string $row, string $iso, ?string $alt_locale, ?string $bible_id): array
    {
        return ['id' => $this->rows[$row], 'iso' => $iso, 'alt_locale' => $alt_locale, 'bible_id' => $bible_id];
    }

    /**
     * AC1, AC2: the row's name and thumbnail, its description, and `localization` as stored, right after `thumbnail`;
     * a row with no thumbnail keeps the plan's.
     *
     * @group V4
     * @test
     */
    public function aRowGivesItsNameThumbnailDescriptionAndLocalization()
    {
        $plan = $this->localized($this->p, ['iso' => self::OTHER_ISO]);
        $this->assertSame(['id', 'name', 'thumbnail', 'description', 'localization', 'featured'], array_slice(array_keys($plan), 0, 6));
        $this->assertSame('Other', $plan['name']);
        $this->assertSame('p-other.png', $plan['thumbnail']);
        $this->assertNull($plan['description']);
        $this->assertSame($this->localizationOf('p_qqy', self::OTHER_ISO, null, 'ENGNIV'), $plan['localization']);
        $this->assertIsInt($plan['localization']['id']);

        $plan = $this->localized($this->p);
        $this->assertSame('Bravo', $plan['name']);
        $this->assertSame('p.png', $plan['thumbnail']);
        $this->assertSame('Bravo description', $plan['description']);
        $this->assertSame($this->localizationOf('p_qqz', self::ISO, null, 'ENGESV'), $plan['localization']);
    }

    /**
     * AC3: the exact alt_locale row, case-insensitively; else the NULL row; else the lowest-id row in the language.
     * Another language's locale is ignored.
     *
     * @group V4
     * @test
     */
    public function theRowFollowsTheResolutionOrder()
    {
        $cases = [
            [$this->p, ['iso' => self::ISO], 'p_qqz'],
            [$this->p, ['iso' => strtoupper(self::ISO)], 'p_qqz'],
            [$this->p, ['alt_locale' => 'qq-BE'], 'p_qqz'],
            [$this->p, ['alt_locale' => 'qq'], 'p_qqz'],
            [$this->p, ['alt_locale' => ''], 'p_qqz'],
            [$this->p, ['alt_locale' => 'qq-CA'], 'p_qqz_ca'],
            [$this->p, ['alt_locale' => 'QQ-ca'], 'p_qqz_ca'],
            [$this->p, ['iso' => self::OTHER_ISO, 'alt_locale' => 'qq-CA'], 'p_qqy'],
            [$this->q, ['iso' => self::ISO], 'q_qqz_ca'],
            [$this->q, ['alt_locale' => 'qq-US'], 'q_qqz_ca'],
            [$this->q, ['alt_locale' => 'qq-BE'], 'q_qqz_be'],
            [$this->q, ['alt_locale' => 'QQ-be'], 'q_qqz_be'],
        ];

        foreach ($cases as [$plan_id, $locale, $row]) {
            $plan = $this->localized($plan_id, $locale);
            $this->assertSame($this->rows[$row], $plan['localization']['id'] ?? null, $row . ' ' . json_encode($locale));
        }

        $be = $this->localized($this->q, ['alt_locale' => 'qq-BE']);
        $this->assertSame('Charlie BE', $be['name']);
        $this->assertSame('q-be.png', $be['thumbnail']);
        $this->assertSame('Charlie description', $be['description']);
    }

    /**
     * AC4: no row in the language: 200, the plan's own name and thumbnail, both keys null, everything else as without
     * the flag. P has no row in qqx; N has none at all.
     *
     * @group V4
     * @test
     */
    public function withoutARowThePlanIsReturnedAsItIs()
    {
        foreach ([[$this->p, self::UNUSED_ISO, 'zz localized details test P', 'p.png'], [$this->n, self::ISO, 'zz localized details test N', 'n.png']] as [$plan_id, $iso, $name, $thumbnail]) {
            $plain = $this->show($plan_id);
            $plan = $this->localized($plan_id, ['iso' => $iso]);

            $this->assertSame($name, $plan['name']);
            $this->assertSame($thumbnail, $plan['thumbnail']);
            $this->assertArrayHasKey('description', $plan);
            $this->assertNull($plan['description']);
            $this->assertArrayHasKey('localization', $plan);
            $this->assertNull($plan['localization']);
            $this->assertSame($plain, array_diff_key($plan, ['description' => 0, 'localization' => 0]));
        }
    }

    /**
     * P3: a row applies whatever the plan's featured or draft state. Q is not featured; P is made a draft here.
     *
     * @group V4
     * @test
     */
    public function featuredAndDraftDoNotMatter()
    {
        $this->assertSame('Charlie CA', $this->localized($this->q)['name']);

        // toBase() skips Eloquent's timestamps, so the plan's updated_at stays as it was.
        Plan::whereKey($this->p)->toBase()->update(['draft' => 1]);
        $plan = $this->localized($this->p);
        $this->assertTrue((bool) $plan['draft']);
        $this->assertSame('Bravo', $plan['name']);
        $this->assertSame($this->rows['p_qqz'], $plan['localization']['id']);
    }

    /**
     * AC6: for the same parameters the plan list shows the same name, thumbnail and localization as Plan Details:
     * Discover (featured plans with a row in the language) and the user's own plans (every plan).
     *
     * @group V4
     * @test
     */
    public function theListAndTheDetailsAgree()
    {
        $locales = [
            ['iso' => self::ISO],
            ['iso' => strtoupper(self::ISO)],
            ['iso' => self::ISO, 'alt_locale' => 'qq-CA'],
            ['iso' => self::ISO, 'alt_locale' => 'qq-ca'],
            ['iso' => self::ISO, 'alt_locale' => 'qq-BE'],
            ['iso' => self::OTHER_ISO, 'alt_locale' => 'qq-CA'],
            ['iso' => self::UNUSED_ISO],
        ];

        foreach ($locales as $locale) {
            $discover = $this->listed(true, $locale);
            $my_plans = $this->listed(false, $locale);

            foreach ([$this->p, $this->q, $this->n] as $plan_id) {
                $details = $this->show($plan_id, ['localized' => 'true'] + $locale);
                $message = $plan_id . ' ' . json_encode($locale);

                $this->assertArrayHasKey($plan_id, $my_plans, $message);
                foreach (['name', 'thumbnail', 'localization'] as $key) {
                    $this->assertSame($my_plans[$plan_id][$key], $details[$key], $message . ' My Plans ' . $key);
                }

                if ($plan_id === $this->p && $details['localization'] !== null) {
                    $this->assertArrayHasKey($plan_id, $discover, $message);
                    foreach (['name', 'thumbnail', 'localization'] as $key) {
                        $this->assertSame($discover[$plan_id][$key], $details[$key], $message . ' Discover ' . $key);
                    }
                } else {
                    // Not featured, or no row in the language: Discover leaves it out.
                    $this->assertArrayNotHasKey($plan_id, $discover, $message);
                }
            }
        }
    }

    /**
     * AC7: without localized=true (absent, false, 1, yes, a list), and with iso or alt_locale alone, the response is
     * byte for byte the plain one, with neither key.
     *
     * @group V4
     * @test
     */
    public function anyOtherValueLeavesTheResponseAsItWas()
    {
        $plain = $this->expectStatus($this->fetch($this->p, []), 200)->getContent();
        $this->assertStringNotContainsString('"description"', $plain);
        $this->assertStringNotContainsString('"localization"', $plain);

        $variants = [
            ['localized' => 'false', 'iso' => self::ISO],
            ['localized' => '1', 'iso' => self::ISO],
            ['localized' => 'yes', 'iso' => self::ISO],
            ['localized' => ['true'], 'iso' => self::ISO],
            ['localized' => '', 'iso' => self::ISO],
            ['iso' => self::ISO],
            ['alt_locale' => 'qq-CA'],
            ['iso' => [self::ISO]],
            ['localized' => 'false', 'iso' => [self::ISO], 'alt_locale' => ['qq-CA']],
        ];
        foreach ($variants as $query) {
            $this->assertSame($plain, $this->expectStatus($this->fetch($this->p, $query), 200)->getContent(), json_encode($query));
        }
    }

    /**
     * The flag in any case, and as a JSON true (checkParam reads the JSON body of a GET).
     *
     * @group V4
     * @test
     */
    public function theFlagIsTheWordTrueInAnyCaseOrJsonTrue()
    {
        foreach (['TRUE', 'True'] as $value) {
            $this->assertSame('Bravo', $this->show($this->p, ['localized' => $value, 'iso' => self::ISO])['name'], $value);
        }

        $this->actingAs($this->key_user, 'tokens');
        echo "\nTesting: v4_internal_plans.show JSON {\"localized\":true,\"iso\":\"qqz\"}";
        $response = $this->withHeaders($this->params)->json(
            'GET',
            route('v4_internal_plans.show', ['plan_id' => $this->p]),
            ['localized' => true, 'iso' => self::ISO]
        );
        $this->assertSame('Bravo', $this->expectStatus($response, 200)->json()['name']);
    }

    /**
     * AC8: in localized mode every other key equals the same request without the flag, days and their playlist items
     * and verse text included.
     *
     * @group V4
     * @test
     */
    public function theStructureIsUntouched()
    {
        $changed = ['name' => 0, 'thumbnail' => 0, 'description' => 0, 'localization' => 0];
        foreach ([[], ['show_details' => 'true'], ['show_text' => 'true']] as $extra) {
            $plain = $this->show($this->p, $extra);
            $localized = $this->localized($this->p, $extra);

            $this->assertSame(array_diff_key($plain, $changed), array_diff_key($localized, $changed), json_encode($extra));
            $this->assertNotEmpty($localized['days']);
        }
    }

    /**
     * AC9: together with include_user_bible and include_completed_at every key is present and nothing else differs.
     *
     * @group V4
     * @test
     */
    public function worksWithTheOtherFlags()
    {
        $flags = ['include_user_bible' => 'true', 'include_completed_at' => 'true'];
        $plain = $this->show($this->p, $flags);
        $localized = $this->localized($this->p, $flags);

        $this->assertArrayHasKey('user_bible', $localized);
        $this->assertArrayHasKey('completed_at', $localized['days'][0]);
        $this->assertSame('Bravo description', $localized['description']);
        $this->assertSame(
            array_diff_key($plain, ['name' => 0, 'thumbnail' => 0]),
            array_diff_key($localized, ['name' => 0, 'thumbnail' => 0, 'description' => 0, 'localization' => 0])
        );
    }

    /**
     * AC9 without a token. Its own test because actingAs() in an earlier call would keep the request authenticated.
     *
     * @group V4
     * @test
     */
    public function worksWithoutAToken()
    {
        $plan = $this->localized($this->p, ['alt_locale' => 'qq-CA'], false);

        $this->assertSame('Alpha CA', $plan['name']);
        $this->assertSame($this->localizationOf('p_qqz_ca', self::ISO, 'qq-CA', null), $plan['localization']);
        $this->assertNull($plan['description']);
    }

    public static function refusedProvider(): array
    {
        $missing = 'iso is required when localized=true';
        $iso_list = 'iso must be a single value';
        $alt_list = 'alt_locale must be a single value';

        return [
            // AC5
            'iso missing'                       => [['localized' => 'true'], $missing],
            'iso empty'                         => [['localized' => 'true', 'iso' => ''], $missing],
            'iso missing, alt_locale sent'      => [['localized' => 'TRUE', 'alt_locale' => 'qq-CA'], $missing],
            // AC10, in its order: a list iso, a list alt_locale, a missing iso
            'iso list'                          => [['localized' => 'true', 'iso' => [self::ISO]], $iso_list],
            'alt_locale list'                   => [['localized' => 'true', 'iso' => self::ISO, 'alt_locale' => ['qq-CA']], $alt_list],
            'iso list beats alt_locale list'    => [['localized' => 'true', 'iso' => [self::ISO], 'alt_locale' => ['qq-CA']], $iso_list],
            'alt_locale list beats missing iso' => [['localized' => 'true', 'alt_locale' => ['qq-CA']], $alt_list],
        ];
    }

    /**
     * AC5, AC10: each refused value is a 422 with its message, never a 500.
     *
     * @dataProvider refusedProvider
     * @group V4
     * @test
     */
    public function refusedValuesGiveTheMessage(array $query, string $message)
    {
        $this->expectStatus($this->fetch($this->p, $query), 422)->assertJsonPath('error.message', $message);
    }

    /**
     * AC11: an unknown plan is still 404 Plan Not Found, also when the locale parameters would be refused.
     *
     * @group V4
     * @test
     */
    public function anUnknownPlanIsStill404()
    {
        $missing = (int) Plan::withTrashed()->max('id') + 1000;
        $this->expectStatus($this->fetch($missing, ['localized' => 'true', 'iso' => self::ISO]), 404)
            ->assertJsonPath('error.message', 'Plan Not Found');
    }

    /**
     * AC11: the 404 comes before the locale checks.
     *
     * @group V4
     * @test
     */
    public function anUnknownPlanIs404BeforeTheLocaleChecks()
    {
        $missing = (int) Plan::withTrashed()->max('id') + 1000;
        $this->expectStatus($this->fetch($missing, ['localized' => 'true']), 404)
            ->assertJsonPath('error.message', 'Plan Not Found');
    }

    /**
     * AC11: a soft-deleted plan with rows is 404 Plan Not Found.
     *
     * @group V4
     * @test
     */
    public function aSoftDeletedPlanIsStill404()
    {
        Plan::whereKey($this->p)->first()->delete();
        $this->expectStatus($this->fetch($this->p, ['localized' => 'true', 'iso' => self::ISO]), 404)
            ->assertJsonPath('error.message', 'Plan Not Found');
    }

    /**
     * AC11: a token user who shares no project with the key still gets the 401.
     *
     * @group V4
     * @test
     */
    public function aUserOutsideTheKeysProjectsIsStill401()
    {
        $key_projects = ProjectMember::where('user_id', $this->user_id)->pluck('project_id');
        $outsider = User::where('id', '!=', $this->user_id)
            ->whereNotIn('id', ProjectMember::whereIn('project_id', $key_projects)->select('user_id'))
            ->orderBy('id')
            ->first();
        if (!$outsider) {
            $this->markTestSkipped('Needs a user who shares no project with the test key\'s owner.');
        }

        $this->actingAs($outsider, 'tokens');
        echo "\nTesting: v4_internal_plans.show " . json_encode(['plan_id' => $this->p, 'localized' => 'true', 'iso' => self::ISO]);
        $response = $this->withHeaders($this->params)->get(
            route('v4_internal_plans.show', ['plan_id' => $this->p, 'localized' => 'true', 'iso' => self::ISO])
        );
        $this->expectStatus($response, 401)->assertJsonPath('error.message', trans('api.projects_users_not_connected'));
    }
}
