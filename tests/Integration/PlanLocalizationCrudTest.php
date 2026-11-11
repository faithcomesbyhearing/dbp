<?php

namespace Tests\Integration;

use App\Models\Bible\Bible;
use App\Models\Language\Language;
use App\Models\Plan\Plan;
use App\Models\Plan\PlanLocalization;
use App\Models\User\Key;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Coverage for the plan localization CRUD (v4_internal_plans_localizations.*), API task 102557.
 *
 * Five editor-only calls on dbp_users.plans_localization. The caller needs a user token, a project shared with the
 * key's owner (401), and the plan_admin role (any plan) or plan_editor (only plans they created), else 403 before any
 * 404. The role belongs to the token's user, never the key's owner, and is never read from client input.
 *
 * Seeds a project shared by the test-key owner and four other users (admin, editor, both roles, no role), a fifth
 * user sharing no project with the key's owner, the role_user rows, and three plans: a featured draft owned by the
 * admin, a featured draft owned by the editor, and a plan of the editor's that is not featured. The plan_admin and
 * plan_editor roles are created once if missing and kept. The featured plans are drafts, so a run that dies before
 * tearDown puts nothing on Discover. Needs the plans_localization table, the ENGESV Bible and the languages
 * eng/fra/zho/ara with their iso1 codes; skipped without them.
 *
 * The router reuses the controller instance (and its $key) within one test method, so every role case has its own
 * method. key and v go in headers only, and nothing about a request is printed (#1114).
 *
 * Seeds data, so run it only against a throwaway test database, never a shared one.
 *
 * @group plans
 */
class PlanLocalizationCrudTest extends TestCase
{
    private const NOT_EDITOR = 'This user is not a plan admin or plan editor.';
    private const NOT_OWNER = 'This user can only edit their own plans.';
    private const NOT_FEATURED = 'This plan is not featured; only featured plans can have localizations.';

    protected $key;
    protected $params;
    private $key_user;
    private $project_id;
    private $admin;
    private $editor;
    private $both;
    private $nobody;
    private $outsider;
    private $role_user_ids = [];
    private $role_ids = [];
    private $plan_ids = [];
    private $admins_plan_id;
    private $editors_plan_id;
    private $unfeatured_plan_id;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::connection('dbp_users')->hasTable('plans_localization')) {
            $this->markTestSkipped('dbp_users.plans_localization does not exist on this database (PBI 103276).');
        }
        if (Bible::whereId('ENGESV')->value('id') !== 'ENGESV') {
            $this->markTestSkipped('Needs the ENGESV Bible in the content database.');
        }
        if (Language::where('iso1', 'fr')->value('iso') !== 'fra'
            || Language::where('iso1', 'zh')->value('iso') !== 'zho'
            || Language::where('iso1', 'en')->value('iso') !== 'eng'
            || !Language::where('iso', 'ara')->exists()
            || Language::where('iso1', 'ar')->exists()
        ) {
            $this->markTestSkipped('Needs languages fra/fr, zho/zh, eng/en and ara with no iso1 (as on dbp_TEST).');
        }
        $key = Key::where('name', 'test-key')->first();
        $this->key      = $key->key;
        $this->params   = ['v' => 4, 'key' => $this->key];
        $this->key_user = $key->user;

        // The outsider shares no project with the key's owner; chosen before the seeded project exists.
        $key_projects = ProjectMember::where('user_id', $this->key_user->id)->pluck('project_id');
        $outsider_id = User::where('id', '!=', $this->key_user->id)
            ->whereNotIn('id', ProjectMember::select('user_id')->whereIn('project_id', $key_projects))
            ->orderBy('id')
            ->value('id');
        $member_ids = User::whereNotIn('id', [$this->key_user->id, (int) $outsider_id])
            ->orderBy('id')
            ->limit(4)
            ->pluck('id');
        if ($outsider_id === null || $member_ids->count() < 4) {
            $this->markTestSkipped('Needs five users besides the test-key owner, one of them in none of its projects.');
        }
        [$this->admin, $this->editor, $this->both, $this->nobody] = User::whereIn('id', $member_ids)->orderBy('id')->get()->all();
        $this->outsider = User::find($outsider_id);

        // compareProjects() needs a project shared with the key's owner. projects.id is smallint unsigned and not
        // auto-increment; pick a free id in the high range.
        do {
            $this->project_id = random_int(60000, 65000);
        } while (Project::withTrashed()->where('id', $this->project_id)->exists());
        Project::create(['id' => $this->project_id, 'name' => 'plan localization test project']);
        $member_role = $this->role('developer', 'Developer');
        foreach ([$this->key_user, $this->admin, $this->editor, $this->both, $this->nobody] as $user) {
            ProjectMember::create([
                'project_id' => $this->project_id,
                'user_id'    => $user->id,
                'role_id'    => $member_role->id,
                'token'      => unique_random('dbp_users.project_members', 'token', 12),
            ]);
        }

        $this->role_ids = [
            'plan_admin'  => $this->role('plan_admin', 'Plan admin')->id,
            'plan_editor' => $this->role('plan_editor', 'Plan editor')->id,
        ];
        $this->grant($this->admin, 'plan_admin');
        $this->grant($this->editor, 'plan_editor');
        $this->grant($this->both, 'plan_admin');
        $this->grant($this->both, 'plan_editor');

        $this->admins_plan_id     = $this->seedPlan($this->admin->id, true);
        $this->editors_plan_id    = $this->seedPlan($this->editor->id, true);
        $this->unfeatured_plan_id = $this->seedPlan($this->editor->id, false);
    }

    protected function tearDown(): void
    {
        foreach ($this->plan_ids as $plan_id) {
            // The FK cascade removes the plan's localizations.
            Plan::withTrashed()->where('id', $plan_id)->forceDelete();
        }
        if ($this->role_user_ids) {
            DB::connection('dbp_users')->table('role_user')->whereIn('id', $this->role_user_ids)->delete();
        }
        if ($this->project_id) {
            ProjectMember::where('project_id', $this->project_id)->delete();
            Project::withTrashed()->where('id', $this->project_id)->forceDelete();
        }
        parent::tearDown();
    }

    /**
     * The role with this slug, created once if the database lacks it and then kept: roles.id is a tinyint, so
     * creating and deleting roles on every run would use up its range. The plan roles are what the DB request
     * inserts anyway. Role declares no $fillable; forceCreate() bypasses mass-assignment protection.
     */
    private function role(string $slug, string $name): Role
    {
        return Role::where('slug', $slug)->first()
            ?? Role::forceCreate(['name' => $name, 'slug' => $slug, 'description' => $name . ' (created by a test)']);
    }

    private function grant(User $user, string $slug): void
    {
        // organization_id is NOT NULL and ignored by the guard; 9 as in the DB request.
        $this->role_user_ids[] = DB::connection('dbp_users')->table('role_user')->insertGetId([
            'role_id'         => $this->role_ids[$slug],
            'user_id'         => $user->id,
            'organization_id' => 9,
        ]);
    }

    /** A plan with no days. Featured plans are drafts too, so they never show on Discover. */
    private function seedPlan(int $owner_id, bool $featured): int
    {
        $plan = Plan::forceCreate([
            'user_id'              => $owner_id,
            'name'                 => 'plan localization test plan',
            'suggested_start_date' => now()->toDateString(),
            'featured'             => $featured,
            'draft'                => true,
        ]);
        $this->plan_ids[] = $plan->id;

        return $plan->id;
    }

    private function seedLocalization(int $plan_id, string $iso, ?string $alt_locale = null, array $extra = []): PlanLocalization
    {
        return PlanLocalization::create([
            'plan_id'    => $plan_id,
            'iso'        => $iso,
            'alt_locale' => $alt_locale,
            'name'       => 'seeded ' . $iso . ($alt_locale ? ' ' . $alt_locale : ''),
        ] + $extra);
    }

    private function rowCount(): int
    {
        return PlanLocalization::whereIn('plan_id', $this->plan_ids)->count();
    }

    /** A JSON call as $user (no token when null). Extra route parameters become the query string. */
    private function send(string $method, string $route, array $route_params, ?User $user, array $body = [])
    {
        if ($user) {
            $this->actingAs($user, 'tokens');
        }
        return $this->withHeaders($this->params)->json($method, route($route, $route_params), $body);
    }

    /** A call with a raw body, for bodies json() cannot produce ({}, invalid JSON, a top-level list). */
    private function sendRaw(string $method, string $route, array $route_params, User $user, string $content, string $type = 'application/json')
    {
        $this->actingAs($user, 'tokens');
        $server = $this->transformHeadersToServerVars($this->params + ['Content-Type' => $type, 'Accept' => 'application/json']);
        return $this->call($method, route($route, $route_params), [], [], [], $server, $content);
    }

    private function index(int $plan_id, ?User $user, array $query = [])
    {
        return $this->send('GET', 'v4_internal_plans_localizations.index', ['plan_id' => $plan_id] + $query, $user);
    }

    private function store(int $plan_id, ?User $user, array $body, array $query = [])
    {
        return $this->send('POST', 'v4_internal_plans_localizations.store', ['plan_id' => $plan_id] + $query, $user, $body);
    }

    private function show(int $id, ?User $user, array $query = [])
    {
        return $this->send('GET', 'v4_internal_plans_localizations.show', ['localization_id' => $id] + $query, $user);
    }

    private function update(int $id, ?User $user, array $body, array $query = [])
    {
        return $this->send('PUT', 'v4_internal_plans_localizations.update', ['localization_id' => $id] + $query, $user, $body);
    }

    private function destroy(int $id, ?User $user, array $query = [])
    {
        return $this->send('DELETE', 'v4_internal_plans_localizations.destroy', ['localization_id' => $id] + $query, $user);
    }

    private function assertError($response, int $status, string $message): void
    {
        $response->assertStatus($status);
        $this->assertSame($message, $response->json('error.message'));
    }

    /**
     * Runs all five calls as $user on a seeded row of $plan_id and checks each gets $status with $message, and that
     * nothing was written.
     */
    private function assertAllFiveRefused(int $plan_id, ?User $user, int $status, ?string $message, array $query = []): void
    {
        $row = $this->seedLocalization($plan_id, 'eng', 'en-GB', ['description' => 'untouched']);
        $before = $this->rowCount();

        $responses = [
            'index'   => $this->index($plan_id, $user, $query),
            'store'   => $this->store($plan_id, $user, ['iso' => 'fra', 'name' => 'x', 'plan_role' => 'plan_admin'], $query),
            'show'    => $this->show($row->id, $user, $query),
            'update'  => $this->update($row->id, $user, ['description' => 'changed', 'plan_role' => 'plan_admin'], $query),
            'destroy' => $this->destroy($row->id, $user, $query),
        ];
        foreach ($responses as $call => $response) {
            $this->assertSame($status, $response->status(), $call);
            if ($message !== null) {
                $this->assertSame($message, $response->json('error.message'), $call);
            }
        }

        $this->assertSame($before, $this->rowCount());
        $this->assertSame('untouched', $row->fresh()->description);
    }

    /** @test */
    public function withoutATokenEveryCallIs401()
    {
        $this->assertAllFiveRefused($this->admins_plan_id, null, 401, null);
    }

    /** @test */
    public function aUserWhoSharesNoProjectWithTheKeysOwnerIs401EvenWithARole()
    {
        $this->grant($this->outsider, 'plan_admin');

        $this->assertAllFiveRefused(
            $this->admins_plan_id,
            $this->outsider,
            401,
            trans('api.projects_users_not_connected')
        );
    }

    /** @test */
    public function aUserWithNeitherRoleIs403OnAllFiveCallsBeforeAny404()
    {
        $this->assertAllFiveRefused($this->admins_plan_id, $this->nobody, 403, self::NOT_EDITOR);

        // The role is checked before the lookup, so unknown ids reveal nothing.
        $this->assertError($this->index(999999999, $this->nobody), 403, self::NOT_EDITOR);
        $this->assertError($this->show(999999999, $this->nobody), 403, self::NOT_EDITOR);
    }

    /** @test */
    public function aKeyWhoseOwnerIsAPlanAdminGivesAUserWithoutARoleNothing()
    {
        $this->grant($this->key_user, 'plan_admin');

        $this->assertAllFiveRefused($this->admins_plan_id, $this->nobody, 403, self::NOT_EDITOR);
    }

    /** @test */
    public function aPlanEditorClaimingPlanAdminInTheQueryOrBodyIsStillRefusedOnSomeoneElsesPlan()
    {
        $this->assertAllFiveRefused(
            $this->admins_plan_id,
            $this->editor,
            403,
            self::NOT_OWNER,
            ['plan_role' => 'plan_admin', 'dbp.plan_editor_guard.role' => 'plan_admin']
        );
    }

    /** @test */
    public function aPlanEditorIs403OnEveryCallOnSomeoneElsesPlanAnd404ForUnknownIds()
    {
        $this->assertAllFiveRefused($this->admins_plan_id, $this->editor, 403, self::NOT_OWNER);

        $this->assertError($this->index(999999999, $this->editor), 404, 'Plan Not Found');
        $this->assertError($this->store(999999999, $this->editor, ['iso' => 'fra', 'name' => 'x']), 404, 'Plan Not Found');
        $this->assertError($this->show(999999999, $this->editor), 404, 'Plan Localization Not Found');
        $this->assertError($this->update(999999999, $this->editor, ['name' => 'x']), 404, 'Plan Localization Not Found');
        $this->assertError($this->destroy(999999999, $this->editor), 404, 'Plan Localization Not Found');
    }

    /** @test */
    public function aPlanEditorCanDoEverythingOnAFeaturedDraftPlanTheyCreated()
    {
        $this->assertTrue((bool) Plan::find($this->editors_plan_id)->draft);

        $created = $this->store($this->editors_plan_id, $this->editor, ['iso' => 'fra', 'name' => 'Mon plan']);
        $created->assertOk();
        $id = $created->json('id');
        $this->assertSame($this->editors_plan_id, $created->json('plan_id'));

        $this->show($id, $this->editor)->assertOk()->assertJson(['id' => $id, 'name' => 'Mon plan']);
        $this->assertSame([$id], array_column($this->index($this->editors_plan_id, $this->editor)->json('data'), 'id'));
        $this->update($id, $this->editor, ['name' => 'Mon plan à moi'])->assertOk()->assertJson(['name' => 'Mon plan à moi']);
        $this->destroy($id, $this->editor)->assertOk()->assertExactJson(['id' => $id, 'message' => 'Plan localization deleted']);
        $this->assertNull(PlanLocalization::find($id));
    }

    /** @test */
    public function aUserWithBothRolesActsAsAPlanAdmin()
    {
        $created = $this->store($this->admins_plan_id, $this->both, ['iso' => 'fra', 'name' => 'x']);
        $created->assertOk();

        $this->show($created->json('id'), $this->both)->assertOk();
        $this->destroy($created->json('id'), $this->both)->assertOk();
    }

    /** @test */
    public function aPlanAdminCanCreateReadListUpdateAndDeleteOnAnyFeaturedPlan()
    {
        $created = $this->store($this->admins_plan_id, $this->admin, [
            'iso'         => 'fra',
            'name'        => 'La Bible en un an',
            'description' => 'Une description',
            'bible_id'    => 'engesv',
        ]);
        $created->assertOk();
        $created->assertJsonStructure(['id', 'plan_id', 'iso', 'alt_locale', 'name', 'thumbnail', 'description', 'bible_id', 'created_at', 'updated_at']);
        $created->assertJson([
            'plan_id'    => $this->admins_plan_id,
            'iso'        => 'fra',
            'alt_locale' => null,
            'thumbnail'  => null,
            'bible_id'   => 'ENGESV',
        ]);
        $fra = $created->json('id');

        // alt_locale is stored and returned in canonical case.
        $fr_ca = $this->store($this->admins_plan_id, $this->admin, ['iso' => 'FRA', 'alt_locale' => 'fr-ca', 'name' => 'Québec', 'thumbnail' => 'qc.png', 'bible_id' => 'ENGESV']);
        $fr_ca->assertOk()->assertJson(['iso' => 'fra', 'alt_locale' => 'fr-CA']);
        $zh = $this->store($this->admins_plan_id, $this->admin, ['iso' => 'zho', 'alt_locale' => 'zh-hant-hk', 'name' => '一年', 'thumbnail' => 'hk.png', 'bible_id' => 'ENGESV']);
        $zh->assertOk()->assertJson(['alt_locale' => 'zh-Hant-HK']);

        // Read and list (ordered by id; the other plan's rows are not listed; an empty list for a plan with none).
        $this->show($fra, $this->admin)->assertOk()->assertJson(['id' => $fra, 'name' => 'La Bible en un an']);
        $this->seedLocalization($this->editors_plan_id, 'spa');
        $listed = $this->index($this->admins_plan_id, $this->admin);
        $listed->assertOk();
        $this->assertSame([$fra, $fr_ca->json('id'), $zh->json('id')], array_column($listed->json('data'), 'id'));
        $this->index($this->unfeatured_plan_id, $this->admin)->assertOk()->assertExactJson(['data' => []]);

        // A partial update changes only what was sent, and moves updated_at.
        PlanLocalization::whereKey($fra)->update(['updated_at' => '2020-01-01 00:00:00']);
        $before = PlanLocalization::find($fra);
        $this->update($fra, $this->admin, ['description' => 'Nouvelle description'])->assertOk();
        $after = PlanLocalization::find($fra);
        $this->assertSame('Nouvelle description', $after->description);
        foreach (['iso', 'alt_locale', 'name', 'thumbnail', 'bible_id'] as $field) {
            $this->assertSame($before->$field, $after->$field, $field);
        }
        $this->assertEquals($before->created_at, $after->created_at);
        $this->assertNotEquals('2020-01-01 00:00:00', $after->updated_at->format('Y-m-d H:i:s'));

        // null clears, and "" clears the same way.
        $this->update($zh->json('id'), $this->admin, ['thumbnail' => null, 'bible_id' => null, 'alt_locale' => null])
            ->assertOk()->assertJson(['thumbnail' => null, 'bible_id' => null, 'alt_locale' => null]);
        $this->update($fr_ca->json('id'), $this->admin, ['thumbnail' => '', 'bible_id' => ''])
            ->assertOk()->assertJson(['thumbnail' => null, 'bible_id' => null, 'alt_locale' => 'fr-CA']);

        // The object read can be sent back: its own plan_id, id and timestamps are ignored.
        $read = $this->show($fra, $this->admin)->json();
        $this->update($fra, $this->admin, $read)->assertOk()->assertJson(['id' => $fra, 'name' => 'La Bible en un an']);

        // Delete: gone, a second delete is a 404, the plan and its other rows are untouched.
        $this->destroy($fra, $this->admin)->assertOk()->assertExactJson(['id' => $fra, 'message' => 'Plan localization deleted']);
        $this->assertError($this->show($fra, $this->admin), 404, 'Plan Localization Not Found');
        $this->assertError($this->destroy($fra, $this->admin), 404, 'Plan Localization Not Found');
        $this->assertNotNull(Plan::find($this->admins_plan_id));
        $this->assertSame(2, PlanLocalization::where('plan_id', $this->admins_plan_id)->count());
    }

    /** @test */
    public function aDuplicateIsA409WithTheExistingIdAndWritesNothing()
    {
        $fra = $this->seedLocalization($this->admins_plan_id, 'fra');
        $fr_ca = $this->seedLocalization($this->admins_plan_id, 'fra', 'fr-CA');
        $zho = $this->seedLocalization($this->admins_plan_id, 'zho', 'zh-Hant-HK');
        $before = $this->rowCount();
        $plan = $this->admins_plan_id;

        // A second row with no alt_locale, also in another case.
        foreach (['fra', 'FRA'] as $iso) {
            $this->assertError(
                $this->store($plan, $this->admin, ['iso' => $iso, 'name' => 'x']),
                409,
                "Plan {$plan} already has a localization for iso fra with no alt_locale (id {$fra->id})."
            );
        }
        // The same locale, case-insensitively.
        $this->assertError(
            $this->store($plan, $this->admin, ['iso' => 'FRA', 'alt_locale' => 'FR-ca', 'name' => 'x']),
            409,
            "Plan {$plan} already has a localization for iso fra and alt_locale fr-CA (id {$fr_ca->id})."
        );
        // An update that would make a duplicate.
        $this->assertError(
            $this->update($zho->id, $this->admin, ['iso' => 'fra', 'alt_locale' => 'fr-CA']),
            409,
            "Plan {$plan} already has a localization for iso fra and alt_locale fr-CA (id {$fr_ca->id})."
        );

        $this->assertSame($before, $this->rowCount());
        $this->assertSame('zho', $zho->fresh()->iso);
    }

    /** @test */
    public function invalidBodiesAre422AndWriteNothing()
    {
        $row = $this->seedLocalization($this->admins_plan_id, 'fra', null, ['description' => 'untouched']);
        $before = $this->rowCount();
        $store = 'v4_internal_plans_localizations.store';
        $update = 'v4_internal_plans_localizations.update';
        $plan_params = ['plan_id' => $this->admins_plan_id];
        $row_params = ['localization_id' => $row->id];

        // Not JSON, invalid JSON, a list.
        $this->assertError(
            $this->sendRaw('POST', $store, $plan_params, $this->admin, 'iso=fra&name=x', 'application/x-www-form-urlencoded'),
            422,
            'Send the fields as a JSON object with Content-Type: application/json.'
        );
        $this->assertError($this->sendRaw('POST', $store, $plan_params, $this->admin, '{"iso":'), 422, 'The request body must be a JSON object.');
        $this->assertError($this->sendRaw('POST', $store, $plan_params, $this->admin, '[{"iso":"fra","name":"x"}]'), 422, 'The request body must be a JSON object.');
        $this->assertError($this->sendRaw('PUT', $update, $row_params, $this->admin, '{}'), 422, 'Send at least one field to change.');

        // Several problems, one reply.
        $this->assertError(
            $this->store($this->admins_plan_id, $this->admin, ['iso' => 'fr', 'alt_locale' => 'fr', 'name' => str_repeat('a', 192), 'plan_id' => $this->editors_plan_id]),
            422,
            'iso must be a 3-letter language code. alt_locale must be a locale code with a region or script, such as fr-CA or zh-Hant-HK. name may not be longer than 191 characters. plan_id does not match the plan in the URL.'
        );
        // The lookups.
        $this->assertError(
            $this->store($this->admins_plan_id, $this->admin, ['iso' => 'qqx', 'name' => 'x', 'bible_id' => 'NOSUCHBIBLE']),
            422,
            'iso does not match any language. bible_id does not match any Bible.'
        );
        // Update limits.
        $this->assertError($this->update($row->id, $this->admin, ['name' => null]), 422, 'name cannot be empty.');
        $this->assertError($this->update($row->id, $this->admin, ['iso' => '']), 422, 'iso cannot be empty.');
        $this->assertError($this->update($row->id, $this->admin, ['plan_id' => $this->editors_plan_id]), 422, 'Send at least one field to change. plan_id cannot be changed.');
        $this->assertError($this->update($row->id, $this->admin, ['description' => 'x', 'plan_id' => null]), 422, 'plan_id cannot be changed.');

        $this->assertSame($before, $this->rowCount());
        $this->assertSame('untouched', $row->fresh()->description);
    }

    /** @test */
    public function planStateRulesHold()
    {
        // Create needs a featured plan; an unknown plan is a 404.
        $this->assertError($this->store($this->unfeatured_plan_id, $this->admin, ['iso' => 'fra', 'name' => 'x']), 422, self::NOT_FEATURED);
        $this->assertError($this->store(999999999, $this->admin, ['iso' => 'fra', 'name' => 'x']), 404, 'Plan Not Found');
        $this->assertError($this->index(999999999, $this->admin), 404, 'Plan Not Found');

        // A plan that is no longer featured: update refused; read, list and delete allowed.
        $kept = $this->seedLocalization($this->editors_plan_id, 'fra');
        $removed = $this->seedLocalization($this->editors_plan_id, 'spa');
        Plan::whereKey($this->editors_plan_id)->update(['featured' => false]);
        $this->assertError($this->update($kept->id, $this->admin, ['name' => 'y']), 422, self::NOT_FEATURED);
        $this->show($kept->id, $this->admin)->assertOk();
        $this->index($this->editors_plan_id, $this->admin)->assertOk();
        $this->destroy($removed->id, $this->admin)->assertOk();

        // A soft-deleted plan: it and its rows behave as absent.
        $row = $this->seedLocalization($this->admins_plan_id, 'fra');
        Plan::whereKey($this->admins_plan_id)->delete();
        $this->assertError($this->store($this->admins_plan_id, $this->admin, ['iso' => 'spa', 'name' => 'x']), 404, 'Plan Not Found');
        $this->assertError($this->index($this->admins_plan_id, $this->admin), 404, 'Plan Not Found');
        $this->assertError($this->show($row->id, $this->admin), 404, 'Plan Localization Not Found');
        $this->assertError($this->update($row->id, $this->admin, ['name' => 'y']), 404, 'Plan Localization Not Found');
        $this->assertError($this->destroy($row->id, $this->admin), 404, 'Plan Localization Not Found');
        $this->assertNotNull(PlanLocalization::find($row->id));
    }

    /** @test */
    public function isoAndAltLocaleMustBeTheSameLanguage()
    {
        $before = $this->rowCount();
        $this->assertError(
            $this->store($this->admins_plan_id, $this->admin, ['iso' => 'eng', 'alt_locale' => 'fr-CA', 'name' => 'x']),
            422,
            'alt_locale fr-CA does not belong to the language eng.'
        );
        $this->assertError(
            $this->store($this->admins_plan_id, $this->admin, ['iso' => 'eng', 'alt_locale' => 'ar-EG', 'name' => 'x']),
            422,
            'alt_locale ar-EG does not belong to the language eng.'
        );
        $this->assertSame($before, $this->rowCount());

        foreach ([['fra', 'fr-CA'], ['zho', 'zh-Hant-HK'], ['ara', 'ar-EG']] as [$iso, $alt_locale]) {
            $this->store($this->admins_plan_id, $this->admin, ['iso' => $iso, 'alt_locale' => $alt_locale, 'name' => 'x'])
                ->assertOk();
        }

        // Changing only iso is checked against the stored alt_locale.
        $fr_ca = PlanLocalization::where('plan_id', $this->admins_plan_id)->where('alt_locale', 'fr-CA')->first();
        $this->assertError(
            $this->update($fr_ca->id, $this->admin, ['iso' => 'eng']),
            422,
            'alt_locale fr-CA does not belong to the language eng.'
        );
        $this->assertSame('fra', $fr_ca->fresh()->iso);
    }
}
