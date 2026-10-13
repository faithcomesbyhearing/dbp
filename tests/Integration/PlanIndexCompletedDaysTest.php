<?php

namespace Tests\Integration;

use App\Exceptions\MySQLErrorCode;
use App\Models\Plan\Plan;
use App\Models\Plan\PlanDay;
use App\Models\Plan\UserPlan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\Playlist\PlaylistItemsComplete;
use App\Models\User\Key;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

/**
 * Coverage for the optional `completed_days` key on GET /api/plans (v4_internal_plans.index), PBI 102548.
 *
 * The Bible.is My Plans screen estimated completed days from percentage_completed, which counts playlist
 * items, so it could disagree with Plan Details, which shows each day's `completed` flag. With
 * `completed_days=true` on the user's own plans (featured=false with a token user), each plan gains
 * `completed_days`: the number of its days with a plan_days_completed row for the user. Without the
 * parameter, with any value other than the word `true` (any case), on featured listings, or without a
 * token user, the key is omitted and the payload is identical to before the change.
 *
 * Seeds one featured plan with four days of two playlist items each, adopted by the test user:
 *   day 1, day 2  marked complete with PlanDay::complete() (day row plus every item, as the API does)
 *   day 3         both items completed through item rows only, no day row: deliberately NOT counted
 *   day 4         untouched
 * One test adds a second plan and a second user to prove the count is per plan and per user.
 *
 * The API key is sent only as a request header, never in the URL, and nothing that contains it is printed:
 * test output ends up in terminals, CI logs and agent transcripts.
 *
 * Seeds data, so run it only against a throwaway test database, never a shared one.
 *
 * Extends TestCase rather than ApiV4Test so the stale @test methods in ApiV4Test, whose routes no longer
 * exist, are not inherited and re-run with this class.
 */
class PlanIndexCompletedDaysTest extends TestCase
{
    protected $key;
    protected $params;
    private $plan_id;
    private $user_id;
    private $key_user;
    private $project_id;
    private $created_role_id;
    private $plan_days = [];
    private $playlist_item_ids = [];
    private $plan_ids = [];
    private $extra_user_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $key = Key::where('name', 'test-key')->first();
        $this->key      = $key->key;
        $this->params   = ['v' => 4, 'key' => $this->key];
        $this->user_id  = $key->user_id;
        $this->key_user = $key->user;

        // v4_internal_plans.index sits behind UserDataAccess, which only lets whitelisted keys through.
        config(['auth.compat_users.api_keys.user_data_access' => $this->key]);

