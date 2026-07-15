<?php

namespace Tests\Integration;

use App\Models\User\Account;
use App\Models\User\APIToken;
use App\Models\User\Profile;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Support\Str;

/**
 * Coverage for the cross-project social login fallback in
 * UsersController::loginWithSocialProvider().
 *
 * The dbp_users database is shared by multiple client apps, each with its own
 * project_id. When a social provider sends no email (Facebook accounts without
 * email, Apple ID on any sign-in after the first), an existing user from another
 * project must still be found by their (provider_id, provider_user_id) pair and
 * linked to the new project, instead of being duplicated (autocreate=true) or
 * rejected with a 401 (autocreate=false).
 */
class SocialLoginTest extends ApiV4Test
{
    private $user;
    private $origin_project_id;
    private $new_project_id;
    private $provider_user_id;
    private $cleanup_user_ids;
    private $created_user_role;

    protected function setUp(): void
    {
        parent::setUp();

        // projects.id is smallint unsigned and not auto-increment — pick free ids in the high range.
        do {
            $this->origin_project_id = random_int(60000, 65000);
        } while (
            Project::withTrashed()
                ->where('id', $this->origin_project_id)
                ->exists()
        );
        Project::create([
            'id' => $this->origin_project_id,
            'name' => 'Social login test origin project',
        ]);
        do {
            $this->new_project_id = random_int(60000, 65000);
        } while (
            $this->new_project_id === $this->origin_project_id ||
            Project::withTrashed()
                ->where('id', $this->new_project_id)
                ->exists()
        );
        Project::create([
            'id' => $this->new_project_id,
            'name' => 'Social login test new project',
        ]);

        // login() resolves Role slug 'user' when linking a ProjectMember.
        $role = Role::where('slug', 'user')->first();
        $this->created_user_role = !$role;
        if (!$role) {
            Role::create([
                'slug' => 'user',
                'name' => 'user',
                'description' => 'User',
            ]);
        }

        $this->user = factory(User::class)->create();
        $this->cleanup_user_ids = [$this->user->id];
        $this->provider_user_id = 'test-social-' . Str::random(24);
        Account::create([
            'user_id' => $this->user->id,
            'project_id' => $this->origin_project_id,
            'provider_id' => 'facebook',
            'provider_user_id' => $this->provider_user_id,
        ]);
    }

    protected function tearDown(): void
    {
        APIToken::whereIn('user_id', $this->cleanup_user_ids)->delete();
        // The users FK on user_accounts has no delete cascade — remove accounts first.
        Account::whereIn('user_id', $this->cleanup_user_ids)->delete();
        Profile::whereIn('user_id', $this->cleanup_user_ids)->delete();
        ProjectMember::whereIn('user_id', $this->cleanup_user_ids)->delete();
        User::withTrashed()
            ->whereIn('id', $this->cleanup_user_ids)
            ->forceDelete();
        // Projects use SoftDeletes — forceDelete fires the FK cascade on any leftover rows.
        Project::withTrashed()
            ->whereIn('id', [$this->origin_project_id, $this->new_project_id])
            ->forceDelete();
        if ($this->created_user_role) {
            Role::where('slug', 'user')->delete();
        }
        parent::tearDown();
    }

