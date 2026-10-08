<?php

namespace Tests\Integration;

use App\Models\Bible\Bible;
use App\Models\Plan\Plan;
use App\Models\Plan\UserPlan;
use App\Models\User\Key;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Coverage for the user's chosen Bible on a plan, `user_plans.user_bible` (PBI 102552):
 *   POST   /plans/{id}/start   optional `user_bible`, echoed in the reply only when sent
 *   GET    /plans/{id}         `include_user_bible=true` adds `user_bible` (null when none)
 *   PUT    /plans/{id}/bible   set or change it; the user must already have a user_plans row
 *   DELETE /plans/{id}/bible   clear it
 * A bad value is a 422 with nothing written. Without the new inputs every call behaves as before.
 *
 * The repo has no migration for user_plans.user_bible (the DB team added it), so the class skips
 * when the column is missing; the local test database adds it from the DB team's DDL.
 *
 * Seeds a project membership and a plan, so run it only against a throwaway test database, never
 * a shared one. Extends TestCase rather than ApiV4Test so ApiV4Test's stale @test methods, whose
 * routes no longer exist, are not inherited.
 *
 * @group plans
 */
class PlanUserBibleTest extends TestCase
{
    protected $key;
    protected $params;
    private $plan_id;
    private $user_id;
    private $key_user;
    private $project_id;
    private $created_role_id;
    private $bible_id;
    private $other_bible_id;

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

        // v4_internal_plans.show sits behind UserDataAccess, which only lets whitelisted keys through.
        config(['auth.compat_users.api_keys.user_data_access' => $this->key]);

        // compareProjects() requires the test-key user to share at least one project with itself.
        // projects.id is smallint unsigned and not auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create([
            'id'   => $this->project_id,
            'name' => 'user_bible test project',
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

