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

/**
 * Coverage for the optional `user_status` key on GET /api/plans (v4_internal_plans.index).
 *
 * The Bible.is Discover page lists featured plans and badges each one with the signed-in
 * user's relationship to it. Before this change the app had to make a second call
 * (featured=false) and join client-side, and even then only saw the stored
 * percentage_completed, an integer that rounds 1-of-365 to 0 and 364-of-365 to 100.
 *
 * With `user_status=true` and an api_token, each plan gains `user_status`:
 *   null          no user_plans row (the user never started the plan)
 *   not_started   subscribed, nothing completed
 *   in_progress   some but not all playlist items completed
 *   completed     every playlist item completed
 * Without the parameter, with any value other than the literal `true`, or without a token
 * user, the key is omitted and the payload is identical to before the change.
 *
 * Seeds one featured plan with two days of one playlist item each, owned by the test-key
 * user, and drives the status through the four states by writing playlist_items_completed
 * rows directly (the same rows PlanDay::complete() and PlaylistItems::complete() write).
 */
class PlanIndexUserStatusTest extends ApiV4Test
{
    private $plan_id;
    private $user_id;
    private $key_user;
    private $project_id;
    private $playlist_item_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $key = Key::where('key', $this->key)->first();
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
        $role = Role::firstOrCreate(
            ['slug' => 'developer'],
            ['name' => 'developer', 'description' => 'Developer']
        );
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
        parent::tearDown();
    }

    private function subscribe(): void
    {
        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $this->plan_id,
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
            array_merge(
                $this->params,
                ['featured' => 'true', 'limit' => 1000, 'sort_by' => 'id', 'sort_dir' => 'desc'],
                $extra
            )
        );
    }

    /**
     * Call the index and return the seeded plan's item from `data`, or null if it is absent.
     */
    private function fetchSeededPlan(array $extra = [], bool $as_token_user = true): ?array
    {
        if ($as_token_user) {
            // Bypass the APIToken middleware's api_token requirement; compareProjects() still runs.
            $this->actingAs($this->key_user, 'tokens');
        }

        $path = $this->indexRoute($extra);
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->get($path);
        $response->assertSuccessful();

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
     * Locks in checkBoolean()'s strict-'true' parsing: `user_status=1` is off.
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
        $plan = $this->fetchSeededPlan(['user_status' => '1']);
        $this->assertNotNull($plan);
        $this->assertArrayNotHasKey('user_status', $plan);
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
    public function nullWhenUserHasNotStartedThePlan()
    {
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertArrayHasKey('user_status', $plan);
        $this->assertNull($plan['user_status']);
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_plans.index
     * @see      \App\Http\Controllers\Plan\PlansController::index
     * @group    V4
     * @test
     */
    public function notStartedWhenSubscribedWithNothingCompleted()
    {
        $this->subscribe();
        $plan = $this->fetchSeededPlan(['user_status' => 'true']);
        $this->assertNotNull($plan);
        $this->assertSame(UserPlan::STATUS_NOT_STARTED, $plan['user_status']);
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
}