    private function socialLogin(array $body)
    {
        $path = route('v4_internal_user.login', $this->params);
        echo "\nTesting: $path";
        return $this->withHeaders($this->params)->post($path, $body);
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_user.login
     * @see      \App\Http\Controllers\User\UsersController::login
     * @group    V4
     * @group    travis
     * @test
     */
    public function crossProjectSocialLoginWithoutEmailSucceeds()
    {
        $response = $this->socialLogin([
            'project_id' => $this->new_project_id,
            'social_provider_id' => 'facebook',
            'social_provider_user_id' => $this->provider_user_id,
        ]);
        $response->assertSuccessful();
        $this->assertEquals($this->user->id, $response->json('id'));
        $this->assertEquals(
            $this->origin_project_id,
            $response->json('linked_from_project')
        );
        $this->assertDatabaseHas(
            'user_accounts',
            [
                'user_id' => $this->user->id,
                'project_id' => $this->new_project_id,
                'provider_id' => 'facebook',
                'provider_user_id' => $this->provider_user_id,
            ],
            'dbp_users'
        );
        $this->assertDatabaseHas(
            'project_members',
            [
                'user_id' => $this->user->id,
                'project_id' => $this->new_project_id,
            ],
            'dbp_users'
        );

        // The link is a one-time event: the account row created above resolves the next
        // login through the project-scoped lookup, so the flag must no longer appear.
        $second_login = $this->socialLogin([
            'project_id' => $this->new_project_id,
            'social_provider_id' => 'facebook',
            'social_provider_user_id' => $this->provider_user_id,
        ]);
        $second_login->assertSuccessful();
        $this->assertEquals($this->user->id, $second_login->json('id'));
        $this->assertNull($second_login->json('linked_from_project'));
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_user.login
     * @see      \App\Http\Controllers\User\UsersController::login
     * @group    V4
     * @group    travis
     * @test
     */
    public function sameProjectSocialLoginUnaffected()
    {
        $response = $this->socialLogin([
            'project_id' => $this->origin_project_id,
            'social_provider_id' => 'facebook',
            'social_provider_user_id' => $this->provider_user_id,
        ]);
        $response->assertSuccessful();
        $this->assertEquals($this->user->id, $response->json('id'));
        $this->assertNull($response->json('linked_from_project'));
        // The project-scoped lookup matched — no additional account row was linked.
        $this->assertEquals(
            1,
            Account::where('provider_user_id', $this->provider_user_id)->count()
        );
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_user.login
     * @see      \App\Http\Controllers\User\UsersController::login
     * @group    V4
     * @group    travis
     * @test
     */
    public function autocreateFalseSucceedsWithCrossProjectAccount()
    {
        $response = $this->socialLogin([
            'project_id' => $this->new_project_id,
            'social_provider_id' => 'facebook',
            'social_provider_user_id' => $this->provider_user_id,
            'autocreate' => 'false',
        ]);
        $response->assertSuccessful();
        $this->assertEquals($this->user->id, $response->json('id'));
        $this->assertEquals(
            $this->origin_project_id,
            $response->json('linked_from_project')
        );
        $this->assertDatabaseHas(
            'user_accounts',
            [
                'user_id' => $this->user->id,
                'project_id' => $this->new_project_id,
                'provider_id' => 'facebook',
                'provider_user_id' => $this->provider_user_id,
            ],
            'dbp_users'
        );
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_user.login
     * @see      \App\Http\Controllers\User\UsersController::login
     * @group    V4
     * @group    travis
     * @test
     */
    public function autocreateFalseStillReturns401WhenNoAccountAnywhere()
    {
        $response = $this->socialLogin([
            'project_id' => $this->new_project_id,
            'social_provider_id' => 'facebook',
            'social_provider_user_id' => 'test-social-' . Str::random(24),
            'autocreate' => 'false',
        ]);
        $response->assertStatus(401);
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_user.login
     * @see      \App\Http\Controllers\User\UsersController::login
     * @group    V4
     * @group    travis
     * @test
     */
    public function softDeletedUserFallsThroughToAutocreate()
    {
        // Re-fetch before soft deleting — the legacy factory instance lacks the
        // deleted_at attribute and errors inside SoftDeletes::runSoftDelete().
        $this->user->fresh()->delete();

        $response = $this->socialLogin([
            'project_id' => $this->new_project_id,
            'social_provider_id' => 'facebook',
            'social_provider_user_id' => $this->provider_user_id,
        ]);
        $response->assertSuccessful();
        $new_user_id = $response->json('id');
        $this->assertNotNull($new_user_id);
        // The soft-deleted user's account is skipped; a new user is created instead of a 401.
        $this->assertNotEquals($this->user->id, $new_user_id);
        $this->cleanup_user_ids[] = $new_user_id;
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_user.login
     * @see      \App\Http\Controllers\User\UsersController::login
     * @group    V4
     * @group    travis
     * @test
     */
    public function oldestAccountWinsAcrossProjects()
    {
        // user_accounts has no unique constraint — the same social identity can point at
        // different users. The fallback must deterministically pick the oldest account.
        $second_user = factory(User::class)->create();
        $this->cleanup_user_ids[] = $second_user->id;
        Account::create([
            'user_id' => $second_user->id,
            'project_id' => $this->origin_project_id,
            'provider_id' => 'facebook',
            'provider_user_id' => $this->provider_user_id,
        ]);

        $response = $this->socialLogin([
            'project_id' => $this->new_project_id,
            'social_provider_id' => 'facebook',
            'social_provider_user_id' => $this->provider_user_id,
        ]);
        $response->assertSuccessful();
        $this->assertEquals($this->user->id, $response->json('id'));
    }

    /**
     * @category V4_API
     * @category Route Name: v4_internal_user.login
     * @see      \App\Http\Controllers\User\UsersController::login
     * @group    V4
     * @group    travis
     * @test
     */
    public function nonexistentProjectIdReturns404InsteadOf500()
    {
        // Before the early project check, an unknown project_id survived until the
        // user_accounts insert and died on the projects FK with a 500.
        do {
            $missing_project_id = random_int(60000, 65000);
        } while (
            Project::withTrashed()
                ->where('id', $missing_project_id)
                ->exists()
        );

        $response = $this->socialLogin([
            'project_id' => $missing_project_id,
            'social_provider_id' => 'facebook',
            'social_provider_user_id' => $this->provider_user_id,
        ]);
        $response->assertStatus(404);
        // Rejected before any side effects: no account row was linked anywhere.
        $this->assertEquals(
            1,
            Account::where('provider_user_id', $this->provider_user_id)->count()
        );
    }
}