        // compareProjects() requires the test-key user to share at least one project with itself.
        // projects.id is smallint unsigned and not auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create([
            'id'   => $this->project_id,
            'name' => 'completed_days test project',
        ]);
        // Role declares no $fillable, so firstOrCreate() would throw MassAssignmentException on a
        // database without this role; forceCreate() bypasses mass-assignment protection.
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

        // Featured, so the featured-listing tests can assert the seeded plan is present and still lacks the key
        // (an empty featured list would make them pass without checking anything). This is why the test must
        // only run against a throwaway database.
        [$this->plan_id, $this->plan_days, $this->playlist_item_ids] = $this->createPlan('completed_days test plan', 4);
    }

    protected function tearDown(): void
    {
        foreach ($this->plan_ids as $plan_id) {
            // Playlists first: FK cascade removes playlist_items and playlist_items_completed.
            // forceDelete bypasses SoftDeletes on Playlist and Plan so the cascades fire.
            Playlist::where('plan_id', $plan_id)->forceDelete();
            // Plan cascade removes plan_days, plan_days_completed and user_plans.
            Plan::where('id', $plan_id)->forceDelete();
        }
        // After the plans, so no completion row still points at these users.
        foreach ($this->extra_user_ids as $user_id) {
            User::withTrashed()->where('id', $user_id)->forceDelete();
        }
        ProjectMember::where('project_id', $this->project_id)->delete();
        Project::withTrashed()->where('id', $this->project_id)->forceDelete();
        if ($this->created_role_id) {
            Role::where('id', $this->created_role_id)->delete();
        }
        parent::tearDown();
    }

    /**
     * A featured plan owned and adopted by the test user, with $day_count days of two playlist items each.
     *
     * @return array [plan id, [order => PlanDay], [order => [playlist item ids]]]
     */
    private function createPlan(string $name, int $day_count): array
    {
        $plan = Plan::create([
            'user_id'              => $this->user_id,
            'name'                 => $name,
            'suggested_start_date' => now()->toDateString(),
            'draft'                => false,
        ]);
        $plan->featured = true; // not fillable
        $plan->save();
        $this->plan_ids[] = $plan->id;

        $days = [];
        $item_ids = [];
        foreach (range(1, $day_count) as $order) {
            $playlist = Playlist::create([
                'user_id' => $this->user_id,
                'name'    => $name . ' day ' . $order,
                'plan_id' => $plan->id,
                'draft'   => false,
            ]);
            $days[$order] = PlanDay::create([
                'plan_id'      => $plan->id,
                'playlist_id'  => $playlist->id,
                'order_column' => $order,
            ]);
            foreach ([1, 2] as $chapter) {
                $item = PlaylistItems::create([
                    'playlist_id'   => $playlist->id,
                    'fileset_id'    => 'ENGESVN2DA',
                    'book_id'       => 'MAT',
                    'chapter_start' => $chapter,
                    'chapter_end'   => $chapter,
                    'verse_start'   => 1,
                    'duration'      => 0,
                ]);
                $item_ids[$order][] = $item->id;
            }
        }

        // The user's own plans list (featured=false) inner-joins user_plans.
        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $plan->id,
            'percentage_completed' => 0,
        ]);

        return [$plan->id, $days, $item_ids];
    }

    private function seedProgress(): void
    {
        $this->plan_days[1]->complete($this->user_id);
        $this->plan_days[2]->complete($this->user_id);
        foreach ($this->playlist_item_ids[3] as $item_id) {
            PlaylistItemsComplete::create([
                'user_id'          => $this->user_id,
                'playlist_item_id' => $item_id,
            ]);
        }
    }

    /**
     * Call the index and return the whole `data` array.
     *
     * `key` and `v` travel only as headers (checkParam reads headers first), so the URL holds no secret and is
     * safe to print. Only the route name and the extra query parameters are echoed.
     */
    private function fetchPlans(array $extra = [], bool $as_token_user = true): array
    {
        if ($as_token_user) {
            // Bypass the APIToken middleware's api_token requirement; compareProjects() still runs.
            $this->actingAs($this->key_user, 'tokens');
        }

        // Large limit and newest-first ordering keep the seeded plans on the first page.
        $query = array_merge(['featured' => 'false', 'limit' => 1000, 'sort_by' => 'id', 'sort_dir' => 'desc'], $extra);
        echo "\nTesting: v4_internal_plans.index " . json_encode($extra);
        $response = $this->withHeaders($this->params)->get(route('v4_internal_plans.index', $query));
        try {
            $response->assertSuccessful();
        } catch (ExpectationFailedException $e) {
            // On a failed request Laravel appends the logged exception, whose SQL bindings include the key.
            throw new ExpectationFailedException(str_replace($this->key, '<KEY>', $e->getMessage()));
        }

        return $response->json('data');
    }

    private function planFrom(array $data, int $plan_id): ?array
    {
        foreach ($data as $plan) {
            if ((int) $plan['id'] === $plan_id) {
                return $plan;
            }
        }
        return null;
    }

    private function seededPlan(array $data): ?array
    {
        return $this->planFrom($data, (int) $this->plan_id);
    }

    private function assertNoPlanHasCompletedDays(array $data): void
    {
        foreach ($data as $plan) {
            $this->assertArrayNotHasKey('completed_days', $plan);
        }
    }

    /**
     * AC1, AC8: only the two days with a day row count; day 3 (items only) does not.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function countsDaysMarkedComplete()
    {
        $this->seedProgress();
        $plan = $this->seededPlan($this->fetchPlans(['completed_days' => 'true']));

        $this->assertNotNull($plan);
        $this->assertSame(2, $plan['completed_days']);
        $this->assertSame(4, $plan['total_days']);
    }

    /**
     * The count is per plan and per user, and a duplicate day row is counted once.
     *
     * A second plan with one completed day, and a second user who has completed every day of the first plan,
     * must not change the first plan's count. A query that summed across plans or users would read 3 or 6.
     * plan_days_completed's original migration declares only a non-unique (user_id, plan_day_id) index, while
     * CleanUpPlanDaysCompletedTable adds a unique key on dev/prod. A second row for the same day is inserted
     * directly; the test asserts which case the database is in (two rows, or the insert refused) and that the
     * count stays 2 either way.
     *
     * @group V4
     * @test
     */
    public function countIsPerPlanAndPerUserAndIgnoresDuplicateRows()
    {
        $this->seedProgress();

        [$other_plan_id, $other_plan_days] = $this->createPlan('completed_days second plan', 2);
        $other_plan_days[1]->complete($this->user_id);

        $other_user = factory(User::class)->create();
        $this->extra_user_ids[] = $other_user->id;
        foreach ($this->plan_days as $day) {
            $day->complete($other_user->id);
        }

        $duplicate_refused = false;
        try {
            DB::connection('dbp_users')->table('plan_days_completed')->insert([
                'user_id'     => $this->user_id,
                'plan_day_id' => $this->plan_days[1]->id,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() != MySQLErrorCode::DUPLICATE_ENTRY) {
                throw $e;
            }
            $duplicate_refused = true;
        }
        // Say which case this database exercised: either a duplicate row really exists and must be counted once,
        // or the unique key (added by CleanUpPlanDaysCompletedTable on dev/prod) makes duplicates impossible.
        $rows_for_day = DB::connection('dbp_users')->table('plan_days_completed')
            ->where('user_id', $this->user_id)
            ->where('plan_day_id', $this->plan_days[1]->id)
            ->count();
        $this->assertSame($duplicate_refused ? 1 : 2, $rows_for_day);

        $data = $this->fetchPlans(['completed_days' => 'true']);

        $this->assertSame(2, $this->seededPlan($data)['completed_days']);
        $this->assertSame(1, $this->planFrom($data, (int) $other_plan_id)['completed_days']);
    }

    /**
     * The word true is accepted in any case.
     *
     * @group V4
     * @test
     */
    public function acceptsTrueInAnyCase()
    {
        $this->seedProgress();
        foreach (['TRUE', 'True'] as $value) {
            $plan = $this->seededPlan($this->fetchPlans(['completed_days' => $value]));

            $this->assertSame(2, $plan['completed_days'], 'completed_days=' . $value);
        }
    }

    /**
     * AC6: un-completing a day lowers the count.
     *
     * @group V4
     * @test
     */
    public function unCompletingADayLowersTheCount()
    {
        $this->seedProgress();
        $this->plan_days[1]->unComplete($this->user_id);
        $plan = $this->seededPlan($this->fetchPlans(['completed_days' => 'true']));

        $this->assertSame(1, $plan['completed_days']);
    }

    /**
     * AC8: un-completing one item of a completed day leaves the day completed, so it still counts
     * (Plan Details keeps showing the day as completed).
     *
     * @group V4
     * @test
     */
    public function unCompletingOneItemOfACompletedDayKeepsTheCount()
    {
        $this->seedProgress();
        PlaylistItemsComplete::where('user_id', $this->user_id)
            ->where('playlist_item_id', $this->playlist_item_ids[1][0])
            ->delete();
        $plan = $this->seededPlan($this->fetchPlans(['completed_days' => 'true']));

        $this->assertSame(2, $plan['completed_days']);
    }

    /**
     * A followed plan with nothing completed reads 0, never null or absent.
     *
     * @group V4
     * @test
     */
    public function nothingCompletedReadsZero()
    {
        $plan = $this->seededPlan($this->fetchPlans(['completed_days' => 'true']));

        $this->assertNotNull($plan);
        $this->assertArrayHasKey('completed_days', $plan);
        $this->assertSame(0, $plan['completed_days']);
    }

    /**
     * AC2: without the parameter the key is absent.
     *
     * @group V4
     * @test
     */
    public function absentParamOmitsKey()
    {
        $this->seedProgress();
        $data = $this->fetchPlans();

        $this->assertNotNull($this->seededPlan($data));
        $this->assertNoPlanHasCompletedDays($data);
    }

    /**
     * AC3: only the word true turns it on, parsed in the controller independently of checkBoolean() (before #1062,
     * checkBoolean() accepted any value but "false"). `false`, `1`, `yes`, `no`, `off`, `on`, empty and an array are
     * all off, and the array does not cause a 500 (fetchPlans asserts a 2xx).
     *
     * @group V4
     * @test
     */
    public function nonTrueValuesOmitKey()
    {
        $this->seedProgress();
        foreach (['false', '1', 'yes', 'no', 'off', 'on', '', ['true']] as $value) {
            $data = $this->fetchPlans(['completed_days' => $value]);
            $this->assertNotNull($this->seededPlan($data), 'completed_days=' . json_encode($value));
            $this->assertNoPlanHasCompletedDays($data);
        }
    }

    /**
     * AC4: featured listings never carry the key.
     *
     * @group V4
     * @test
     */
    public function featuredListingOmitsKey()
    {
        $this->seedProgress();
        $data = $this->fetchPlans(['featured' => 'true', 'completed_days' => 'true']);

        $this->assertNotNull($this->seededPlan($data));
        $this->assertNoPlanHasCompletedDays($data);
    }

    /**
     * AC4: without a token user index() forces featured=true, so the key never appears. Its own test because
     * actingAs() in an earlier call would keep the request authenticated.
     *
     * @group V4
     * @test
     */
    public function anonymousRequestOmitsKey()
    {
        $this->seedProgress();
        $data = $this->fetchPlans(['completed_days' => 'true'], false);

        $this->assertNotNull($this->seededPlan($data));
        $this->assertNoPlanHasCompletedDays($data);
    }
}
