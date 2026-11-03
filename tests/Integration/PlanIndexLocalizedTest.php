<?php

namespace Tests\Integration;

use App\Models\Plan\Plan;
use App\Models\Plan\PlanLocalization;
use App\Models\Plan\UserPlan;
use App\Models\User\Key;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\Role;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

/**
 * Coverage for GET /api/plans?localized=true (v4_internal_plans.index, API task 102556): the Discover and My Plans
 * lists shown in the app language through plans_localization.
 *
 * With `localized=true`, `iso` (required) and the optional `alt_locale` are the app locale. Per plan the row used is
 * the exact (iso, alt_locale) row, else the (iso, NULL) row, else the lowest-id row in iso. Discover leaves out
 * plans with no row in the language; My Plans keeps every plan, a plan with no row showing its own name and
 * thumbnail with `localization: null`. Without the flag (any other value too) every response is what it was.
 *
 * Seeds, in a language code no language uses (so no other row can join): plan A, featured, with a (qqz, NULL) row
 * without thumbnail and a (qqz, qq-CA) row; plan B, featured, with only a (qqz, qq-CA) row; plan C, featured, with
 * only a row in another code; plan D, not featured. The test-key user has A, C and D in its plans. A was created
 * before B, so created_at orders them apart from the name order.
 *
 * Seeds data, so run it only against a throwaway test database, never a shared one: the featured plans would show
 * on the Discover list while the test runs. The DB-free parts (parse, sort checks, SQL) are in
 * tests/Feature/PlanLocalizedListTest.php.
 */
class PlanIndexLocalizedTest extends TestCase
{
    private const ISO = 'qqz';
    private const OTHER_ISO = 'qqy';

