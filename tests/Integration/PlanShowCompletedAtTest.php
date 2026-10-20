<?php

namespace Tests\Integration;

use App\Exceptions\MySQLErrorCode;
use App\Models\Plan\Plan;
use App\Models\Plan\PlanDay;
use App\Models\Plan\PlanDayComplete;
use App\Models\Plan\UserPlan;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
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
 * Coverage for the optional `completed_at` key on GET /api/plans/{plan_id} (v4_internal_plans.show), API task 103802.
 *
 * With `include_completed_at=true` every item in `days` gains `completed_at`: when the token user marked that day
 * complete (plan_days_completed.created_at, UTC, "YYYY-MM-DDTHH:MM:SS.000000Z"), or null when the day is not
 * completed or there is no token. Without the parameter, or with any value other than the word `true` (any case),
 * the key is omitted and the payload is identical to before the change.
 *
 * Seeds one plan (not featured) with three days of one playlist item each, adopted by the test user. Days 1 and 2
 * are marked complete with PlanDay::complete() (as the API does), then given distinct completion times with
 * FROM_UNIXTIME(), which stores the given instant whatever the session time zone, so the expected strings hold on
 * any test server. Day 3 is untouched.
 *
 * The API key is sent only as a request header, never in the URL, and nothing that contains it is printed.
 *
 * Seeds data, so run it only against a throwaway test database, never a shared one.
 */
class PlanShowCompletedAtTest extends TestCase
{
    private const DAY1_UNIX = 1790917200; // 2026-10-02T05:00:00Z: 11 PM on Oct 1 in Denver
    private const DAY2_UNIX = 1791920273; // 2026-10-13T19:37:53Z

    protected $key;
    protected $params;
    private $plan_id;
    private $user_id;
    private $key_user;
    private $project_id;
    private $created_role_id;
    private $plan_days = [];
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

        // v4_internal_plans.show sits behind UserDataAccess, which only lets whitelisted keys through.
        config(['auth.compat_users.api_keys.user_data_access' => $this->key]);

        // compareProjects() requires the test-key user to share at least one project with itself.
        // projects.id is smallint unsigned and not auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create([
            'id'   => $this->project_id,
            'name' => 'completed_at test project',
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

