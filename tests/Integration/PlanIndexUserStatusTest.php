<?php

namespace Tests\Integration;

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
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

/**
 * Coverage for the optional `user_status` key on GET /api/plans (v4_internal_plans.index).
 *
 * The Bible.is Discover page lists featured plans and badges each one with the signed-in
 * user's relationship to it. Before this change the app had to make a second call
 * (featured=false) and join client-side, and even then only saw the stored
 * percentage_completed, an integer that rounds 1-of-365 to 0 and 364-of-365 to 100.
 *
 * With `user_status=true` and an api_token, each plan gains `user_status`:
 *   null          no user_plans row (the user never added the plan, or stopped a plan someone else created)
 *   saved         a row with no start_date (not started), whatever is completed
 *   in_progress   a row with a start_date, not finished (including a plan with no items yet)
 *   completed     a row with a start_date, at least one playlist item, every one completed
 * Without the parameter, with any value other than the word `true` (any case), or without a
 * token user, the key is omitted and the payload is identical to before the change.
 *
 * Seeds one featured plan with two days of one playlist item each, and drives the status
 * through the four states by writing playlist_items_completed rows directly (the same rows
 * PlanDay::complete() and PlaylistItems::complete() write). The plan's user_plans rows are
 * created by the test itself (Plan::create, unlike POST /api/plans, adds none for the owner), so
 * the test user, although the plan's owner, starts in the no-row state that reads null. What
 * decides the status is the user_plans row, not who created the plan.
 *
 * Seeds data, so run it only against a throwaway test database, never a shared one: the
 * featured plan would show on the Discover list while the test runs.
 *
 * Extends TestCase rather than ApiV4Test so that the stale @test methods in ApiV4Test, whose
 * routes no longer exist, are not inherited and re-run with every run of this class.
 */
class PlanIndexUserStatusTest extends TestCase
{
    protected $key;
    protected $params;
    private $plan_id;
    private $user_id;
    private $key_user;
    private $project_id;
    private $created_role_id;
    private $playlist_item_ids = [];

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
        // Seed a throwaway Project + ProjectMember so the membership intersect is non-empty.
        // projects.id is smallint unsigned and not auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create([
            'id'   => $this->project_id,
            'name' => 'user_status test project',
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

        // A featured, published plan so it appears on the Discover (featured=true) list.
        $plan = Plan::create([
            'user_id'              => $this->user_id,
            'name'                 => 'user_status test plan',
            'suggested_start_date' => now()->toDateString(),
            'draft'                => false,
        ]);
        $plan->featured = true; // not fillable
        $plan->save();
        $this->plan_id = $plan->id;