    protected $key;
    protected $params;
    private $user_id;
    private $key_user;
    private $project_id;
    private $created_role_id;
    private $plan_ids = [];
    private $a;
    private $b;
    private $c;
    private $d;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::connection('dbp_users')->hasTable('plans_localization')) {
            $this->markTestSkipped('dbp_users.plans_localization does not exist on this database (PBI 103276).');
        }
        if (PlanLocalization::whereIn('iso', [self::ISO, self::OTHER_ISO])->exists()) {
            $this->markTestSkipped('The database already holds localizations in the test codes qqz/qqy.');
        }

        $key = Key::where('name', 'test-key')->first();
        $this->key      = $key->key;
        $this->params   = ['v' => 4, 'key' => $this->key];
        $this->user_id  = $key->user_id;
        $this->key_user = $key->user;

        // v4_internal_plans.index sits behind UserDataAccess, which only lets whitelisted keys through.
        config(['auth.compat_users.api_keys.user_data_access' => $this->key]);

        // compareProjects() requires the test-key user to share a project with itself. projects.id is smallint
        // unsigned and not auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create(['id' => $this->project_id, 'name' => 'localized plans test project']);
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

        $this->a = $this->seedPlan('zz localized test A', 'a.png', true, '2020-01-01 00:00:00');
        $this->b = $this->seedPlan('zz localized test B', 'b.png', true, '2021-01-01 00:00:00');
        $this->c = $this->seedPlan('zz localized test C', 'c.png', true, '2022-01-01 00:00:00');
        $this->d = $this->seedPlan('zz localized test D', 'd.png', false, '2023-01-01 00:00:00');

        $this->seedLocalization($this->a, self::ISO, null, 'Bravo', null, 'ENGESV');
        $this->seedLocalization($this->a, self::ISO, 'qq-CA', 'Alpha CA', 'a-ca.png', null);
        $this->seedLocalization($this->b, self::ISO, 'qq-CA', 'Charlie CA', 'b-ca.png', null);
        $this->seedLocalization($this->c, self::OTHER_ISO, null, 'Other', 'c-other.png', null);

        $this->subscribe($this->a, '2026-10-01');
        $this->subscribe($this->c, null);
        $this->subscribe($this->d, null);
    }

    protected function tearDown(): void
    {
        foreach ($this->plan_ids as $plan_id) {
            // The FK cascades remove the plan's localizations and user_plans rows.
            Plan::withTrashed()->where('id', $plan_id)->forceDelete();
        }
        ProjectMember::where('project_id', $this->project_id)->delete();
        Project::withTrashed()->where('id', $this->project_id)->forceDelete();
        if ($this->created_role_id) {
            Role::where('id', $this->created_role_id)->delete();
        }
        parent::tearDown();
    }

    private function seedPlan(string $name, string $thumbnail, bool $featured, string $created_at): int
    {
        $plan = Plan::forceCreate([
            'user_id'              => $this->user_id,
            'name'                 => $name,
            'thumbnail'            => $thumbnail,
            'suggested_start_date' => now()->toDateString(),
            'featured'             => $featured,
            'draft'                => false,
            'created_at'           => $created_at,
            'updated_at'           => $created_at,
        ]);
        $this->plan_ids[] = $plan->id;

        return $plan->id;
    }

    private function seedLocalization(int $plan_id, string $iso, ?string $alt_locale, string $name, ?string $thumbnail, ?string $bible_id): void
    {
        PlanLocalization::create([
            'plan_id'    => $plan_id,
            'iso'        => $iso,
            'alt_locale' => $alt_locale,
            'name'       => $name,
            'thumbnail'  => $thumbnail,
            'bible_id'   => $bible_id,
        ]);
    }

    private function subscribe(int $plan_id, ?string $start_date): void
    {
        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $plan_id,
            'start_date'           => $start_date,
            'percentage_completed' => 0,
        ]);
    }

    /**
     * Call the index. `key` and `v` travel only as headers (checkParam reads headers first), so the URL holds no
     * secret; only the route name and the query parameters are echoed. A large limit keeps every seeded plan on
     * page 1 unless the call sets one.
     */
    private function fetch(array $extra, bool $as_token_user = false)
    {
        if ($as_token_user) {
            // Bypass the APIToken middleware's api_token requirement; compareProjects() still runs.
            $this->actingAs($this->key_user, 'tokens');
        }

        echo "\nTesting: v4_internal_plans.index " . json_encode($extra);
        return $this->withHeaders($this->params)->get(route('v4_internal_plans.index', $extra + ['limit' => 1000]));
    }

    /** A 200, without letting the key reach the test output (a failed request's logged SQL can hold it). */
    private function ok(array $query, bool $as_token_user = false): array
    {
        $response = $this->fetch($query, $as_token_user);
        try {
            $response->assertOk();
        } catch (ExpectationFailedException $e) {
            throw new ExpectationFailedException(str_replace($this->key, '<KEY>', $e->getMessage()));
        }

        return $response->json();
    }

    private function discover(array $extra = []): array
    {
        return $this->ok($extra + ['featured' => 'true', 'localized' => 'true', 'iso' => self::ISO]);
    }

    private function myPlans(array $extra = []): array
    {
        return $this->ok($extra + ['featured' => 'false', 'localized' => 'true', 'iso' => self::ISO], true);
    }

    /**
     * The plain list (no localized). Discover newest first, so the seeded plans are on page 1 whatever else exists;
     * the user's own plans in the default order, because sort_by=id is ambiguous over the user_plans join.
     */
    private function plain(array $extra, bool $as_token_user = false): array
    {
        if ($extra['featured'] === 'true') {
            $extra += ['sort_by' => 'id', 'sort_dir' => 'desc'];
        }

        return $this->ok($extra, $as_token_user);
    }

    /** The seeded plans in the response, by id, in response order. */
    private function seeded(array $body): array
    {
        $plans = [];
        foreach ($body['data'] as $plan) {
            if (in_array((int) $plan['id'], $this->plan_ids, true)) {
                $plans[(int) $plan['id']] = $plan;
            }
        }

        return $plans;
    }

    /**
     * AC1, AC2, AC4: Discover lists only the plans with a row in the language, counts only them, shows the row's
     * name, and keeps the plan's own thumbnail when the row has none. Every other key is what the plain list has.
     *
     * @group V4
     * @test
     */
    public function discoverListsOnlyPlansWithARowInTheLanguage()
    {
        $body = $this->discover();
        $this->assertSame([$this->a, $this->b], array_map('intval', array_column($body['data'], 'id')));
        $this->assertSame(2, $body['total']);

        [$a, $b] = $body['data'];
        $this->assertSame('Bravo', $a['name']);
        $this->assertSame('a.png', $a['thumbnail']);
        $this->assertSame(['iso' => self::ISO, 'alt_locale' => null, 'bible_id' => 'ENGESV'], array_diff_key($a['localization'], ['id' => 0]));
        $this->assertIsInt($a['localization']['id']);
        $this->assertSame('Charlie CA', $b['name']);
        $this->assertSame('b-ca.png', $b['thumbnail']);
        $this->assertSame('qq-CA', $b['localization']['alt_locale']);

        $plain = $this->seeded($this->plain(['featured' => 'true']));
        foreach ([$a, $b] as $item) {
            $expected = $plain[(int) $item['id']];
            $this->assertSame(
                array_diff_key($expected, ['name' => 0, 'thumbnail' => 0]),
                array_diff_key($item, ['name' => 0, 'thumbnail' => 0, 'localization' => 0])
            );
        }

        $this->assertSame([$this->c], array_map('intval', array_column($this->discover(['iso' => self::OTHER_ISO])['data'], 'id')));

        $none = $this->discover(['iso' => 'qqx']);
        $this->assertSame([], $none['data']);
        $this->assertSame(0, $none['total']);
    }

    /**
     * AC3: the exact alt_locale row, case-insensitively; else the NULL row; else the lowest-id row in the language.
     *
     * @group V4
     * @test
     */
    public function eachPlanUsesTheRowForTheAppLocale()
    {
        foreach (['qq-CA', 'QQ-ca'] as $alt_locale) {
            $plans = $this->seeded($this->discover(['alt_locale' => $alt_locale]));
            $this->assertSame('Alpha CA', $plans[$this->a]['name'], $alt_locale);
            $this->assertSame('a-ca.png', $plans[$this->a]['thumbnail'], $alt_locale);
            $this->assertSame('Charlie CA', $plans[$this->b]['name'], $alt_locale);
        }

        foreach (['qq-CH', 'qq', ''] as $alt_locale) {
            $plans = $this->seeded($this->discover(['alt_locale' => $alt_locale]));
            $this->assertSame('Bravo', $plans[$this->a]['name'], $alt_locale);
            $this->assertNull($plans[$this->a]['localization']['alt_locale'], $alt_locale);
            $this->assertSame('Charlie CA', $plans[$this->b]['name'], $alt_locale);
            $this->assertSame('qq-CA', $plans[$this->b]['localization']['alt_locale'], $alt_locale);
        }

        $this->assertSame([$this->a, $this->b], array_keys($this->seeded($this->discover(['iso' => strtoupper(self::ISO)]))));
    }

    /**
     * AC5: My Plans keeps every plan the plain My Plans list has; one with no row keeps its own name and thumbnail
     * and reads `localization: null`, including one that is not featured.
     *
     * @group V4
     * @test
     */
    public function myPlansKeepsEveryPlan()
    {
        $body = $this->myPlans();
        $plain = $this->plain(['featured' => 'false'], true);

        $this->assertSame($plain['total'], $body['total']);
        $ids = array_map('intval', array_column($body['data'], 'id'));
        sort($ids);
        $plain_ids = array_map('intval', array_column($plain['data'], 'id'));
        sort($plain_ids);
        $this->assertSame($plain_ids, $ids);

        $plans = $this->seeded($body);
        $this->assertSame([$this->a, $this->c, $this->d], array_values(array_intersect(array_keys($plans), [$this->a, $this->c, $this->d])));
        $this->assertSame('Bravo', $plans[$this->a]['name']);
        foreach ([$this->c => ['zz localized test C', 'c.png'], $this->d => ['zz localized test D', 'd.png']] as $id => [$name, $thumbnail]) {
            $this->assertArrayHasKey('localization', $plans[$id]);
            $this->assertNull($plans[$id]['localization']);
            $this->assertSame($name, $plans[$id]['name']);
            $this->assertSame($thumbnail, $plans[$id]['thumbnail']);
        }
        $this->assertArrayHasKey('start_date', $plans[$this->a]);
        $this->assertArrayHasKey('percentage_completed', $plans[$this->a]);
    }

    /**
     * AC6: sort_by name is the displayed name, in either direction and any case; created_at is the plan's; ties by
     * plan id; last_interaction works on My Plans in any case and stays a 400 on Discover in any case.
     *
     * @group V4
     * @test
     */
    public function sortingUsesTheDisplayedName()
    {
        $this->assertSame([$this->b, $this->a], array_keys($this->seeded($this->discover(['sort_by' => 'name', 'sort_dir' => 'desc']))));
        $this->assertSame([$this->a, $this->b], array_keys($this->seeded($this->discover(['sort_by' => 'NAME', 'sort_dir' => 'ASC']))));
        $this->assertSame([$this->b, $this->a], array_keys($this->seeded($this->discover(['sort_by' => 'created_at', 'sort_dir' => 'desc']))));
        $this->assertSame([$this->a, $this->b], array_keys($this->seeded($this->discover(['sort_by' => 'Created_At']))));

        foreach (['last_interaction', 'Last_Interaction'] as $sort_by) {
            $plans = $this->seeded($this->myPlans(['sort_by' => $sort_by, 'sort_dir' => 'desc']));
            $this->assertCount(3, $plans, $sort_by);
            $this->assertArrayHasKey('last_interaction', $plans[$this->a], $sort_by);
        }

        foreach (['last_interaction', 'LAST_INTERACTION'] as $sort_by) {
            $this->fetch(['featured' => 'true', 'localized' => 'true', 'iso' => self::ISO, 'sort_by' => $sort_by])
                ->assertStatus(400)
                ->assertJsonPath('error.message', 'Sort by last_interaction is not supported for featured plans');
        }
    }

    public static function refusedProvider(): array
    {
        $sort_by = 'sort_by must be one of: name, created_at, updated_at, last_interaction';
        $sort_dir = 'sort_dir must be asc or desc';
        $missing = 'iso is required when localized=true';

        return [
            // AC7
            'unknown sort_by'           => [['sort_by' => 'foo'], 400, $sort_by],
            'sort_by total_days'        => [['sort_by' => 'total_days'], 400, $sort_by],
            'list sort_by'              => [['sort_by' => ['name']], 400, $sort_by],
            'unknown sort_dir'          => [['sort_dir' => 'up'], 400, $sort_dir],
            'list sort_dir'             => [['sort_dir' => ['asc']], 400, $sort_dir],
            // AC8
            'iso missing'               => [['iso' => null], 422, $missing],
            'iso empty'                 => [['iso' => ''], 422, $missing],
            // AC9
            'Discover last_interaction before missing iso' => [['iso' => null, 'sort_by' => 'last_interaction'], 400, 'Sort by last_interaction is not supported for featured plans'],
            'iso list'                  => [['iso' => [self::ISO]], 422, 'iso must be a single value'],
            'alt_locale list'           => [['alt_locale' => ['qq-CA']], 422, 'alt_locale must be a single value'],
            'iso list before missing'   => [['iso' => [self::ISO], 'alt_locale' => ['qq-CA']], 422, 'iso must be a single value'],
            'iso before sort_by'        => [['iso' => null, 'sort_by' => 'foo'], 422, $missing],
            'sort_by before sort_dir'   => [['sort_by' => 'foo', 'sort_dir' => 'up'], 400, $sort_by],
            'Discover before sort_dir'  => [['sort_by' => 'Last_Interaction', 'sort_dir' => 'up'], 400, 'Sort by last_interaction is not supported for featured plans'],
        ];
    }

    /**
     * AC7–AC9 on Discover: each refused value is a 400 or 422 with its message, never a 500; the first failing check
     * answers.
     *
     * @dataProvider refusedProvider
     * @group V4
     * @test
     */
    public function refusedValuesGiveTheMessage(array $query, int $status, string $message)
    {
        $query = array_filter(
            $query + ['featured' => 'true', 'localized' => 'true', 'iso' => self::ISO],
            function ($value) {
                return $value !== null;
            }
        );
        $this->fetch($query)->assertStatus($status)->assertJsonPath('error.message', $message);
    }

    /**
     * AC8 on My Plans: the missing iso is refused there too.
     *
     * @group V4
     * @test
     */
    public function myPlansNeedIsoToo()
    {
        $this->fetch(['featured' => 'false', 'localized' => 'true'], true)
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'iso is required when localized=true');
    }

    /**
     * T7: with localized=true only the word true makes the list Discover for a token user, also for the last_interaction
     * refusal, whichever checkBoolean() the release carries; featured=1 is the user's own plans.
     *
     * @group V4
     * @test
     */
    public function onlyTheWordTrueMeansDiscover()
    {
        foreach (['1', 'yes'] as $featured) {
            $plans = $this->seeded($this->myPlans(['featured' => $featured, 'sort_by' => 'last_interaction']));
            $this->assertSame([$this->a, $this->c, $this->d], array_values(array_intersect([$this->a, $this->c, $this->d], array_keys($plans))), $featured);
            $this->assertArrayNotHasKey($this->b, $plans, $featured);
        }

        $this->fetch(['featured' => 'TRUE', 'localized' => 'true', 'iso' => self::ISO, 'sort_by' => 'last_interaction'], true)
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Sort by last_interaction is not supported for featured plans');
    }

    /**
     * AC10: pages split the localized list, each plan once, with the localized total.
     *
     * @group V4
     * @test
     */
    public function pagesHoldEachPlanOnce()
    {
        $first = $this->discover(['limit' => 1, 'page' => 1]);
        $second = $this->discover(['limit' => 1, 'page' => 2]);

        $this->assertSame(2, $first['total']);
        $this->assertSame(2, $first['last_page']);
        $this->assertSame([$this->a, $this->b], array_map('intval', array_merge(array_column($first['data'], 'id'), array_column($second['data'], 'id'))));
    }

    /**
     * AC11: user_status and completed_days keep their values with localized=true.
     *
     * @group V4
     * @test
     */
    public function userStatusAndCompletedDaysAreUnchanged()
    {
        $flags = ['user_status' => 'true', 'completed_days' => 'true'];
        $localized = $this->seeded($this->myPlans($flags));
        $plain = $this->seeded($this->plain(['featured' => 'false'] + $flags, true));

        foreach ([$this->a, $this->c, $this->d] as $id) {
            $this->assertSame($plain[$id]['user_status'], $localized[$id]['user_status']);
            $this->assertSame($plain[$id]['completed_days'], $localized[$id]['completed_days']);
        }
        $this->assertSame(UserPlan::STATUS_IN_PROGRESS, $localized[$this->a]['user_status']);
        $this->assertSame(UserPlan::STATUS_SAVED, $localized[$this->c]['user_status']);
    }

    /**
     * AC12: without localized=true (absent, 1, yes, false, empty; with or without alt_locale) the response is the
     * plain one, with no `localization` key.
     *
     * @group V4
     * @test
     */
    public function anyOtherValueLeavesTheResponseAsItWas()
    {
        foreach ([['featured' => 'true', 'iso' => self::ISO], ['featured' => 'false']] as $base) {
            $as_token_user = $base['featured'] === 'false';
            $plain = $this->fetch($base, $as_token_user)->getContent();
            $this->assertStringNotContainsString('"localization"', $plain);

            foreach (['1', 'yes', 'false', ''] as $value) {
                foreach ([[], ['alt_locale' => 'qq-CA']] as $alt) {
                    $query = $base + ['localized' => $value] + $alt;
                    $this->assertSame($plain, $this->fetch($query, $as_token_user)->getContent(), json_encode($query));
                }
            }
            $this->assertSame($plain, $this->fetch($base + ['alt_locale' => 'qq-CA'], $as_token_user)->getContent());
        }
    }
}
