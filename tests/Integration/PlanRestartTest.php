<?php

namespace Tests\Integration;

use App\Models\Bible\Bible;
use App\Models\Plan\Plan;
use App\Models\Plan\PlanDay;
use App\Models\Plan\UserPlan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\User\Key;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Coverage for POST /plans/{id}/restart (v4_internal_plans.restart), PBI 102554.
 *
 * Restart replaces the app's two calls (Stop or Reset, then Start). For a plan the user follows it deletes the
 * user's completed items and days, sets percentage_completed to 0, sets the new start_date and, when sent,
 * user_bible, in one transaction on the users database, and never deletes the user_plans row. A plan the user
 * does not follow is a 404; a bad input is a 422; in every error case nothing changes.
 *
 * Seeds two plans of three days with two playlist items each: one created by the test user, and one featured
 * plan created by another user (the case where Stop would delete the row). Needs the user_plans.user_bible
 * column (PBI 102552; skipped without it) and a second user in the users database.
 *
 * Seeds data, including a featured plan that would appear on Discover, so run it only against a throwaway test
 * database, never a shared one. Extends TestCase rather than ApiV4Test so ApiV4Test's stale @test methods are not
 * inherited.
 *
 * @group plans
 */
class PlanRestartTest extends TestCase
{
    protected $key;
    protected $params;
    private $user_id;
    private $key_user;
    private $project_id;
    private $created_role_id;
    private $bible_id;
    private $other_bible_id;
    private $own_plan_id;
    private $featured_plan_id;
    private $plan_days = [];
    private $playlist_item_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::connection('dbp_users')->hasColumn('user_plans', 'user_bible')) {
            $this->markTestSkipped('user_plans.user_bible does not exist on this database; add it from the DB team\'s DDL.');
        }

        $this->bible_id = Bible::whereId('ENGESV')->value('id');
        $this->other_bible_id = Bible::where('id', '!=', 'ENGESV')->orderBy('id')->value('id');
        if ($this->bible_id === null || $this->other_bible_id === null) {
            $this->markTestSkipped('Needs the ENGESV Bible and at least one other Bible in the content database.');
        }

        $key = Key::where('name', 'test-key')->first();
        $this->key      = $key->key;
        $this->params   = ['v' => 4, 'key' => $this->key];
        $this->user_id  = $key->user_id;
        $this->key_user = $key->user;

        $other_user_id = User::where('id', '!=', $this->user_id)->orderBy('id')->value('id');
        if ($other_user_id === null) {
            $this->markTestSkipped('Needs a second user to own the featured plan.');
        }

        // v4_internal_plans.index (used to check the plan is still listed) sits behind UserDataAccess.
        config(['auth.compat_users.api_keys.user_data_access' => $this->key]);

        // compareProjects() requires the test-key user to share at least one project with itself.
        // projects.id is smallint unsigned and not auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create([
            'id'   => $this->project_id,
            'name' => 'restart test project',
        ]);
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

        $this->own_plan_id = $this->seedPlan($this->user_id, false);
        $this->featured_plan_id = $this->seedPlan($other_user_id, true);
    }

    protected function tearDown(): void
    {
        UserPlan::flushEventListeners();
        foreach ([$this->own_plan_id, $this->featured_plan_id] as $plan_id) {
            if ($plan_id) {
                // Playlists first: FK cascade removes playlist_items and playlist_items_completed.
                Playlist::where('plan_id', $plan_id)->forceDelete();
                // Plan cascade removes plan_days, plan_days_completed and user_plans.
                Plan::where('id', $plan_id)->forceDelete();
            }
        }
        if ($this->project_id) {
            ProjectMember::where('project_id', $this->project_id)->delete();
            Project::withTrashed()->where('id', $this->project_id)->forceDelete();
        }
        if ($this->created_role_id) {
            Role::where('id', $this->created_role_id)->delete();
        }
        parent::tearDown();
    }

    /** A plan of three days, two playlist items each. Plan::create adds no user_plans row. */
    private function seedPlan(int $owner_id, bool $featured): int
    {
        $plan = Plan::create([
            'user_id'              => $owner_id,
            'name'                 => 'restart test plan',
            'suggested_start_date' => now()->toDateString(),
            'draft'                => false,
        ]);
        $plan->featured = $featured; // not fillable
        $plan->save();

        foreach ([1, 2, 3] as $order) {
            $playlist = Playlist::create([
                'user_id' => $owner_id,
                'name'    => 'restart test day ' . $order,
                'plan_id' => $plan->id,
                'draft'   => false,
            ]);
            $this->plan_days[$plan->id][$order] = PlanDay::create([
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
                $this->playlist_item_ids[$plan->id][] = $item->id;
            }
        }

        return $plan->id;
    }

    /** The user's row with progress: $days_completed days marked complete as the API does (day row + items). */
    private function seedFollowing(int $plan_id, int $days_completed, ?string $user_bible = null): void
    {
        UserPlan::create([
            'user_id'    => $this->user_id,
            'plan_id'    => $plan_id,
            'start_date' => '2026-01-01',
        ]);
        foreach (array_slice($this->plan_days[$plan_id], 0, $days_completed, true) as $plan_day) {
            $plan_day->complete($this->user_id);
        }
        $user_plan = UserPlan::where('plan_id', $plan_id)->where('user_id', $this->user_id)->first();
        $user_plan->calculatePercentageCompleted()->save();
        if ($user_bible !== null) {
            $this->rows($plan_id)->update(['user_bible' => $user_bible]);
        }
    }

    private function rows(int $plan_id)
    {
        return DB::connection('dbp_users')->table('user_plans')
            ->where('plan_id', $plan_id)
            ->where('user_id', $this->user_id);
    }

    /** Everything Restart may change, so an error case can assert none of it moved. */
    private function snapshot(int $plan_id): array
    {
        $row = $this->rows($plan_id)->first();

        return [
            'row'             => $row ? [
                (string) $row->start_date,
                (int) $row->percentage_completed,
                $row->user_bible,
            ] : null,
            'days_completed'  => DB::connection('dbp_users')->table('plan_days_completed')
                ->where('user_id', $this->user_id)
                ->whereIn('plan_day_id', collect($this->plan_days[$plan_id])->pluck('id'))
                ->count(),
            'items_completed' => DB::connection('dbp_users')->table('playlist_items_completed')
                ->where('user_id', $this->user_id)
                ->whereIn('playlist_item_id', $this->playlist_item_ids[$plan_id])
                ->count(),
        ];
    }

    private function restart(array $body, int $plan_id)
    {
        // Satisfies APIToken:check; compareProjects() still runs against the seeded membership.
        $this->actingAs($this->key_user, 'tokens');
        return $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans.restart', ['plan_id' => $plan_id] + $this->params),
            $body
        );
    }

    private function assertRestarted(int $plan_id, string $start_date, ?string $user_bible): void
    {
        $after = $this->snapshot($plan_id);
        $this->assertNotNull($after['row'], 'the user_plans row must still exist');
        $this->assertStringStartsWith($start_date, $after['row'][0]);
        $this->assertSame(0, $after['row'][1]);
        $this->assertSame($user_bible, $after['row'][2]);
        $this->assertSame(0, $after['days_completed']);
        $this->assertSame(0, $after['items_completed']);
    }

    /**
     * AC1: a finished plan, restarted with a date and a Bible (sent in lower case, stored in the Bible's case).
     *
     * @test
     */
    public function restartClearsProgressAndSavesDateAndBible()
    {
        $this->seedFollowing($this->own_plan_id, 3, $this->other_bible_id);
        $this->assertSame(100, $this->snapshot($this->own_plan_id)['row'][1]);

        $response = $this->restart(
            ['start_date' => '2026-10-10', 'user_bible' => strtolower($this->bible_id)],
            $this->own_plan_id
        );

        $response->assertOk();
        $this->assertSame([
            'start_date'           => '2026-10-10',
            'percentage_completed' => 0,
            'user_bible'           => $this->bible_id,
            'message'              => 'Plan restarted',
        ], $response->json());
        $this->assertRestarted($this->own_plan_id, '2026-10-10', $this->bible_id);
    }

    /**
     * AC2: a featured plan made by someone else keeps the user's row (Stop would delete it) and stays listed.
     *
     * @test
     */
    public function restartKeepsAFeaturedPlanInTheUsersPlans()
    {
        $this->seedFollowing($this->featured_plan_id, 3);

        $this->restart(['start_date' => '2026-10-10', 'user_bible' => $this->bible_id], $this->featured_plan_id)
            ->assertOk();

        $this->assertRestarted($this->featured_plan_id, '2026-10-10', $this->bible_id);

        $listed = $this->withHeaders($this->params)->get(route(
            'v4_internal_plans.index',
            $this->params + ['featured' => 'false', 'limit' => 1000, 'sort_by' => 'id', 'sort_dir' => 'desc']
        ));
        $listed->assertSuccessful();
        $this->assertContains($this->featured_plan_id, array_map('intval', array_column($listed->json('data'), 'id')));
    }

    /**
     * AC3: without user_bible the stored Bible is kept and echoed; with none stored the reply says null.
     *
     * @test
     */
    public function restartWithoutBibleKeepsTheStoredOne()
    {
        $this->seedFollowing($this->own_plan_id, 1, $this->bible_id);
        $this->restart(['start_date' => '2026-10-10'], $this->own_plan_id)
            ->assertOk()
            ->assertJsonPath('user_bible', $this->bible_id);
        $this->assertRestarted($this->own_plan_id, '2026-10-10', $this->bible_id);

        $this->seedFollowing($this->featured_plan_id, 1);
        $this->restart(['start_date' => '2026-10-10', 'user_bible' => '   '], $this->featured_plan_id)
            ->assertOk()
            ->assertJsonPath('user_bible', null);
        $this->assertRestarted($this->featured_plan_id, '2026-10-10', null);
    }

    /**
     * AC5 and AC6: a plan in progress can be restarted, and repeating the call gives the same reply.
     *
     * @test
     */
    public function restartWorksOnAPlanInProgressAndIsRepeatable()
    {
        $this->seedFollowing($this->own_plan_id, 1);
        $body = ['start_date' => '2026-10-10', 'user_bible' => $this->bible_id];

        $first = $this->restart($body, $this->own_plan_id);
        $second = $this->restart($body, $this->own_plan_id);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json(), $second->json());
        $this->assertRestarted($this->own_plan_id, '2026-10-10', $this->bible_id);
    }

    /**
     * AC4: an unknown plan or a plan the user does not follow is a 404; Restart never creates the row.
     *
     * @test
     */
    public function restartOfAPlanTheUserDoesNotFollowIs404()
    {
        $missing = (int) Plan::withTrashed()->max('id') + 1000;
        $this->restart(['start_date' => '2026-10-10'], $missing)
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Plan Not Found');

        $this->restart(['start_date' => '2026-10-10'], $this->featured_plan_id)
            ->assertNotFound()
            ->assertJsonPath('error.message', 'User Plan Not Found');
        $this->assertNull($this->rows($this->featured_plan_id)->first());
    }

    public static function badInputProvider(): array
    {
        return [
            'start_date missing'           => [[], 'start_date'],
            'start_date not a real date'   => [['start_date' => '2026-02-30'], 'YYYY-MM-DD'],
            'start_date as a datetime'     => [['start_date' => '2026-10-10T00:00:00Z'], 'YYYY-MM-DD'],
            'start_date US format'         => [['start_date' => '10/10/2026'], 'YYYY-MM-DD'],
            'user_bible unknown'           => [['start_date' => '2026-10-10', 'user_bible' => 'XXXXXX'], 'does not match any Bible'],
            'user_bible a number'          => [['start_date' => '2026-10-10', 'user_bible' => 123], 'must be a string'],
            'user_bible too long'          => [['start_date' => '2026-10-10', 'user_bible' => 'ENGESVN2DA16X'], 'may not be longer than 12 characters'],
        ];
    }

    /**
     * AC4: every bad input is a 422 before any write, so progress, date, Bible and row are untouched.
     *
     * @dataProvider badInputProvider
     * @test
     */
    public function badInputIs422AndChangesNothing(array $body, string $message)
    {
        $this->seedFollowing($this->own_plan_id, 2, $this->bible_id);
        $before = $this->snapshot($this->own_plan_id);

        $response = $this->restart($body, $this->own_plan_id);

        $response->assertStatus(422);
        $this->assertStringContainsString($message, (string) $response->json('error'));
        $this->assertSame($before, $this->snapshot($this->own_plan_id));
    }

    /**
     * AC7: when a write fails inside the transaction everything rolls back, including the completion rows that
     * were already deleted before the failing UPDATE. This is the test that would fail if the transaction ran
     * on the default (content) connection.
     *
     * @test
     */
    public function aFailedWriteRollsEverythingBack()
    {
        $this->seedFollowing($this->featured_plan_id, 3, $this->other_bible_id);
        $before = $this->snapshot($this->featured_plan_id);
        $this->assertSame(3, $before['days_completed']);

        UserPlan::saving(function () {
            throw new \RuntimeException('forced failure for PlanRestartTest');
        });

        $response = $this->restart(
            ['start_date' => '2026-10-10', 'user_bible' => $this->bible_id],
            $this->featured_plan_id
        );

        $response->assertStatus(500);
        $this->assertSame($before, $this->snapshot($this->featured_plan_id));
    }
}
