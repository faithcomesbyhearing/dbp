<?php

namespace Tests\Integration;

use App\Models\Plan\Plan;
use App\Models\Plan\PlanDay;
use App\Models\Plan\PlanDayComplete;
use App\Models\Plan\UserPlan;
use App\Models\User\Key;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\Role;

/**
 * Regression coverage for bug 90007 / PR #1062.
 *
 * Pre-PR #1062, an absent `complete` query param defaulted to "mark complete" because the call
 * site read `checkParam('complete') ?? true`. PR #1062 swapped this to `checkBoolean('complete')`,
 * which returns false for an absent param — silently flipping bibleis "mark day complete" calls
 * (bibleis never sends the param) into `unComplete()` calls. The fix in PlansController::completeDay
 * restores the absent-param default while preserving PR #1062's strict-`'true'` parsing for any
 * value that *is* sent.
 */
class PlanDayCompleteTest extends ApiV4Test
{
    private $plan_id;
    private $plan_day_id;
    private $user_id;
    private $project_id;

    protected function setUp(): void
    {
        parent::setUp();

        $key = Key::where('key', $this->key)->first();
        $this->user_id = $key->user_id;

        // compareProjects() requires the test-key user to share at least one project with itself.
        // Seed a throwaway Project + ProjectMember so the membership intersect is non-empty.
        // projects.id is smallint unsigned and not auto-increment — pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create([
            'id'   => $this->project_id,
            'name' => 'Bug 90007 regression test project',
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

        $plan = Plan::create([
            'user_id'              => $this->user_id,
            'name'                 => 'Bug 90007 regression test plan',
            'suggested_start_date' => now()->toDateString(),
        ]);
        $this->plan_id = $plan->id;

        $plan_day = PlanDay::create([
            'plan_id'      => $plan->id,
            'order_column' => 1,
        ]);
        $this->plan_day_id = $plan_day->id;

        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $plan->id,
            'percentage_completed' => 0,
        ]);

        // Bypass the APIToken middleware's api_token requirement.
        // compareProjects() in the controller still runs and uses the seeded ProjectMember above.
        $this->actingAs($key->user, 'tokens');
    }

    protected function tearDown(): void
    {
        // forceDelete bypasses Plan's SoftDeletes so the FK cascade fires
        // and cleans up plan_days, user_plans, and plan_days_completed.
        Plan::where('id', $this->plan_id)->forceDelete();
        ProjectMember::where('project_id', $this->project_id)->delete();
        // Project uses SoftDeletes — forceDelete avoids leaving an orphan that would block re-runs.
        Project::withTrashed()->where('id', $this->project_id)->forceDelete();
        parent::tearDown();
    }

    private function completeRoute(array $extra = []): string
    {
        return route(
            'v4_internal_plans_days.complete',
            array_merge($this->params, ['day_id' => $this->plan_day_id], $extra)
        );
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_plans_days.complete
     * @see      \App\Http\Controllers\Plan\PlansController::completeDay
     * @group    V4
     * @group    travis
     * @test
     */
    public function absentCompleteParamMarksDayComplete()
    {
        $path = $this->completeRoute();
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->post($path);
        $response->assertSuccessful();
        $response->assertJsonFragment(['message' => 'Plan Day completed']);
        $this->assertDatabaseHas(
            'plan_days_completed',
            ['user_id' => $this->user_id, 'plan_day_id' => $this->plan_day_id],
            'dbp_users'
        );
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_plans_days.complete
     * @see      \App\Http\Controllers\Plan\PlansController::completeDay
     * @group    V4
     * @group    travis
     * @test
     */
    public function completeTrueMarksDayComplete()
    {
        $path = $this->completeRoute(['complete' => 'true']);
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->post($path);
        $response->assertSuccessful();
        $response->assertJsonFragment(['message' => 'Plan Day completed']);
        $this->assertDatabaseHas(
            'plan_days_completed',
            ['user_id' => $this->user_id, 'plan_day_id' => $this->plan_day_id],
            'dbp_users'
        );
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_plans_days.complete
     * @see      \App\Http\Controllers\Plan\PlansController::completeDay
     * @group    V4
     * @group    travis
     * @test
     */
    public function completeFalseMarksDayNotComplete()
    {
        PlanDayComplete::create([
            'user_id'     => $this->user_id,
            'plan_day_id' => $this->plan_day_id,
        ]);

        $path = $this->completeRoute(['complete' => 'false']);
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->post($path);
        $response->assertSuccessful();
        $response->assertJsonFragment(['message' => 'Plan Day not completed']);
        $this->assertDatabaseMissing(
            'plan_days_completed',
            ['user_id' => $this->user_id, 'plan_day_id' => $this->plan_day_id],
            'dbp_users'
        );
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_plans_days.complete
     * @see      \App\Http\Controllers\Plan\PlansController::completeDay
     * @group    V4
     * @group    travis
     * @test
     */
    public function randomCompleteValueMarksDayNotComplete()
    {
        // Locks in PR #1062's strict-'true' parsing — anything other than 'true' resolves to false.
        $path = $this->completeRoute(['complete' => 'yes']);
        echo "\nTesting: $path";
        $response = $this->withHeaders($this->params)->post($path);
        $response->assertSuccessful();
        $response->assertJsonFragment(['message' => 'Plan Day not completed']);
        $this->assertDatabaseMissing(
            'plan_days_completed',
            ['user_id' => $this->user_id, 'plan_day_id' => $this->plan_day_id],
            'dbp_users'
        );
    }
}