        // Two days, one playlist item each, so the plan has exactly two completable items.
        foreach ([1, 2] as $order) {
            $playlist = Playlist::create([
                'user_id' => $this->user_id,
                'name'    => 'user_status test day ' . $order,
                'plan_id' => $plan->id,
                'draft'   => false,
            ]);
            PlanDay::create([
                'plan_id'      => $plan->id,
                'playlist_id'  => $playlist->id,
                'order_column' => $order,
            ]);
            $item = PlaylistItems::create([
                'playlist_id'   => $playlist->id,
                'fileset_id'    => 'ENGESVN2DA',
                'book_id'       => 'MAT',
                'chapter_start' => $order,
                'chapter_end'   => $order,
                'verse_start'   => 1,
                'duration'      => 0,
            ]);
            $this->playlist_item_ids[] = $item->id;
        }
    }

    protected function tearDown(): void
    {
        // Playlists first: FK cascade removes playlist_items and playlist_items_completed.
        // forceDelete bypasses SoftDeletes on Playlist and Plan so the cascades fire.
        Playlist::where('plan_id', $this->plan_id)->forceDelete();
        // Plan cascade removes plan_days and user_plans.
        Plan::where('id', $this->plan_id)->forceDelete();
        ProjectMember::where('project_id', $this->project_id)->delete();
        Project::withTrashed()->where('id', $this->project_id)->forceDelete();
        if ($this->created_role_id) {
            Role::where('id', $this->created_role_id)->delete();
        }
        parent::tearDown();
    }

    /**
     * Give the test user a user_plans row: started (as POST /plans/{id}/start leaves it) by
     * default, or with no start_date (as POST /plans, translate and a creator's stop leave it).
     */
    private function subscribe(?string $start_date = '2026-10-01'): void
    {
        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $this->plan_id,
            'start_date'           => $start_date,
            'percentage_completed' => 0,
        ]);
    }

    private function completeItem(int $index): void
    {
        PlaylistItemsComplete::create([
            'user_id'          => $this->user_id,
            'playlist_item_id' => $this->playlist_item_ids[$index],
        ]);
    }

    private function indexRoute(array $extra = []): string
    {
        // Large limit and newest-first ordering keep the seeded plan on the first page
        // regardless of how many featured plans the test database holds.
        return route(
            'v4_internal_plans.index',
            array_merge(['featured' => 'true', 'limit' => 1000, 'sort_by' => 'id', 'sort_dir' => 'desc'], $extra)
        );
    }

    /**
     * Assert a 2xx without letting the key reach the test output: on a failed request Laravel appends the logged
     * exception, whose SQL bindings include the key.
     */
    private function assertSuccessfulWithoutKey($response): void
    {
        try {
            $response->assertSuccessful();
        } catch (ExpectationFailedException $e) {
            throw new ExpectationFailedException(str_replace($this->key, '<KEY>', $e->getMessage()));
        }
    }

    /**
     * Call the index and return the seeded plan's item from `data`, or null if it is absent.
     *
     * `key` and `v` travel only as headers (checkParam reads headers first), so the URL holds no secret. Only the
     * route name and the extra query parameters are echoed.
     */
    private function fetchSeededPlan(array $extra = [], bool $as_token_user = true): ?array
    {
        if ($as_token_user) {
            // Bypass the APIToken middleware's api_token requirement; compareProjects() still runs.
            $this->actingAs($this->key_user, 'tokens');
        }

        echo "\nTesting: v4_internal_plans.index " . json_encode($extra);
        $response = $this->withHeaders($this->params)->get($this->indexRoute($extra));
        $this->assertSuccessfulWithoutKey($response);

        foreach ($response->json('data') as $plan) {
            if ((int) $plan['id'] === (int) $this->plan_id) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function keyIsOmittedWhenParameterIsAbsent()
    {
        $this->subscribe();
        $plan = $this->fetchSeededPlan();
        $this->assertNotNull($plan, 'seeded featured plan should be listed');
        $this->assertArrayNotHasKey('user_status', $plan);
        $this->assertArrayHasKey('total_days', $plan);
    }

    /**
     * Locks in the controller's strict-'true' parsing (independent of checkBoolean()): `1`, `yes`, `no`, `off` and
     * an array are all off, and the array does not cause a 500.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function keyIsOmittedWhenParameterIsNotExactlyTrue()
    {
        $this->subscribe();
        foreach (['1', 'yes', 'no', 'off', ['true']] as $value) {
            $plan = $this->fetchSeededPlan(['user_status' => $value]);
            $this->assertNotNull($plan);
            $this->assertArrayNotHasKey('user_status', $plan, 'user_status=' . json_encode($value));
        }
    }

    /**
     * The word true is case-insensitive: `TRUE` and `True` turn the key on.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function keyIsPresentForTrueInAnyCase()
    {
        foreach (['TRUE', 'True'] as $value) {
            $plan = $this->fetchSeededPlan(['user_status' => $value]);
            $this->assertNotNull($plan);
            $this->assertArrayHasKey('user_status', $plan, 'user_status=' . $value);
        }
    }

    /**
     * No token user: the flag alone must not change the payload.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function keyIsOmittedForAnonymousCaller()
    {
        $this->subscribe();
        $plan = $this->fetchSeededPlan(['user_status' => 'true'], false);
        $this->assertNotNull($plan);
        $this->assertArrayNotHasKey('user_status', $plan);
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function nullWhenUserHasNotAdoptedThePlan()
    {
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertArrayHasKey('user_status', $plan);
        $this->assertNull($plan['user_status']);
    }

    /**
     * A started plan is in_progress even with nothing completed yet.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function inProgressWhenStartedWithNothingCompleted()
    {
        $this->subscribe();
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertSame(UserPlan::STATUS_IN_PROGRESS, $plan['user_status']);
    }

    /**
     * One of two items: the stored percentage would be 50 here, but the same code path
     * yields in_progress for 1 of 1095 where the stored percentage rounds to 0.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function inProgressWhenSomeItemsCompleted()
    {
        $this->subscribe();
        $this->completeItem(0);
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertSame(UserPlan::STATUS_IN_PROGRESS, $plan['user_status']);
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function completedWhenEveryItemCompleted()
    {
        $this->subscribe();
        $this->completeItem(0);
        $this->completeItem(1);
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertSame(UserPlan::STATUS_COMPLETED, $plan['user_status']);
    }

    /**
     * The user's own list (featured=false) carries the key too, alongside the existing
     * start_date and percentage_completed columns from the user_plans join.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function keyIsPresentOnTheUsersOwnList()
    {
        $this->subscribe();
        $this->completeItem(0);
        $plan = $this->fetchSeededPlan(['featured' => 'false', 'user_status' => 'true']);
        $this->assertNotNull($plan, 'subscribed plan should be on the featured=false list');
        $this->assertSame(UserPlan::STATUS_IN_PROGRESS, $plan['user_status']);
        $this->assertArrayHasKey('percentage_completed', $plan);
        $this->assertArrayHasKey('start_date', $plan);
    }

    /**
     * A row with no start_date is saved: the plan is in the user's plans but was never started. This
     * is what distinguishes saved from the null above.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function savedWhenNotStartedWithNothingCompleted()
    {
        $this->subscribe(null);
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertSame(UserPlan::STATUS_SAVED, $plan['user_status']);

        $own = $this->fetchSeededPlan(['featured' => 'false', 'user_status' => 'true']);
        $this->assertNotNull($own, 'a saved plan is on the featured=false list');
        $this->assertSame(UserPlan::STATUS_SAVED, $own['user_status']);
        $this->assertNull($own['start_date']);
    }

    /**
     * The start date alone decides started: completed items in a plan with no start_date (old data, or
     * translate copying progress; clients cannot do it) still read saved, at 1 of 2 and at 2 of 2.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function savedWhenNotStartedEvenWithItemsCompleted()
    {
        $this->subscribe(null);
        $this->completeItem(0);
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertSame(UserPlan::STATUS_SAVED, $plan['user_status'], '1 of 2 items, no start date');

        $this->completeItem(1);
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertSame(UserPlan::STATUS_SAVED, $plan['user_status'], '2 of 2 items, no start date');
    }

    /**
     * The test user created the seeded plan, so stopping it (DELETE /plans/{id}/stop) clears progress and the start
     * date but keeps the row: the plan reads saved, not null. Also catches stop() deleting the creator's row.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::stop
     * @group    V4
     * @test
     */
    public function creatorStoppingTheirOwnPlanReadsSaved()
    {
        $this->subscribe();
        $this->completeItem(0);
        $this->assertSame(
            UserPlan::STATUS_IN_PROGRESS,
            $this->fetchSeededPlan(['user_status' => 'true'])['user_status']
        );

        $this->actingAs($this->key_user, 'tokens');
        echo "\nTesting: v4_internal_plans.stop";
        $this->assertSuccessfulWithoutKey(
            $this->withHeaders($this->params)->delete(route('v4_internal_plans.stop', ['plan_id' => $this->plan_id]))
        );

        $plan = $this->fetchSeededPlan(['featured' => 'false', 'user_status' => 'true']);
        $this->assertNotNull($plan, "the creator's stopped plan stays on their featured=false list");
        $this->assertSame(UserPlan::STATUS_SAVED, $plan['user_status']);
        $this->assertNull($plan['start_date']);
    }

    /**
     * Starting a saved plan (POST /plans/{id}/start) sets the start date, so it reads in_progress.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::start
     * @group    V4
     * @test
     */
    public function savedPlanReadsInProgressAfterStart()
    {
        $this->subscribe(null);
        $this->assertSame(
            UserPlan::STATUS_SAVED,
            $this->fetchSeededPlan(['user_status' => 'true'])['user_status']
        );

        $this->actingAs($this->key_user, 'tokens');
        echo "\nTesting: v4_internal_plans.start";
        $this->assertSuccessfulWithoutKey(
            $this->withHeaders($this->params)
                ->post(route('v4_internal_plans.start', ['plan_id' => $this->plan_id]), ['start_date' => '2026-10-16'])
        );

        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertSame(UserPlan::STATUS_IN_PROGRESS, $plan['user_status']);
    }
}