        [$this->plan_id, $this->plan_days] = $this->createPlan('completed_at test plan', 3);
    }

    protected function tearDown(): void
    {
        foreach ($this->plan_ids as $plan_id) {
            // Playlists first: FK cascade removes playlist_items and playlist_items_completed.
            Playlist::where('plan_id', $plan_id)->forceDelete();
            // Plan cascade removes plan_days, plan_days_completed and user_plans.
            Plan::where('id', $plan_id)->forceDelete();
        }
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
     * A plan owned and adopted by the test user, with $day_count days of one playlist item each.
     *
     * @return array [plan id, [order => PlanDay]]
     */
    private function createPlan(string $name, int $day_count): array
    {
        $plan = Plan::create([
            'user_id'              => $this->user_id,
            'name'                 => $name,
            'suggested_start_date' => now()->toDateString(),
            'draft'                => false,
        ]);
        $this->plan_ids[] = $plan->id;

        $days = [];
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

        return [$plan->id, $days];
    }

    /**
     * Mark a day complete the way the API does, then pin its completion time.
     */
    private function completeAt(int $order, int $unix, ?int $user_id = null): void
    {
        $user_id = $user_id ?? $this->user_id;
        $this->plan_days[$order]->complete($user_id);
        $this->setCompletedAt($order, $unix, $user_id);
    }

    private function setCompletedAt(int $order, int $unix, int $user_id): void
    {
        DB::connection('dbp_users')->table('plan_days_completed')
            ->where('user_id', $user_id)
            ->where('plan_day_id', $this->plan_days[$order]->id)
            ->update(['created_at' => DB::raw('FROM_UNIXTIME(' . $unix . ')')]);
    }

    private function seedProgress(): void
    {
        $this->completeAt(1, self::DAY1_UNIX);
        $this->completeAt(2, self::DAY2_UNIX);
    }

    /**
     * Call Plan Details for the seeded plan and return the whole response.
     *
     * `key` and `v` travel only as headers (checkParam reads headers first), so the URL holds no secret. Only the
     * route name and the extra query parameters are echoed.
     */
    private function fetchPlan(array $extra = [], bool $as_token_user = true, ?int $plan_id = null, int $status = 200): array
    {
        if ($as_token_user) {
            // Bypass the APIToken middleware's api_token requirement; compareProjects() still runs.
            $this->actingAs($this->key_user, 'tokens');
        }

        $query = array_merge(['plan_id' => $plan_id ?? $this->plan_id], $extra);
        echo "\nTesting: v4_internal_plans.show " . json_encode($extra);
        $response = $this->withHeaders($this->params)->get(route('v4_internal_plans.show', $query));
        $this->assertStatusWithoutKey($response, $status);

        return $response->json();
    }

    /**
     * Every status assertion goes through here: on a failed request Laravel appends the logged exception, whose SQL
     * bindings include the key, so the key is replaced before PHPUnit prints the message.
     */
    private function assertStatusWithoutKey($response, int $status): void
    {
        try {
            $response->assertStatus($status);
        } catch (ExpectationFailedException $e) {
            throw new ExpectationFailedException(str_replace($this->key, '<KEY>', $e->getMessage()));
        }
    }

    private function daysById(array $plan): array
    {
        return array_column($plan['days'], null, 'id');
    }

    private function assertNoDayHasCompletedAt(array $plan, string $message = ''): void
    {
        $this->assertNotEmpty($plan['days'], $message);
        foreach ($plan['days'] as $day) {
            $this->assertArrayNotHasKey('completed_at', $day, $message);
        }
    }

    /**
     * AC1: completed days carry their own UTC time; the open day is null; every day has the key.
     *
     * @category V4_API
     * @category Route Name: v4_internal_plans.show
     * @see      \App\Http\Controllers\Plan\PlansController::show
     * @group    V4
     * @test
     */
    public function completedDaysCarryTheirTime()
    {
        $this->seedProgress();
        $days = $this->daysById($this->fetchPlan(['include_completed_at' => 'true']));

        $this->assertCount(3, $days);
        $this->assertTrue($days[$this->plan_days[1]->id]['completed']);
        $this->assertSame('2026-10-02T05:00:00.000000Z', $days[$this->plan_days[1]->id]['completed_at']);
        $this->assertSame('2026-10-13T19:37:53.000000Z', $days[$this->plan_days[2]->id]['completed_at']);
        $this->assertFalse($days[$this->plan_days[3]->id]['completed']);
        $this->assertArrayHasKey('completed_at', $days[$this->plan_days[3]->id]);
        $this->assertNull($days[$this->plan_days[3]->id]['completed_at']);
    }

    /**
     * AC2: the key is on the day, next to `completed`, also with show_details.
     *
     * @group V4
     * @test
     */
    public function worksWithShowDetails()
    {
        $this->seedProgress();
        $days = $this->daysById($this->fetchPlan(['include_completed_at' => 'true', 'show_details' => 'true']));

        $day = $days[$this->plan_days[1]->id];
        $this->assertSame('2026-10-02T05:00:00.000000Z', $day['completed_at']);
        $this->assertArrayHasKey('playlist', $day);
        foreach ($day['playlist']['items'] ?? [] as $item) {
            $this->assertArrayNotHasKey('completed_at', $item);
        }
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
            $days = $this->daysById($this->fetchPlan(['include_completed_at' => $value]));

            $this->assertSame(
                '2026-10-13T19:37:53.000000Z',
                $days[$this->plan_days[2]->id]['completed_at'],
                'include_completed_at=' . $value
            );
        }
    }

    /**
     * A JSON `true` in the request body turns it on too (checkParam reads the JSON body of a GET).
     *
     * @group V4
     * @test
     */
    public function acceptsJsonTrue()
    {
        $this->seedProgress();
        $this->actingAs($this->key_user, 'tokens');

        echo "\nTesting: v4_internal_plans.show JSON {\"include_completed_at\":true}";
        $response = $this->withHeaders($this->params)->json(
            'GET',
            route('v4_internal_plans.show', ['plan_id' => $this->plan_id]),
            ['include_completed_at' => true]
        );
        $this->assertStatusWithoutKey($response, 200);

        $days = $this->daysById($response->json());
        $this->assertSame('2026-10-13T19:37:53.000000Z', $days[$this->plan_days[2]->id]['completed_at']);
    }

    /**
     * AC3: absent, and any value other than the word true (an array included, which must not be a 500), leaves the
     * key off and the response as it was.
     *
     * @group V4
     * @test
     */
    public function absentAndNonTrueValuesOmitTheKey()
    {
        $this->seedProgress();
        $baseline = $this->fetchPlan();
        $this->assertNoDayHasCompletedAt($baseline, 'no parameter');

        foreach (['false', '1', 'yes', '0', '', ['true']] as $value) {
            $plan = $this->fetchPlan(['include_completed_at' => $value]);
            $this->assertSame($baseline, $plan, 'include_completed_at=' . json_encode($value));
        }
    }

    /**
     * AC4: without a token every day reads null. Its own test because actingAs() in an earlier call would keep the
     * request authenticated.
     *
     * @group V4
     * @test
     */
    public function anonymousRequestGetsNullOnEveryDay()
    {
        $this->seedProgress();
        $plan = $this->fetchPlan(['include_completed_at' => 'true'], false);

        $this->assertNotEmpty($plan['days']);
        foreach ($plan['days'] as $day) {
            $this->assertFalse($day['completed']);
            $this->assertArrayHasKey('completed_at', $day);
            $this->assertNull($day['completed_at']);
        }
    }

    /**
     * AC5: nothing completed reads null everywhere.
     *
     * @group V4
     * @test
     */
    public function nothingCompletedReadsNull()
    {
        $plan = $this->fetchPlan(['include_completed_at' => 'true']);

        foreach ($plan['days'] as $day) {
            $this->assertArrayHasKey('completed_at', $day);
            $this->assertNull($day['completed_at']);
        }
    }

    /**
     * AC6 (model level): completing a completed day again keeps its time; un-completing clears it; completing it
     * again gives a new time; resetting the plan clears every day.
     *
     * @group V4
     * @test
     */
    public function lifecycleOfACompletionTime()
    {
        $this->seedProgress();
        $day1 = $this->plan_days[1]->id;

        // Each response goes into its own variable: the same expression asserted twice would read as one value.
        $this->plan_days[1]->complete($this->user_id);
        $after_repeat = $this->daysById($this->fetchPlan(['include_completed_at' => 'true']));
        $this->assertSame('2026-10-02T05:00:00.000000Z', $after_repeat[$day1]['completed_at'], 'completing again keeps the first time');

        $this->plan_days[1]->unComplete($this->user_id);
        $after_uncomplete = $this->daysById($this->fetchPlan(['include_completed_at' => 'true']));
        $this->assertNull($after_uncomplete[$day1]['completed_at'], 'un-completing clears it');

        $before = time();
        $this->plan_days[1]->complete($this->user_id);
        $after_recomplete = $this->daysById($this->fetchPlan(['include_completed_at' => 'true']));
        $again = $after_recomplete[$day1]['completed_at'];
        $this->assertNotNull($again);
        // The database clock sets it; allow for clock skew between the test runner and the DB server.
        $this->assertEqualsWithDelta($before, strtotime($again), 120, 'completing again sets a new time');

        // A reset that saves progress keeps the completion rows, so the times survive.
        UserPlan::where('plan_id', $this->plan_id)->where('user_id', $this->user_id)->first()
            ->reset(now()->toDateString(), true, $this->user_id)->save();
        $after_saved_reset = $this->daysById($this->fetchPlan(['include_completed_at' => 'true']));
        $this->assertSame($again, $after_saved_reset[$day1]['completed_at'], 'reset with save_progress keeps the time');
        $this->assertSame('2026-10-13T19:37:53.000000Z', $after_saved_reset[$this->plan_days[2]->id]['completed_at']);

        UserPlan::where('plan_id', $this->plan_id)->where('user_id', $this->user_id)->first()
            ->reset(now()->toDateString(), false, $this->user_id)->save();
        foreach ($this->fetchPlan(['include_completed_at' => 'true'])['days'] as $day) {
            $this->assertNull($day['completed_at'], 'reset without save_progress clears every day');
        }
    }

    /**
     * AC8: another user's completion of the same day never shows.
     *
     * @group V4
     * @test
     */
    public function anotherUsersTimesNeverShow()
    {
        $this->seedProgress();
        $other_user = factory(User::class)->create();
        $this->extra_user_ids[] = $other_user->id;
        // The other user completes day 1 a day EARLIER than the test user (a query that lost its user filter would
        // return that earlier time through MIN), and day 3, which the test user has not completed.
        $this->completeAt(1, self::DAY1_UNIX - 86400, $other_user->id);
        $this->completeAt(3, self::DAY2_UNIX, $other_user->id);

        $days = $this->daysById($this->fetchPlan(['include_completed_at' => 'true']));

        $this->assertSame('2026-10-02T05:00:00.000000Z', $days[$this->plan_days[1]->id]['completed_at']);
        $this->assertNull($days[$this->plan_days[3]->id]['completed_at']);
        // The transformer would hide day 3 anyway (the test user's `completed` is false), so check the query itself.
        $day_ids = array_keys(PlanDayComplete::getCompletedAtByPlanAndUserId($this->plan_id, $this->user_id));
        sort($day_ids); // GROUP BY does not promise an order
        $this->assertSame(
            [(int) $this->plan_days[1]->id, (int) $this->plan_days[2]->id],
            $day_ids,
            'only the test user\'s own completed days'
        );
    }

    /**
     * If a duplicate (user, day) row exists (the original migration declares only a non-unique index;
     * userPlanDays:cleanup added the unique key on dev/prod), the earliest time is returned. The test says which
     * case this database exercised.
     *
     * @group V4
     * @test
     */
    public function duplicateRowsGiveTheEarliestTime()
    {
        $this->seedProgress();

        $duplicate_refused = false;
        try {
            DB::connection('dbp_users')->table('plan_days_completed')->insert([
                'user_id'     => $this->user_id,
                'plan_day_id' => $this->plan_days[2]->id,
                'created_at'  => DB::raw('FROM_UNIXTIME(' . (self::DAY2_UNIX - 3600) . ')'),
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() != MySQLErrorCode::DUPLICATE_ENTRY) {
                throw $e;
            }
            $duplicate_refused = true;
        }

        $days = $this->daysById($this->fetchPlan(['include_completed_at' => 'true']));

        $this->assertSame(
            $duplicate_refused ? '2026-10-13T19:37:53.000000Z' : '2026-10-13T18:37:53.000000Z',
            $days[$this->plan_days[2]->id]['completed_at']
        );
        $this->assertSame(
            PlanDayComplete::getCompletedAtByPlanAndUserId($this->plan_id, $this->user_id)[$this->plan_days[2]->id],
            $days[$this->plan_days[2]->id]['completed_at']
        );
    }

    /**
     * AC7: other calls do not gain the key: the plans list (with every flag) and Start's reply, whose days come from
     * the shared scope.
     *
     * @group V4
     * @test
     */
    public function otherCallsDoNotGainTheKey()
    {
        $this->seedProgress();
        $this->actingAs($this->key_user, 'tokens');

        $start = $this->withHeaders($this->params)->post(
            route('v4_internal_plans.start', ['plan_id' => $this->plan_id, 'include_completed_at' => 'true']),
            ['start_date' => now()->toDateString()]
        );
        $this->assertStatusWithoutKey($start, 200);
        $this->assertStringContainsString('"days"', $start->getContent());
        $this->assertStringNotContainsString('completed_at', $start->getContent());

        $index = $this->withHeaders($this->params)->get(route('v4_internal_plans.index', [
            'featured' => 'false', 'limit' => 1000, 'include_completed_at' => 'true',
            'user_status' => 'true', 'completed_days' => 'true',
        ]));
        $this->assertStatusWithoutKey($index, 200);
        $this->assertStringNotContainsString('completed_at', $index->getContent());
    }

    /**
     * AC10: an unknown plan is still 404 with the flag on.
     *
     * @group V4
     * @test
     */
    public function unknownPlanIsStill404()
    {
        $missing = (int) Plan::withTrashed()->max('id') + 1000;
        $body = $this->fetchPlan(['include_completed_at' => 'true'], true, $missing, 404);

        $this->assertSame('Plan Not Found', $body['error']['message']);
    }
}