        // Plan::create (unlike POST /api/plans) adds no user_plans row, so each test starts with none.
        $plan = Plan::create([
            'user_id'              => $this->user_id,
            'name'                 => 'user_bible test plan',
            'suggested_start_date' => now()->toDateString(),
            'draft'                => false,
        ]);
        $this->plan_id = $plan->id;
    }

    protected function tearDown(): void
    {
        if ($this->plan_id) {
            // Plan cascade removes user_plans; forceDelete bypasses SoftDeletes so the cascade fires.
            Plan::where('id', $this->plan_id)->forceDelete();
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

    /** A user_plans row as an old-style Start left it: no Bible, optionally some progress. */
    private function seedRow(?string $user_bible = null, int $percentage_completed = 0, string $start_date = '2026-01-01'): void
    {
        UserPlan::create([
            'user_id'              => $this->user_id,
            'plan_id'              => $this->plan_id,
            'start_date'           => $start_date,
            'percentage_completed' => $percentage_completed,
        ]);
        if ($user_bible !== null) {
            $this->rows()->update(['user_bible' => $user_bible]);
        }
    }

    private function rows()
    {
        return DB::connection('dbp_users')->table('user_plans')
            ->where('plan_id', $this->plan_id)
            ->where('user_id', $this->user_id);
    }

    private function row()
    {
        return $this->rows()->first();
    }

    private function actAsUser(): void
    {
        // Satisfies APIToken / APIToken:check; compareProjects() still runs against the seeded membership.
        $this->actingAs($this->key_user, 'tokens');
    }

    private function start(array $body, ?int $plan_id = null)
    {
        $this->actAsUser();
        return $this->withHeaders($this->params)->postJson(
            route('v4_internal_plans.start', ['plan_id' => $plan_id ?? $this->plan_id] + $this->params),
            $body
        );
    }

    private function show(array $query = [], bool $as_user = true)
    {
        if ($as_user) {
            $this->actAsUser();
        }
        return $this->withHeaders($this->params)->get(
            route('v4_internal_plans.show', ['plan_id' => $this->plan_id] + $this->params + $query)
        );
    }

    private function putBible(array $body, ?int $plan_id = null, bool $as_user = true)
    {
        if ($as_user) {
            $this->actAsUser();
        }
        return $this->withHeaders($this->params)->putJson(
            route('v4_internal_plans.bible_update', ['plan_id' => $plan_id ?? $this->plan_id] + $this->params),
            $body
        );
    }

    private function deleteBible(?int $plan_id = null)
    {
        $this->actAsUser();
        return $this->withHeaders($this->params)->deleteJson(
            route('v4_internal_plans.bible_destroy', ['plan_id' => $plan_id ?? $this->plan_id] + $this->params)
        );
    }

    private function missingPlanId(): int
    {
        return (int) Plan::withTrashed()->max('id') + 1000;
    }

    public static function badValueProvider(): array
    {
        return [
            'unknown id'      => ['XXXXXX', 'does not match any Bible'],
            'the word null'   => ['null', 'does not match any Bible'],
            'a number'        => [123, 'must be a string'],
            'true'            => [true, 'must be a string'],
            'a list'          => [['ENGESV'], 'must be a string'],
            'an object'       => [['a' => 1], 'must be a string'],
            'too long'        => ['ENGESVN2DA16X', 'may not be longer than 12 characters'],
        ];
    }

    /**
     * AC1: first Start with a Bible creates the row with both values and echoes the Bible.
     *
     * @test
     */
    public function startWithBibleCreatesTheRowAndEchoesIt()
    {
        $response = $this->start(['start_date' => '2026-10-05', 'user_bible' => $this->bible_id]);

        $response->assertOk();
        $response->assertJsonPath('user_bible', $this->bible_id);
        $row = $this->row();
        $this->assertNotNull($row);
        $this->assertSame($this->bible_id, $row->user_bible);
        $this->assertStringStartsWith('2026-10-05', (string) $row->start_date);
    }

    /**
     * AC2 / AC12: Start on an existing (legacy) row replaces the Bible and the date, not progress.
     *
     * @test
     */
    public function startOnAnExistingRowReplacesTheBibleAndKeepsProgress()
    {
        $this->seedRow($this->other_bible_id, 40);

        $this->start(['start_date' => '2026-10-05', 'user_bible' => $this->bible_id])->assertOk();

        $row = $this->row();
        $this->assertSame($this->bible_id, $row->user_bible);
        $this->assertStringStartsWith('2026-10-05', (string) $row->start_date);
        $this->assertSame(40, (int) $row->percentage_completed);
    }

    /**
     * AC3 / D3: the old app's Start (no user_bible) keeps the stored choice and its reply has no key.
     *
     * @test
     */
    public function startWithoutBibleKeepsTheStoredValueAndOmitsTheKey()
    {
        $this->seedRow($this->bible_id);

        $response = $this->start(['start_date' => '2026-10-05']);

        $response->assertOk();
        $response->assertJsonMissingPath('user_bible');
        $this->assertSame($this->bible_id, $this->row()->user_bible);
    }

    /**
     * Blank values mean "not sent" on Start, not "clear".
     *
     * @test
     */
    public function startWithBlankBibleKeepsTheStoredValue()
    {
        $this->seedRow($this->bible_id);

        foreach (['', '   ', null, '0', 0, false] as $blank) {
            $response = $this->start(['start_date' => '2026-10-05', 'user_bible' => $blank]);
            $response->assertOk();
            $response->assertJsonMissingPath('user_bible');
            $this->assertSame($this->bible_id, $this->row()->user_bible);
        }
    }

    /**
     * AC4 / D2: a bad value on a first Start is a 422 and no row is created.
     *
     * @dataProvider badValueProvider
     * @test
     */
    public function startWithBadBibleCreatesNoRow($value, string $message)
    {
        $response = $this->start(['start_date' => '2026-10-05', 'user_bible' => $value]);

        $response->assertStatus(422);
        $this->assertStringContainsString($message, $response->getContent());
        $this->assertNull($this->row());
    }

    /**
     * AC4: a bad value on an existing row changes neither the date nor the stored Bible.
     *
     * @test
     */
    public function startWithBadBibleLeavesAnExistingRowUnchanged()
    {
        $this->seedRow($this->bible_id, 0, '2026-01-01');

        $this->start(['start_date' => '2026-10-05', 'user_bible' => 'XXXXXX'])->assertStatus(422);

        $row = $this->row();
        $this->assertStringStartsWith('2026-01-01', (string) $row->start_date);
        $this->assertSame($this->bible_id, $row->user_bible);
    }

    /**
     * Case-insensitive match; the Bible's own spelling is what is stored and echoed.
     *
     * @test
     */
    public function theCanonicalIdIsStored()
    {
        $response = $this->start(['start_date' => '2026-10-05', 'user_bible' => strtolower($this->bible_id)]);

        $response->assertOk();
        $response->assertJsonPath('user_bible', $this->bible_id);
        $this->assertSame($this->bible_id, $this->row()->user_bible);
    }

    /**
     * AC5: with the flag the key is present: the value, or null for a NULL value or no row.
     *
     * @test
     */
    public function detailsWithTheFlagReturnTheValueOrNull()
    {
        // Never started: no row.
        $response = $this->show(['include_user_bible' => 'true']);
        $response->assertOk();
        $this->assertArrayHasKey('user_bible', $response->json());
        $this->assertNull($response->json('user_bible'));

        $this->seedRow();
        $response = $this->show(['include_user_bible' => 'true']);
        $response->assertOk();
        $this->assertArrayHasKey('user_bible', $response->json());
        $this->assertNull($response->json('user_bible'));

        $this->rows()->update(['user_bible' => $this->bible_id]);
        $this->show(['include_user_bible' => 'TRUE'])->assertOk()->assertJsonPath('user_bible', $this->bible_id);
    }

    /**
     * AC5: no token user → null, even when the token-less caller's plan has a stored value.
     *
     * @test
     */
    public function detailsWithTheFlagAndNoTokenReturnNull()
    {
        $this->seedRow($this->bible_id);

        $response = $this->show(['include_user_bible' => 'true'], false);

        $response->assertOk();
        $this->assertArrayHasKey('user_bible', $response->json());
        $this->assertNull($response->json('user_bible'));
    }

    /**
     * AC6: without the literal word true the payload has no user_bible key.
     *
     * @test
     */
    public function detailsWithoutTheFlagOmitTheKey()
    {
        $this->seedRow($this->bible_id);

        foreach ([[], ['include_user_bible' => '1'], ['include_user_bible' => 'yes'], ['include_user_bible' => 'false']] as $query) {
            $response = $this->show($query);
            $response->assertOk();
            $this->assertArrayNotHasKey('user_bible', $response->json(), json_encode($query));
            $this->assertArrayHasKey('percentage_completed', $response->json());
        }
    }

    /**
     * AC7 / AC12: PUT sets a legacy row's Bible, changes it again, and leaves progress alone.
     *
     * @test
     */
    public function putSetsAndChangesTheBible()
    {
        $this->seedRow(null, 50);

        $this->putBible(['user_bible' => $this->bible_id])
            ->assertOk()
            ->assertExactJson(['user_bible' => $this->bible_id, 'message' => 'Plan Bible updated']);
        $this->assertSame($this->bible_id, $this->row()->user_bible);

        $this->putBible(['user_bible' => $this->other_bible_id])
            ->assertOk()
            ->assertJsonPath('user_bible', $this->other_bible_id);

        $row = $this->row();
        $this->assertSame($this->other_bible_id, $row->user_bible);
        $this->assertSame(50, (int) $row->percentage_completed);
        $this->assertStringStartsWith('2026-01-01', (string) $row->start_date);
    }

    /**
     * AC7: sending the stored value is a 200 with no write, so updated_at does not move.
     *
     * @test
     */
    public function putWithTheSameValueDoesNotWrite()
    {
        $this->seedRow($this->bible_id);
        $this->rows()->update(['updated_at' => '2020-01-01 00:00:00']);

        $this->putBible(['user_bible' => $this->bible_id])->assertOk()->assertJsonPath('user_bible', $this->bible_id);

        $this->assertStringStartsWith('2020-01-01', (string) $this->row()->updated_at);
    }

    /**
     * AC8: the 404s and the 401.
     *
     * @test
     */
    public function putReportsMissingRowsAndPlans()
    {
        $this->putBible(['user_bible' => $this->bible_id])
            ->assertNotFound()
            ->assertJsonPath('error.message', 'User Plan Not Found');

        $this->putBible(['user_bible' => $this->bible_id], $this->missingPlanId())
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Plan Not Found');
    }

    /**
     * AC8: no token → 401 from APIToken:check.
     *
     * @test
     */
    public function putWithoutATokenIsUnauthorized()
    {
        $this->seedRow();

        $this->putBible(['user_bible' => $this->bible_id], null, false)->assertUnauthorized();
        $this->assertNull($this->row()->user_bible);
    }

    /**
     * AC8: required on PUT; blank is missing, not "clear".
     *
     * @test
     */
    public function putWithoutAValueIsRejected()
    {
        $this->seedRow($this->bible_id);

        foreach ([[], ['user_bible' => ''], ['user_bible' => '   '], ['user_bible' => null]] as $body) {
            $response = $this->putBible($body);
            $response->assertStatus(422);
            $this->assertStringContainsString("missing parameter 'user_bible'", $response->getContent());
        }
        $this->assertSame($this->bible_id, $this->row()->user_bible);
    }

    /**
     * AC8: bad values are 422 and the stored value is untouched.
     *
     * @dataProvider badValueProvider
     * @test
     */
    public function putWithABadValueWritesNothing($value, string $message)
    {
        $this->seedRow($this->bible_id);

        $response = $this->putBible(['user_bible' => $value]);

        $response->assertStatus(422);
        $this->assertStringContainsString($message, $response->getContent());
        $this->assertSame($this->bible_id, $this->row()->user_bible);
    }

    /**
     * AC9: no row → 404 User Plan Not Found; unknown plan → 404 Plan Not Found.
     *
     * Kept apart from the 200 cases: within one test the router reuses the controller instance,
     * and replyWithError's setStatusCode(404) would carry over to a later successful reply on the
     * same route (in production each request gets a fresh controller).
     *
     * @test
     */
    public function deleteReportsMissingRowsAndPlans()
    {
        $this->deleteBible()->assertNotFound()->assertJsonPath('error.message', 'User Plan Not Found');
        $this->deleteBible($this->missingPlanId())->assertNotFound()->assertJsonPath('error.message', 'Plan Not Found');
    }

    /**
     * AC9: DELETE clears it, is repeatable, and leaves progress alone.
     *
     * @test
     */
    public function deleteClearsTheBible()
    {
        $this->seedRow($this->bible_id, 30);

        foreach ([1, 2] as $attempt) {
            $this->deleteBible()
                ->assertOk()
                ->assertExactJson(['user_bible' => null, 'message' => 'Plan Bible removed']);
            $row = $this->row();
            $this->assertNull($row->user_bible);
            $this->assertSame(30, (int) $row->percentage_completed);
        }
    }

    /**
     * AC10: Details follows PUT and DELETE.
     *
     * @test
     */
    public function detailsFollowPutAndDelete()
    {
        $this->seedRow();

        $this->putBible(['user_bible' => $this->other_bible_id])->assertOk();
        $this->show(['include_user_bible' => 'true'])->assertJsonPath('user_bible', $this->other_bible_id);

        $this->deleteBible()->assertOk();
        $response = $this->show(['include_user_bible' => 'true']);
        $this->assertArrayHasKey('user_bible', $response->json());
        $this->assertNull($response->json('user_bible'));
    }
}
