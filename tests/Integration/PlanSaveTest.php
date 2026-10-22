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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Coverage for POST /plans/{id}/save (v4_internal_plans.save), API task 102555.
 *
 * Save gives the user a user_plans row with no start date and 0% completed, without starting the plan. A user who
 * already has the plan keeps everything but a sent user_bible. Drafts are a 404 for everyone. Start, Stop, Restart
 * and Complete day then work on the saved row as on any other (Liubov's review: Start and Stop after Save).
 * DELETE /plans/{id}/save (v4_internal_plans.unsave) removes a saved plan, refuses a started plan or one the user
 * created (409), and does nothing for a plan the user does not have (Todd's review of #1124).
 *
 * Seeds three plans of three days with two playlist items each: one created by another user (the shared case; for a
 * featured plan the code path is the same, since Save, Start and Stop never look at the featured flag), one draft by
 * that user, one draft by the test user, and one published plan by the test user. None is featured, so a run that dies before tearDown leaves nothing on
 * Discover. Needs the user_plans.user_bible column (PBI 102552), a second user, and the ENGESVN2DA fileset and MAT
 * book the playlist items point at (foreign keys); skipped without them.
 *
 * Seeds data, so run it only against a throwaway test database, never a shared one. Extends TestCase rather than
 * ApiV4Test so ApiV4Test's stale @test methods are not inherited.
 *
 * @group plans
 */
class PlanSaveTest extends TestCase
{
    protected $key;
    protected $params;
    private $user_id;
    private $key_user;
    private $project_id;
    private $created_role_id;
    private $bible_id;
    private $other_bible_id;
    private $shared_plan_id;
    private $others_draft_id;
    private $own_draft_id;
    private $own_plan_id;
    private $plan_days = [];

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
        if (!DB::connection('dbp')->table('bible_filesets')->where('id', 'ENGESVN2DA')->exists()
            || !DB::connection('dbp')->table('books')->where('id', 'MAT')->exists()) {
            $this->markTestSkipped('Needs the ENGESVN2DA fileset and the MAT book, which the seeded playlist items reference.');
        }

        $key = Key::where('name', 'test-key')->first();
        $this->key      = $key->key;
        $this->params   = ['v' => 4, 'key' => $this->key];
        $this->user_id  = $key->user_id;
        $this->key_user = $key->user;

        $other_user_id = User::where('id', '!=', $this->user_id)->orderBy('id')->value('id');
        if ($other_user_id === null) {
            $this->markTestSkipped('Needs a second user to own the shared plan.');
        }

        // v4_internal_plans.index (used to check My Plans) sits behind UserDataAccess.
        config(['auth.compat_users.api_keys.user_data_access' => $this->key]);

        // compareProjects() requires the test-key user to share at least one project with itself.
        // projects.id is smallint unsigned and not auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create([
            'id'   => $this->project_id,
            'name' => 'save test project',
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

        $this->shared_plan_id = $this->seedPlan($other_user_id, false);
        $this->others_draft_id = $this->seedPlan($other_user_id, true);
        $this->own_draft_id = $this->seedPlan($this->user_id, true);
        $this->own_plan_id = $this->seedPlan($this->user_id, false);
    }

    protected function tearDown(): void
    {
        foreach ([$this->shared_plan_id, $this->others_draft_id, $this->own_draft_id, $this->own_plan_id] as $plan_id) {
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
    private function seedPlan(int $owner_id, bool $draft): int
    {
        $plan = Plan::create([
            'user_id'              => $owner_id,
            'name'                 => 'save test plan',
            'suggested_start_date' => now()->toDateString(),
            'draft'                => $draft,
        ]);

        foreach ([1, 2, 3] as $order) {
            $playlist = Playlist::create([
                'user_id' => $owner_id,
                'name'    => 'save test day ' . $order,
                'plan_id' => $plan->id,
                'draft'   => $draft,
            ]);
            $this->plan_days[$plan->id][$order] = PlanDay::create([
                'plan_id'      => $plan->id,
                'playlist_id'  => $playlist->id,
                'order_column' => $order,
            ]);
            foreach ([1, 2] as $chapter) {
                PlaylistItems::create([
                    'playlist_id'   => $playlist->id,
                    'fileset_id'    => 'ENGESVN2DA',
                    'book_id'       => 'MAT',
                    'chapter_start' => $chapter,
                    'chapter_end'   => $chapter,
                    'verse_start'   => 1,
                    'duration'      => 0,
                ]);
            }
        }

        return $plan->id;
    }

    private function rows(int $plan_id)
    {
        return DB::connection('dbp_users')->table('user_plans')
            ->where('plan_id', $plan_id)
            ->where('user_id', $this->user_id);
    }

    /** The user's rows for the plan, as [start_date, percentage_completed, user_bible, updated_at]. */
    private function rowValues(int $plan_id): array
    {
        return $this->rows($plan_id)->get()->map(function ($row) {
            return [
                $row->start_date === null ? null : (string) $row->start_date,
                (int) $row->percentage_completed,
                $row->user_bible,
                (string) $row->updated_at,
            ];
        })->all();
    }

    private function save(int $plan_id, array $body = [])
    {
        // Satisfies APIToken:check; compareProjects() still runs against the seeded membership. key and v go in
        // headers only, so a failing assertion that prints the URL cannot print the key.
        $this->actingAs($this->key_user, 'tokens');
        echo "\nTesting: v4_internal_plans.save";
        return $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans.save', ['plan_id' => $plan_id]),
            $body
        );
    }

    private function start(int $plan_id, array $body)
    {
        $this->actingAs($this->key_user, 'tokens');
        return $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans.start', ['plan_id' => $plan_id]),
            $body
        );
    }

    private function stop(int $plan_id)
    {
        $this->actingAs($this->key_user, 'tokens');
        return $this->withHeaders($this->params)->deleteJson(
            route('v4_internal_plans.stop', ['plan_id' => $plan_id])
        );
    }

    private function unsave(int $plan_id)
    {
        $this->actingAs($this->key_user, 'tokens');
        echo "\nTesting: v4_internal_plans.unsave";
        return $this->withHeaders($this->params)->deleteJson(
            route('v4_internal_plans.unsave', ['plan_id' => $plan_id])
        );
    }

    /** The user's completed days and items for the plan. */
    private function completions(int $plan_id): int
    {
        $day_ids = collect($this->plan_days[$plan_id])->pluck('id');
        $playlist_ids = collect($this->plan_days[$plan_id])->pluck('playlist_id');
        return DB::connection('dbp_users')->table('plan_days_completed')
                ->where('user_id', $this->user_id)->whereIn('plan_day_id', $day_ids)->count()
            + DB::connection('dbp_users')->table('playlist_items_completed as pic')
                ->join('playlist_items as pli', 'pli.id', '=', 'pic.playlist_item_id')
                ->where('pic.user_id', $this->user_id)->whereIn('pli.playlist_id', $playlist_ids)->count();
    }

    /** The user's own plans list (My Plans), as plan id => entries, so a duplicate row shows as two entries. */
    private function myPlans(): array
    {
        $this->actingAs($this->key_user, 'tokens');
        $response = $this->withHeaders($this->params)->get(route(
            'v4_internal_plans.index',
            ['featured' => 'false', 'limit' => 1000, 'sort_by' => 'id', 'sort_dir' => 'desc']
        ));
        $response->assertSuccessful();
        $by_id = [];
        foreach ($response->json('data') as $plan) {
            $by_id[(int) $plan['id']][] = $plan;
        }
        return $by_id;
    }

    /**
     * AC1: saving a plan the user does not have creates one row with no start date, 0% and no Bible, and replies
     * with the plan object Start returns; the plan is then in My Plans once, with no start date.
     *
     * @test
     */
    public function saveCreatesARowWithNoStartDate()
    {
        $response = $this->save($this->shared_plan_id);

        $response->assertOk()
            ->assertJsonPath('id', $this->shared_plan_id)
            ->assertJsonPath('start_date', null)
            ->assertJsonPath('percentage_completed', 0)
            ->assertJsonPath('draft', false)
            ->assertJsonMissingPath('user_bible');
        $this->assertCount(3, $response->json('days'));

        $rows = $this->rowValues($this->shared_plan_id);
        $this->assertCount(1, $rows);
        $this->assertSame([null, 0, null], array_slice($rows[0], 0, 3));

        $listed = $this->myPlans()[$this->shared_plan_id] ?? [];
        $this->assertCount(1, $listed, 'the saved plan must be in My Plans exactly once');
        $this->assertNull($listed[0]['start_date']);
    }

    /**
     * AC3: saving again writes nothing: one row, and its updated_at does not move (not an interaction).
     *
     * @test
     */
    public function savingAgainChangesNothing()
    {
        $first = $this->save($this->shared_plan_id)->assertOk()->json();
        $this->rows($this->shared_plan_id)->update(['updated_at' => '2026-01-01 00:00:00']);
        $before = $this->rowValues($this->shared_plan_id);

        $second = $this->save($this->shared_plan_id)->assertOk()->json();

        $this->assertSame($before, $this->rowValues($this->shared_plan_id));
        $this->assertSame('2026-01-01 00:00:00', $before[0][3]);
        unset($first['updated_at'], $second['updated_at']);
        $this->assertSame($first, $second);
    }

    /**
     * AC4: saving a plan the user already started keeps its start date, progress and Bible.
     *
     * @test
     */
    public function savingAStartedPlanChangesNothing()
    {
        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $this->shared_plan_id,
            'start_date'           => '2026-01-01',
            'percentage_completed' => 0,
        ]);
        $this->plan_days[$this->shared_plan_id][1]->complete($this->user_id);
        UserPlan::where('plan_id', $this->shared_plan_id)->where('user_id', $this->user_id)->first()
            ->calculatePercentageCompleted()->save();
        $this->rows($this->shared_plan_id)->update(['user_bible' => $this->bible_id, 'updated_at' => '2026-01-02 00:00:00']);
        $before = $this->rowValues($this->shared_plan_id);
        $this->assertSame(33, $before[0][1]);

        $this->save($this->shared_plan_id)
            ->assertOk()
            ->assertJsonPath('start_date', '2026-01-01')
            ->assertJsonPath('percentage_completed', 33);

        $this->assertSame($before, $this->rowValues($this->shared_plan_id));
    }

    /**
     * AC5: a valid user_bible (sent in lower case) is stored in the Bible's case and echoed; on an existing row it
     * replaces the stored one.
     *
     * @test
     */
    public function saveStoresAndEchoesUserBible()
    {
        $this->save($this->shared_plan_id, ['user_bible' => strtolower($this->bible_id)])
            ->assertOk()
            ->assertJsonPath('user_bible', $this->bible_id);
        $this->assertSame($this->bible_id, $this->rowValues($this->shared_plan_id)[0][2]);

        $this->save($this->shared_plan_id, ['user_bible' => $this->other_bible_id])
            ->assertOk()
            ->assertJsonPath('user_bible', $this->other_bible_id);
        $rows = $this->rowValues($this->shared_plan_id);
        $this->assertCount(1, $rows);
        $this->assertSame([null, 0, $this->other_bible_id], array_slice($rows[0], 0, 3));

        // Not sent: the stored Bible is kept and the reply has no user_bible key.
        $this->save($this->shared_plan_id)->assertOk()->assertJsonMissingPath('user_bible');
        $this->assertSame($this->other_bible_id, $this->rowValues($this->shared_plan_id)[0][2]);
    }

    /**
     * AC5: an invalid user_bible is a 422 with Start's messages, and no row is written.
     *
     * @test
     */
    public function invalidUserBibleSavesNothing()
    {
        $cases = [
            'no such Bible'   => ['user_bible' => 'ZZZNOBIBLE'],
            'over 12 chars'   => ['user_bible' => 'ABCDEFGHIJKLM'],
            'not a string'    => ['user_bible' => 123],
        ];
        foreach ($cases as $name => $body) {
            $this->save($this->shared_plan_id, $body)->assertStatus(422);
            $this->assertSame([], $this->rowValues($this->shared_plan_id), $name . ': nothing may be written');
        }
    }

    /**
     * AC6 (Liubov, point 2): Start on a saved plan sets the date on the same row; the saved Bible is kept when Start
     * sends none, and replaced when it sends one. The plan is still in My Plans once.
     *
     * @test
     */
    public function startAfterSaveStartsTheSameRow()
    {
        $this->save($this->shared_plan_id, ['user_bible' => $this->bible_id])->assertOk();

        $this->start($this->shared_plan_id, ['start_date' => '2026-10-20'])
            ->assertOk()
            ->assertJsonPath('start_date', '2026-10-20');
        $rows = $this->rowValues($this->shared_plan_id);
        $this->assertCount(1, $rows);
        $this->assertSame(['2026-10-20', 0, $this->bible_id], array_slice($rows[0], 0, 3));

        $this->start($this->shared_plan_id, ['start_date' => '2026-10-21', 'user_bible' => $this->other_bible_id])
            ->assertOk();
        $this->assertSame(
            ['2026-10-21', 0, $this->other_bible_id],
            array_slice($this->rowValues($this->shared_plan_id)[0], 0, 3)
        );

        $this->assertCount(1, $this->myPlans()[$this->shared_plan_id] ?? []);
    }

    /**
     * AC6 (Liubov, point 3): Stop on a saved plan someone else created deletes the row, the plan leaves My Plans,
     * and it can be saved again.
     *
     * @test
     */
    public function stopAfterSaveRemovesItAndItCanBeSavedAgain()
    {
        $this->save($this->shared_plan_id)->assertOk();

        $this->stop($this->shared_plan_id)->assertOk();
        $this->assertSame([], $this->rowValues($this->shared_plan_id));
        $this->assertArrayNotHasKey($this->shared_plan_id, $this->myPlans());

        $this->save($this->shared_plan_id)->assertOk()->assertJsonPath('start_date', null);
        $this->assertCount(1, $this->rowValues($this->shared_plan_id));
    }

    /**
     * AC6: Restart works on a saved plan and sets its start date; completing a day through the API leaves the
     * start date empty (the plan reads saved until Start, 103805 rev 2).
     *
     * @test
     */
    public function restartAndCompleteDayWorkOnASavedPlan()
    {
        $this->save($this->shared_plan_id)->assertOk();

        $this->actingAs($this->key_user, 'tokens');
        $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans_days.complete', ['day_id' => $this->plan_days[$this->shared_plan_id][1]->id])
        )->assertOk();
        $this->assertNull($this->rowValues($this->shared_plan_id)[0][0]);

        // Save can reach master before Restart (API task 102554) does; this part needs Restart's route.
        if (!Route::has('v4_internal_plans.restart')) {
            $this->markTestSkipped('Restart (API task 102554) is not on this branch.');
        }
        $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans.restart', ['plan_id' => $this->shared_plan_id]),
            ['start_date' => '2026-10-20']
        )->assertOk();
        $rows = $this->rowValues($this->shared_plan_id);
        $this->assertCount(1, $rows);
        $this->assertSame(['2026-10-20', 0], array_slice($rows[0], 0, 2));
    }

    /**
     * AC7: any draft is a 404 Plan Not Found, whoever created it, and nothing is written.
     *
     * @test
     */
    public function draftsCannotBeSaved()
    {
        $this->save($this->others_draft_id)->assertNotFound()->assertJsonPath('error.message', 'Plan Not Found');
        $this->assertSame([], $this->rowValues($this->others_draft_id));

        $this->save($this->own_draft_id)->assertNotFound()->assertJsonPath('error.message', 'Plan Not Found');
        $this->assertSame([], $this->rowValues($this->own_draft_id));
    }

    /**
     * AC8: an unknown plan is a 404; no token or a user outside the key's projects is a 401; nothing is written.
     *
     * @test
     */
    public function errorsWriteNothing()
    {
        $missing_id = (int) Plan::withTrashed()->max('id') + 1000;
        $this->save($missing_id)->assertNotFound()->assertJsonPath('error.message', 'Plan Not Found');

        // No api_token: APIToken:check answers before the controller runs.
        $this->app['auth']->forgetGuards();
        $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans.save', ['plan_id' => $this->shared_plan_id])
        )->assertUnauthorized();
        $this->assertSame([], $this->rowValues($this->shared_plan_id));

        $key_projects = ProjectMember::where('user_id', $this->user_id)->pluck('project_id');
        $outsider = User::where('id', '!=', $this->user_id)
            ->whereNotIn('id', ProjectMember::whereIn('project_id', $key_projects)->select('user_id'))
            ->orderBy('id')
            ->first();
        if (!$outsider) {
            $this->markTestSkipped('Needs a user who shares no project with the test key\'s owner.');
        }
        $this->actingAs($outsider, 'tokens');
        $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans.save', ['plan_id' => $this->shared_plan_id])
        )->assertUnauthorized();
        $this->assertSame(0, DB::connection('dbp_users')->table('user_plans')
            ->where('plan_id', $this->shared_plan_id)->where('user_id', $outsider->id)->count());
    }

    /**
     * AC10: Unsave removes a saved plan, with anything completed in it through the API, and it can be saved again.
     *
     * @test
     */
    public function unsaveRemovesASavedPlan()
    {
        $this->save($this->shared_plan_id)->assertOk();
        $this->actingAs($this->key_user, 'tokens');
        $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans_days.complete', ['day_id' => $this->plan_days[$this->shared_plan_id][1]->id])
        )->assertOk();
        $this->assertGreaterThan(0, $this->completions($this->shared_plan_id));

        $this->unsave($this->shared_plan_id)
            ->assertOk()
            ->assertJsonPath('id', $this->shared_plan_id)
            ->assertJsonPath('start_date', null)
            ->assertJsonPath('percentage_completed', null);

        $this->assertSame([], $this->rowValues($this->shared_plan_id));
        $this->assertSame(0, $this->completions($this->shared_plan_id));
        $this->assertArrayNotHasKey($this->shared_plan_id, $this->myPlans());

        $this->save($this->shared_plan_id)->assertOk()->assertJsonPath('percentage_completed', 0);
        $this->assertCount(1, $this->rowValues($this->shared_plan_id));
    }

    /**
     * AC11: a started plan is refused with 409 and nothing changes (Todd: a stale screen must not wipe progress).
     *
     * @test
     */
    public function unsaveRefusesAStartedPlan()
    {
        $this->save($this->shared_plan_id, ['user_bible' => $this->bible_id])->assertOk();
        $this->start($this->shared_plan_id, ['start_date' => '2026-10-20'])->assertOk();
        $this->actingAs($this->key_user, 'tokens');
        $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans_days.complete', ['day_id' => $this->plan_days[$this->shared_plan_id][1]->id])
        )->assertOk();
        $before = $this->rowValues($this->shared_plan_id);
        $completions = $this->completions($this->shared_plan_id);

        $this->unsave($this->shared_plan_id)
            ->assertStatus(409)
            ->assertJsonPath('error.message', 'This plan has been started. Stop it instead of unsaving it.');

        $this->assertSame($before, $this->rowValues($this->shared_plan_id));
        $this->assertSame($completions, $this->completions($this->shared_plan_id));
    }

    /**
     * AC12: a plan the user created is refused with 409; their record (which keeps it in My Plans) stays.
     *
     * @test
     */
    public function unsaveRefusesThePlansCreator()
    {
        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $this->own_plan_id,
            'percentage_completed' => 0,
        ]);
        $before = $this->rowValues($this->own_plan_id);

        $this->unsave($this->own_plan_id)
            ->assertStatus(409)
            ->assertJsonPath('error.message', 'This plan was created by the user. Delete the plan instead of unsaving it.');

        $this->assertSame($before, $this->rowValues($this->own_plan_id));
    }

    /**
     * AC13: a plan the user does not have returns 200 and nothing is written (repeatable, like Save).
     *
     * @test
     */
    public function unsaveIsRepeatable()
    {
        $this->unsave($this->shared_plan_id)
            ->assertOk()
            ->assertJsonPath('id', $this->shared_plan_id)
            ->assertJsonPath('start_date', null);
        $this->assertSame([], $this->rowValues($this->shared_plan_id));

        $this->save($this->shared_plan_id)->assertOk();
        $this->unsave($this->shared_plan_id)->assertOk();
        $this->unsave($this->shared_plan_id)->assertOk();
        $this->assertSame([], $this->rowValues($this->shared_plan_id));
    }

    /**
     * AC14: drafts and unknown plans are a 404; no token is a 401 and leaves the saved plan in place.
     *
     * @test
     */
    public function unsaveErrors()
    {
        $this->unsave($this->others_draft_id)->assertNotFound()->assertJsonPath('error.message', 'Plan Not Found');
        $missing_id = (int) Plan::withTrashed()->max('id') + 1000;
        $this->unsave($missing_id)->assertNotFound()->assertJsonPath('error.message', 'Plan Not Found');

        $this->save($this->shared_plan_id)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withHeaders($this->params)->deleteJson(
            route('v4_internal_plans.unsave', ['plan_id' => $this->shared_plan_id])
        )->assertUnauthorized();
        $this->assertCount(1, $this->rowValues($this->shared_plan_id));
    }
}
