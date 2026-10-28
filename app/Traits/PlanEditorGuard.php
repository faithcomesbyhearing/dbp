<?php

namespace App\Traits;

use App\Models\Plan\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Who may change what a featured plan shows: its localizations (PlanLocalizationsController) and, later, the featured
 * flag itself (API task 104363). Two roles in roles/role_user grant it, and both are assigned by hand through a DB
 * request:
 *  - plan_admin: any plan;
 *  - plan_editor: only the plans the user created (plans.user_id);
 *  - a user with both roles is a plan_admin.
 *
 * The role belongs to the token's user ($request->user()), never to the key's owner ($this->user in APIController):
 * falling back to the key's owner, as DashboardController::isAdmin does, would let anyone holding an editor's key edit.
 * The project check (compareProjects) runs first, as on every user call, but it is not a permission on its own: the
 * role is what grants the right to edit.
 *
 * The using class must extend APIController (key, replyWithError, setStatusCode) and use CheckProjectMembership
 * (compareProjects). Inside it, the statics and the constant are always written self::…: calling a static on the trait
 * itself (PlanEditorGuard::planRoleFor()) is deprecated since PHP 8.1, and PlanEditorGuard::ROLE_ATTRIBUTE is a fatal
 * error. Nothing here throws: every check returns the error response, and every caller must return it.
 */
trait PlanEditorGuard
{
    /**
     * The request attribute holding the caller's best plan role, set only by guardPlanEditors(). Request attributes
     * are never filled from client input, unlike $request->plan_role, input() or get(), which read (or fall back to)
     * the query string and body: a client could otherwise send plan_role=plan_admin.
     */
    const ROLE_ATTRIBUTE = 'dbp.plan_editor_guard.role';

    /**
     * Registers the guard as controller middleware, so it runs after the route's APIToken:check (the token's user is
     * known by then) and before the action. Called from the constructor without $only, it covers every action,
     * including any added later. It returns its error rather than throwing, so the error has the replyWithError shape
     * the actions use (a thrown exception would be rendered by the exception Handler in its own shape).
     *
     * Order: no token user → 401 (only if a route lacks APIToken:check); no shared project → 401; neither plan role →
     * 403, before anything is looked up, so a user without a role cannot probe which ids exist.
     *
     * @param array $only the actions to guard; empty for all of them
     *
     * @return void
     */
    protected function guardPlanEditors(array $only = [])
    {
        $options = $this->middleware(function (Request $request, $next) {
            $user = $request->user();
            if (!$user) {
                return $this->planEditorError(401, trans('auth.failed'));
            }

            if (!$this->compareProjects($user->id, $this->key)) {
                return $this->planEditorError(401, trans('api.projects_users_not_connected'));
            }

            $role = self::planRoleFor((int) $user->id);
            if ($role === null) {
                return $this->planEditorError(403, 'This user is not a plan admin or plan editor.');
            }

            // Kept on the request, not on the controller: the router reuses the controller instance.
            $request->attributes->set(self::ROLE_ATTRIBUTE, $role);

            return $next($request);
        });

        if (!empty($only)) {
            $options->only($only);
        }
    }

    /**
     * The user's best plan role: plan_admin when they hold it, else plan_editor, else null. One query on dbp_users,
     * on the tables directly rather than $user->roles: RoleUser hard-codes the schema name and the relation would load
     * every role. role_user.organization_id is ignored (the roles are global), and the admin role grants neither.
     *
     * @param int $user_id
     *
     * @return string|null 'plan_admin', 'plan_editor' or null
     */
    public static function planRoleFor(int $user_id) : ?string
    {
        $slugs = DB::connection('dbp_users')->table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user_id)
            ->whereIn('roles.slug', ['plan_admin', 'plan_editor'])
            ->pluck('roles.slug')
            ->map(function ($slug) {
                return strtolower((string) $slug);
            })
            ->all();

        if (in_array('plan_admin', $slugs, true)) {
            return 'plan_admin';
        }

        if (in_array('plan_editor', $slugs, true)) {
            return 'plan_editor';
        }

        return null;
    }

    /**
     * Whether a role may act on a plan. Pure, and fails closed: anything but exactly plan_admin or plan_editor,
     * including null, is false (a 403, never a TypeError). The caller casts both ids to int, so a string attribute
     * cannot make an owner look like a stranger or the other way round.
     *
     * @param string|null $role what guardPlanEditors() stored
     * @param int $plan_owner_id plans.user_id
     * @param int $user_id the token's user
     *
     * @return bool
     */
    public static function canEditPlan(?string $role, int $plan_owner_id, int $user_id) : bool
    {
        if ($role === 'plan_admin') {
            return true;
        }

        if ($role === 'plan_editor') {
            return $plan_owner_id === $user_id;
        }

        return false;
    }

    /**
     * The 403 for a plan_editor acting on a plan they did not create, or null when the caller may act on it. Run it
     * after the plan is loaded (an unknown id is a 404 first) and return what it returns:
     * if ($deny = $this->denyUnlessCanEditPlan($request, $plan)) { return $deny; }
     *
     * @param Request $request
     * @param Plan $plan
     *
     * @return \Illuminate\Http\JsonResponse|null
     */
    protected function denyUnlessCanEditPlan(Request $request, Plan $plan)
    {
        $user = $request->user();

        if ($user && self::canEditPlan($this->planRole($request), (int) $plan->user_id, (int) $user->id)) {
            return null;
        }

        return $this->planEditorError(403, 'This user can only edit their own plans.');
    }

    /**
     * The guard's error response. replyWithError() answers a v2 request with [], which is falsy, so a caller's
     * if ($deny = …) would read it as "allowed". The guarded routes are v4-only (APIVersion answers 404 to any other
     * v), but the guard must never fail open: an empty reply becomes an empty JSON body with the same status.
     *
     * @param int $status
     * @param string $message
     *
     * @return mixed a non-empty error response
     */
    private function planEditorError(int $status, string $message)
    {
        return $this->setStatusCode($status)->replyWithError($message) ?: new JsonResponse([], $status);
    }

    /**
     * The role guardPlanEditors() stored for this request. The only place it is read back, and it reads request
     * attributes only (see ROLE_ATTRIBUTE).
     *
     * @param Request $request
     *
     * @return string|null
     */
    private function planRole(Request $request) : ?string
    {
        $role = $request->attributes->get(self::ROLE_ATTRIBUTE);

        return is_string($role) ? $role : null;
    }
}
