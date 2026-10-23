<?php

namespace App\Http\Controllers\Plan;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Spatie\Fractalistic\ArraySerializer;
use App\Traits\AccessControlAPI;
use App\Http\Controllers\APIController;
use App\Models\Bible\Bible;
use App\Models\Language\Language;
use App\Models\Plan\Plan;
use App\Traits\CheckProjectMembership;
use App\Models\Plan\PlanDay;
use App\Models\Plan\PlanDayComplete;
use App\Models\Plan\UserPlan;
use App\Models\Playlist\Playlist;
use App\Transformers\PlanTransformer;
use App\Transformers\PlanTranslateTransformer;
use App\Transformers\PlanDayPlaylistItemsTransformer;
use App\Transformers\PlanBasicTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Services\Plans\PlanService;
use App\Exceptions\MySQLErrorCode;

class PlansController extends APIController
{
    use AccessControlAPI;
    use CheckProjectMembership;

    protected $days_limit = 1095;
    public $plan_service;

    public function __construct()
    {
        parent::__construct();
        $this->plan_service = new PlanService();
    }

    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     path="/plans",
     *     tags={"Plans"},
     *     summary="List a user's plans",
     *     operationId="v4_internal_plans.index",
     *     @OA\Parameter(
     *          name="featured",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Plan/properties/featured"),
     *          description="Return featured plans"
     *     ),
     *     security={{"api_token":{}}},
     *     @OA\Parameter(
     *          name="iso",
     *          in="query",
     *          @OA\Schema(ref="#/components/schemas/Language/properties/iso"),
     *          description="The iso code to filter plans by. For a complete list see the `iso` field in the `/languages` route"
     *     ),
     *     @OA\Parameter(
     *          name="user_status",
     *          in="query",
     *          @OA\Schema(type="boolean", default=false),
     *          description="When set to `true` (case-insensitive) and a valid api_token is supplied, each plan includes a `user_status` key: null (the plan is not in the user's plans: never added, unsaved, or stopped when someone else created it), `saved` (in the user's plans with no start date: not started), `in_progress` (has a start date and is not completed, including a plan with no playlist items yet) or `completed` (has a start date, at least one playlist item, and every playlist item completed). Creating a plan, or saving one (POST /plans/{plan_id}/save), adds it to the user's plans as `saved` until it is started; Unsave (DELETE /plans/{plan_id}/save) removes it again, unless the user created it or has completed something in it (then 409; see Unsave). Stopping a plan clears its progress and removes it from the user's plans, except for the plan's creator, who keeps it and sees `saved`. Any other value, including `1`, leaves the key off, so existing calls are unchanged."
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/limit"),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(ref="#/components/parameters/sort_by"),
     *     @OA\Parameter(ref="#/components/parameters/sort_dir"),
     *     @OA\Parameter(
     *          name="completed_days",
     *          in="query",
     *          @OA\Schema(type="boolean", default=false),
     *          description="When set to `true` (case-insensitive) on the user's own plans (`featured=false` with a valid api_token), each plan includes `completed_days`: the number of its days the user has marked complete, the same days that show `completed: true` in the plan details. Any other value, including `1` or a list, leaves the key off, so existing calls are unchanged."
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_plan_index"))
     *     )
     * )
     *
     *
     * @return mixed
     *
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_plan_index_detail",
     *   allOf={
     *      @OA\Schema(ref="#/components/schemas/v4_plan"),
     *      @OA\Schema(
     *          @OA\Property(
     *              property="completed_days",
     *              type="integer",
     *              description="Present only when completed_days=true on the user's own plans (featured=false with an api_token). The number of the plan's days the user has marked complete; 0 when none. A day whose items are all complete but which was never marked complete is not counted."
     *          )
     *      ),
     *   },
     *   @OA\Property(property="total_days", type="integer"),
     *   @OA\Property(
     *      property="user_status",
     *      type="string",
     *      nullable=true,
     *      enum={"saved", "in_progress", "completed"},
     *      description="Present only when user_status=true and an api_token is supplied. null when the plan is not in the user's plans (never added, unsaved, or stopped when someone else created it). saved when it has no start date (not started), whatever is completed in it. With a start date: completed when the plan has at least one playlist item and every one is completed, otherwise in_progress (including a plan with no items yet). Creating a plan, or saving one (POST /plans/{plan_id}/save), adds it to the user's plans as saved; Unsave (DELETE /plans/{plan_id}/save) removes it again, unless the user created it or has completed something in it (then 409; see Unsave). Stopping a plan clears its progress and removes it from the user's plans, except for its creator, who keeps it and sees saved."
     *   )
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_plan_index",
     *   description="The v4 plan index response.",
     *   title="User plans",
     *   allOf={
     *      @OA\Schema(ref="#/components/schemas/pagination"),
     *   },
     *   @OA\Property(
     *      property="data",
     *      type="array",
     *      @OA\Items(ref="#/components/schemas/v4_plan_index_detail")
     *   )
     * )
     */


    public function index(Request $request)
    {
        $user = $request->user();

        // Validate Project / User Connection
        if (!empty($user) && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $featured = checkBoolean('featured') || empty($user);
        $limit        = (int) (checkParam('limit') ?? 25);
        $sort_by    = checkParam('sort_by') ?? 'name';
        $sort_dir   = checkParam('sort_dir') ?? 'asc';
        $iso = checkParam('iso');
        // Parsed by hand so it is strict whichever checkBoolean() a release carries (before #1062 it accepted any value
        // but "false"): only the word true (any case) or a JSON true turns it on, and an array is ignored, not a 500.
        $user_status_raw = checkParam('user_status');
        $user_status = $user_status_raw === true
            || (is_string($user_status_raw) && strtolower($user_status_raw) === 'true');

        if($featured && $sort_by === 'last_interaction') {
            return $this->setStatusCode(SymfonyResponse::HTTP_BAD_REQUEST)->replyWithError('Sort by last_interaction is not supported for featured plans');
        }

        $language_id = null;
        if ($iso !== null) {
            $language_id = cacheRemember('v4_language_id_from_iso', [$iso], now()->addDay(), function () use ($iso) {
                return optional(Language::where('iso', $iso)->select('id')->first())->id;
            });
        }

        return $this->reply(
            $this->getPlans($featured, $limit, $sort_by, $sort_dir, $user, $language_id, $user_status)
        );
    }

    private function getPlans(
        $featured,
        $limit,
        $sort_by,
        $sort_dir,
        $user,
        $language_id,
        bool $user_status = false
    ) {
        $plans = Plan::with('days')
            ->with('user')
            ->where('plans.draft', 0)
            ->when($language_id, function ($q) use ($language_id) {
                $q->where('plans.language_id', $language_id);
            })
            ->when($featured || empty($user), function ($q) {
                $q->where('plans.featured', '1');
            })->unless($featured, function ($q) use ($user, $sort_by) {
                $q->join('user_plans', function ($join) use ($user) {
                    $join->on('user_plans.plan_id', '=', 'plans.id')->where('user_plans.user_id', $user->id);
                });

                if ($sort_by === 'last_interaction') {
                    $q->sortByLastInteraction($user);
                }

                $q->addSelect(['plans.*', 'user_plans.start_date', 'user_plans.percentage_completed']);
            })
            ->orderBy($sort_by, $sort_dir)->paginate($limit);

        // Resolve the caller's status per plan with one aggregate query, only when asked for and
        // only for an authenticated token user, so every other request keeps today's exact payload.
        $include_user_status = $user_status && !empty($user);
        $user_statuses = [];
        if ($include_user_status) {
            $user_statuses = UserPlan::getStatusesByPlanIdsAndUserId(
                $plans->getCollection()->pluck('id')->all(),
                $user->id
            );
        }

        foreach ($plans as $plan) {
            $plan->total_days = sizeof($plan->days);
            unset($plan->days);

            if ($include_user_status) {
                // null means the user has no user_plans row: never added the plan, unsaved it (unsave()), or
                // stopped a plan someone else created. save() adds a row with no start date (saved). stop() always
                // clears progress but deletes the row only when the
                // user is not the plan's creator (efe242ce, FCBH-1607 "Share plan", Feb 2020; before that
                // it deleted every row). POST /api/plans and plan translation (PlanService::translate, also
                // behind `artisan translate:plan`) give the creator a row with no start date, so a creator
                // never reads null: saved until the plan is started (given a start date), and saved again
                // after stopping (progress and start date cleared); plans inserted
                // straight into the database, or stopped by their creator before efe242ce, have no creator
                // row and read null.
                $plan->user_status = $user_statuses[$plan->id] ?? null;
            }
        }
        $this->addCompletedDays($plans, $featured, $user);
        return $plans;
    }

    /**
     * Add completed_days to each plan of the user's own plans list, only when completed_days=true is sent, so
     * every other request keeps today's exact payload (PBI 102548).
     *
     * The flag is read here rather than passed through getPlans() so this change does not touch the lines the
     * user_status change (PBI 102131) edits in index() and getPlans(); the two can merge in either order.
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $plans
     * @param bool $featured
     * @param mixed $user
     *
     * @return void
     */
    private function addCompletedDays($plans, bool $featured, $user) : void
    {
        // Only the user's own plans carry progress (the user_plans join in getPlans); featured listings never
        // get the key. $featured is already forced to true when there is no token user (index()).
        if ($featured || empty($user)) {
            return;
        }

        // Parsed by hand so it is strict whichever checkBoolean() a release carries (before #1062 it accepted any value
        // but "false"): only the word true (any case) or a JSON true turns it on, and an array is ignored, not a 500.
        $completed_days_raw = checkParam('completed_days');
        $include_completed_days = $completed_days_raw === true
            || (is_string($completed_days_raw) && strtolower($completed_days_raw) === 'true');
        if (!$include_completed_days) {
            return;
        }

        $completed_days = PlanDay::countCompletedByPlanIdsAndUserId(
            $plans->getCollection()->pluck('id')->all(),
            $user->id
        );

        foreach ($plans as $plan) {
            $plan->completed_days = $completed_days[$plan->id] ?? 0;
        }
    }

    /**
     * Store a newly created plan in storage.
     *
     *  @OA\Post(
     *     path="/plans",
     *     tags={"Plans"},
     *     summary="Crete a plan",
     *     operationId="v4_internal_plans.store",
     *     security={{"api_token":{}}},
     *     @OA\RequestBody(required=true, description="Fields for User Plan Creation",
     *           @OA\MediaType(mediaType="application/json",
     *              @OA\Schema(
     *                  @OA\Property(property="name", ref="#/components/schemas/Plan/properties/name"),
     *                  @OA\Property(property="suggested_start_date", ref="#/components/schemas/Plan/properties/suggested_start_date"),
     *                  @OA\Property(property="days",type="integer")
     *              )
     *          )
     *     ),
     *     @OA\Response(response=200, ref="#/components/responses/plan")
     * )
     *
     * @return \Illuminate\Http\Response|array
     */
    public function store(Request $request)
    {

        // Validate Project / User Connection
        $user = $request->user();
        
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $name = checkParam('name', true);
        $days = intval(checkParam('days', true));
        $days = $days > $this->days_limit ? $this->days_limit : $days;
        $suggested_start_date = checkParam('suggested_start_date');

        $plan = Plan::create([
            'user_id'               => $user->id,
            'name'                  => $name,
            'featured'              => false,
            'suggested_start_date'  => $suggested_start_date ?? ''
        ]);

        for ($i = 0; $i < intval($days); $i++) {
            $data[] = [
                'plan_id' => $plan->id,
                'name' => 'plan_' . $plan->id,
                'user_id' => $user->id
            ];
        }
        Playlist::insert($data);
        $new_playlists = Playlist::select(['id'])
            ->where('name', 'plan_' . $plan->id)
            ->where('plan_id', $plan->id)
            ->where('user_id', $user->id)
            ->get()->pluck('id');
        $plan_days_data = $new_playlists->map(function ($item) use ($plan) {
            return [
                'plan_id'               => $plan->id,
                'playlist_id'           => $item,
            ];
        })->toArray();
        Playlist::whereIn('id', $new_playlists)->update(['name' => '', 'updated_at' => 'created_at']);
        PlanDay::insert($plan_days_data);

        UserPlan::create([
            'user_id'               => $user->id,
            'plan_id'               => $plan->id
        ]);

        $plan = $this->getPlan($plan->id, $user);
        return $this->reply($plan);
    }

    /**
     *
     * @OA\Get(
     *     path="/plans/{plan_id}",
     *     tags={"Plans"},
     *     summary="A user's plan",
     *     operationId="v4_internal_plans.show",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(
     *          name="plan_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/User/properties/id"),
     *          description="The plan id"
     *     ),
     *     @OA\Parameter(
     *          name="show_details",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Give full details of the plan"
     *     ),
     *     @OA\Parameter(
     *          name="show_text",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Enable the full details of the plan and retrieve the text of the playlists items"
     *     ),
     *     @OA\Parameter(
     *          name="include_user_bible",
     *          in="query",
     *          @OA\Schema(type="boolean", default=false),
     *          description="When set to `true` (case-insensitive), the response includes `user_bible`, the Bible the token user chose for this plan (null when there is no token, the user has not saved or started the plan, or none was chosen). Any other value, including `1` or a list, leaves the key off."
     *     ),
     *     @OA\Parameter(
     *          name="include_completed_at",
     *          in="query",
     *          @OA\Schema(type="boolean", default=false),
     *          description="When set to `true` (case-insensitive), every item in `days` includes `completed_at`: when the token user marked that day complete, as a UTC timestamp in the format of the plan's `created_at` (for example `2026-10-13T19:37:53.000000Z`), or null when the day is not completed or there is no token. It is the first completion since the day was last un-completed or the plan was restarted, stopped or reset without `save_progress=true` (a reset that saves progress keeps it); completing the day again keeps it. Any other value, including `1` or a list, leaves the key off."
     *     ),
     *     @OA\Response(response=200, ref="#/components/responses/plan")
     * )
     *
     * @param $plan_id
     *
     * @return mixed
     *
     *
     */
    public function show(Request $request, $plan_id)
    {
        $user = $request->user();

        // Validate Project / User Connection
        if (!empty($user) && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = $this->getPlan((int) $plan_id, $user);

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $show_details = checkBoolean('show_details');
        $show_text = checkBoolean('show_text');
        if ($show_text) {
            $show_details = $show_text;
        }

        if ($show_details) {
            $user_id = empty($user) ? 0 : $user->id;

            $this->plan_service->setPlaylistItemsForEachPlaylist($plan, $user_id);
            if ($show_text) {
                $this->plan_service->setVerseTextToEachPlaylistItem($plan);
            }
        } else {
            $this->plan_service->setFlagEmptyPlaylistForEachPlanDay($plan);
        }

        // Opt-in so the old Bible.is app, whose strict decoders may reject unknown keys, gets the
        // same payload as before. When on, the key is always present: null for no token, no
        // user_plans row, or no Bible chosen. Read here rather than in the shared
        // Plan::scopeWithUserById select, which would change the Start/Update/Store replies too.
        // Parsed by hand so it is strict whichever checkBoolean() a release carries (before #1062 it accepted any value
        // but "false"): only the word true (any case) or a JSON true turns it on, and an array is ignored, not a 500.
        $include_user_bible_raw = checkParam('include_user_bible');
        $include_user_bible = $include_user_bible_raw === true
            || (is_string($include_user_bible_raw) && strtolower($include_user_bible_raw) === 'true');
        $user_bible = null;
        if ($include_user_bible && !empty($user)) {
            $user_bible = UserPlan::where('plan_id', $plan->id)
                ->where('user_id', $user->id)
                ->value('user_bible');
        }

        // Opt-in like include_user_bible and parsed the same strict way. When on, every day gets completed_at: when the
        // token user marked it complete (UTC), or null (not completed, or no token). One query, only when on with a
        // token, kept out of the shared PlanDay::scopeWithCompletedDay select, whose days the Start, Update and Store
        // replies serialise directly.
        $include_completed_at_raw = checkParam('include_completed_at');
        $include_completed_at = $include_completed_at_raw === true
            || (is_string($include_completed_at_raw) && strtolower($include_completed_at_raw) === 'true');
        $completed_at_by_day = [];
        if ($include_completed_at && !empty($user)) {
            $completed_at_by_day = PlanDayComplete::getCompletedAtByPlanAndUserId($plan->id, $user->id);
        }

        return $this->reply(fractal(
            $plan,
            new PlanDayPlaylistItemsTransformer(
                [
                    'v' => $this->v,
                    'key' => $this->key,
                    'show_details' => $show_details,
                    'include_completed_at' => $include_completed_at,
                    'completed_at_by_day' => $completed_at_by_day,
                    'include_user_bible' => $include_user_bible,
                    'user_bible' => $user_bible
                ]
            ),
            new ArraySerializer()
        ));
    }

    /**
     * Update the specified plan.
     *
     *  @OA\Put(
     *     path="/plans/{plan_id}",
     *     tags={"Plans"},
     *     summary="Update a plan",
     *     operationId="v4_internal_plans.update",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Parameter(name="days", in="query",@OA\Schema(type="string"), description="Comma-separated ids of the days to be sorted or deleted"),
     *     @OA\Parameter(name="delete_days", in="query",@OA\Schema(type="boolean"), description="Will delete all days"),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property(property="name", ref="#/components/schemas/Plan/properties/name"),
     *              @OA\Property(property="suggested_start_date", ref="#/components/schemas/Plan/properties/suggested_start_date")
     *          )
     *     )),
     *     @OA\Response(response=200, ref="#/components/responses/plan")
     * )
     *
     * @param  int $plan_id
     * @param  string $days
     *
     * @return array|\Illuminate\Http\Response
     */
    public function update(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('user_id', $user->id)->where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $update_values = [];

        $name = checkParam('name');
        if ($name) {
            $update_values['name'] = $name;
        }

        $suggested_start_date = checkParam('suggested_start_date');
        if ($suggested_start_date) {
            $update_values['suggested_start_date'] = $suggested_start_date;
        }

        $plan->update($update_values);

        $days = checkParam('days');
        $delete_days = checkBoolean('delete_days');

        if ($days || $delete_days) {
            $days_ids = [];
            if (!$delete_days) {
                $days_ids = explode(',', $days);
                PlanDay::setNewOrder($days_ids);
            }
            $deleted_days = PlanDay::whereNotIn('id', $days_ids)->where('plan_id', $plan->id);
            $playlists_ids = $deleted_days->pluck('playlist_id')->unique();
            $playlists = Playlist::whereIn('id', $playlists_ids);
            $deleted_days->delete();
            $playlists->delete();
        }

        $plan = $this->getPlan($plan->id, $user);

        return $this->reply($plan);
    }

    /**
     * Remove the specified plan.
     *
     *  @OA\Delete(
     *     path="/plans/{plan_id}",
     *     tags={"Plans"},
     *     summary="Delete a plan",
     *     operationId="v4_internal_plans.destroy",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(type="string"))
     *     )
     * )
     *
     * @param  int $plan_id
     *
     * @return array|\Illuminate\Http\Response
     */
    public function destroy(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('user_id', $user->id)->where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $playlists_ids = $plan->days()->pluck('playlist_id')->unique();
        $playlists = Playlist::whereIn('id', $playlists_ids);
        $playlists->delete();
        $user_plans = UserPlan::where('plan_id', $plan_id);
        $user_plans->delete();
        $plan->days()->delete();
        $plan->delete();

        return $this->reply('Plan Deleted');
    }

    private function validatePlan()
    {
        $validator = Validator::make(request()->all(), [
            'name'              => 'required|string'
        ]);
        if ($validator->fails()) {
            return ['errors' => $validator->errors()];
        }
        return true;
    }

    /**
     * Start the specified plan.
     *
     *  @OA\Post(
     *     path="/plans/{plan_id}/start",
     *     tags={"Plans"},
     *     summary="Start a plan",
     *     operationId="v4_internal_plans.start",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property(property="start_date", ref="#/components/schemas/UserPlan/properties/start_date"),
     *              @OA\Property(property="user_bible", ref="#/components/schemas/v4_plan_user_bible/properties/user_bible")
     *          )
     *     )),
     *     @OA\Response(response=200, ref="#/components/responses/plan")
     * )
     *
     * @param  int $plan_id
     *
     * @return array|\Illuminate\Http\Response
     */
    public function start(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('id', (int) $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $start_date = checkParam('start_date', true);

        // Optional: validated before anything is written, so a bad value (422) never leaves a
        // started plan behind. Not sent means today's behaviour and any stored choice is kept.
        $user_bible = $this->resolveUserBible(false);

        $user_plan = UserPlan::where('plan_id', $plan_id)->where('user_id', $user->id)->first();

        if (!$user_plan) {
            $user_plan = UserPlan::create([
                'user_id'               => $user->id,
                'plan_id'               => $plan->id
            ]);
        }

        if ($user_bible !== null) {
            $user_plan->user_bible = $user_bible;
        }
        $user_plan->start_date = $start_date;
        $user_plan->save();


        $plan = $this->getPlan($plan_id, $user);

        // Echoed only when sent, so the reply to the old app is unchanged.
        if ($user_bible !== null && $plan) {
            $plan->user_bible = $user_bible;
        }

        return $this->reply($plan);
    }

    /**
     * Save a plan for later: add it to the user's plans with no start date, without starting it.
     *
     *  @OA\Post(
     *     path="/plans/{plan_id}/save",
     *     tags={"Plans"},
     *     summary="Save a plan for later",
     *     description="Gives the user a record for the plan with no start date and 0% completed. If the user already has the plan (saved, started or completed), nothing changes except user_bible when sent; the call still succeeds. Draft plans cannot be saved (404). Start the plan later with Start; remove it with DELETE /plans/{plan_id}/save (Unsave).",
     *     operationId="v4_internal_plans.save",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\RequestBody(required=false, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              @OA\Property(property="user_bible", ref="#/components/schemas/v4_plan_user_bible/properties/user_bible")
     *          )
     *     )),
     *     @OA\Response(response=200, ref="#/components/responses/plan")
     * )
     *
     * @param  int $plan_id
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function save(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('id', (int) $plan_id)->first();

        // A draft is hidden from both plan lists, its creator already has a row, and DeleteDraftPlaylistsPlans
        // deletes it after a day, so a saved draft could never be used. Answered like a plan that does not exist.
        if (!$plan || $plan->draft) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        // Optional, as on Start: validated before anything is written, so a bad value (422) saves nothing.
        $user_bible = $this->resolveUserBible(false);

        // The transaction must be on dbp_users, where every plan table lives (\DB::transaction() would open it on
        // the default content connection). (user_id, plan_id) is not unique, so two Saves at once (a double tap)
        // must not both insert. They queue on a lock on the plan's own row: locking the user_plans row that does not
        // exist yet would take a gap lock on the shared (user_id, plan_id) index, which also blocks or deadlocks
        // other users' first Saves and Starts. The user_plans read below is a plain read taken after the lock, so it
        // sees a row the previous Save committed, under REPEATABLE READ or READ COMMITTED alike.
        $saved = DB::connection('dbp_users')->transaction(function () use ($plan, $user, $user_bible) {
            $locked_plan = Plan::where('id', $plan->id)->lockForUpdate()->first();

            // Checked again under the lock: the creator may have deleted the plan or made it a draft since the
            // check above.
            if (!$locked_plan || $locked_plan->draft) {
                return false;
            }

            $user_plan = UserPlan::where('plan_id', $plan->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$user_plan) {
                // percentage_completed has no column default; Start and translate rely on the non-strict
                // connection inserting 0, which would break if strict mode were turned on.
                $user_plan = new UserPlan([
                    'user_id'              => $user->id,
                    'plan_id'              => $plan->id,
                    'percentage_completed' => 0,
                ]);
            }

            // An existing row keeps its start date, progress and updated_at (a repeat Save is not an interaction,
            // so the plan does not move up the last_interaction sort). Only a sent user_bible changes it, and
            // Eloquent writes nothing when the value is already stored. Not fillable, so set as a property.
            if ($user_bible !== null) {
                $user_plan->user_bible = $user_bible;
            }
            $user_plan->save();

            return true;
        }, 3);

        if (!$saved) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $plan = $this->getPlan($plan->id, $user);

        // Echoed only when sent, as Start does.
        if ($user_bible !== null && $plan) {
            $plan->user_bible = $user_bible;
        }

        return $this->reply($plan);
    }

    /**
     * Unsave a plan: remove a saved plan (no start date, nothing completed) from the user's plans. A started plan, or one
     * with any progress, is refused, so an out-of-date screen cannot wipe progress the way Stop would.
     *
     *  @OA\Delete(
     *     path="/plans/{plan_id}/save",
     *     tags={"Plans"},
     *     summary="Unsave a plan",
     *     description="Removes the plan from the user's plans when it has no start date and nothing completed in it. Repeatable: a plan that is not in the user's plans returns 200 and nothing changes. A started plan, or one with any completed day or item (possible through the API even without a start date; such a plan still reads user_status saved), returns 409 and nothing changes (end it with Stop). A plan the user created returns 409 (delete the plan instead): the user's own unstarted plans also read user_status saved, so offer Unsave only when the plan's user.id is not the signed-in user. An unknown plan returns 404.",
     *     operationId="v4_internal_plans.unsave",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Response(response=200, ref="#/components/responses/plan"),
     *     @OA\Response(response=409, description="The plan has been started, has progress, or the user created it; nothing changed")
     * )
     *
     * @param  int $plan_id
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function unsave(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('id', (int) $plan_id)->first();

        // Unlike save(), a draft is not refused: its creator can turn a published plan back into a draft after others
        // saved it, and Plan Details and library search still show it to them, so they must be able to unsave it.
        // Removing a record is safe for a draft; only creating one is not.
        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        // The creator's record is how their own plan stays in My Plans (stop() keeps it for the same reason), so it is
        // never removed here. Compared as ints so a string attribute cannot make every user look like a non-creator.
        if ((int) $plan->user_id === (int) $user->id) {
            return $this->setStatusCode(409)->replyWithError('This plan was created by the user. Delete the plan instead of unsaving it.');
        }

        // Same transaction and plan lock as save(), so a Save and an Unsave of the same plan never interleave. The
        // user's own records are then read with a lock (a current read), so a record started a moment ago is seen and
        // refused rather than deleted. The progress check below is this transaction's first plain read, so it sees
        // everything committed up to that moment.
        $outcome = DB::connection('dbp_users')->transaction(function () use ($plan, $user) {
            $locked_plan = Plan::where('id', $plan->id)->lockForUpdate()->first();

            if (!$locked_plan) {
                return 'not_found';
            }

            $user_plans = UserPlan::where('plan_id', $plan->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get();

            if ($user_plans->isEmpty()) {
                return 'not_saved';
            }

            // (user_id, plan_id) is not unique: if any of the user's records has a start date, the plan is started.
            if ($user_plans->contains(function ($user_plan) {
                return $user_plan->start_date !== null;
            })) {
                return 'started';
            }

            // Completing a day or an item needs only the record, not a start date, so a plan with no start date can still
            // hold progress (only through direct API calls; the apps complete days of started plans). Unsave never
            // deletes progress: like a started plan, such a plan is refused and nothing is removed.
            $has_completed_days = DB::connection('dbp_users')->table('plan_days_completed as pdc')
                ->join('plan_days as pd', 'pd.id', '=', 'pdc.plan_day_id')
                ->where('pd.plan_id', $plan->id)
                ->where('pdc.user_id', $user->id)
                ->exists();
            if ($has_completed_days || !empty(PlanDay::getPlaylistIdsByPlanAndUser($plan->id, $user->id))) {
                return 'has_progress';
            }

            UserPlan::where('plan_id', $plan->id)->where('user_id', $user->id)->delete();

            return 'unsaved';
        }, 3);

        if ($outcome === 'not_found') {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        if ($outcome === 'started') {
            return $this->setStatusCode(409)->replyWithError('This plan has been started. Stop it instead of unsaving it.');
        }

        if ($outcome === 'has_progress') {
            return $this->setStatusCode(409)->replyWithError('This plan has progress. Stop it instead of unsaving it.');
        }

        // Unsaved, or it was not in the user's plans: either way it is not there now.
        return $this->reply($this->getPlan($plan->id, $user));
    }

    /**
     * Store the newly created plan days.
     *
     *  @OA\Post(
     *     path="/plans/{plan_id}/day",
     *     tags={"Plans"},
     *     summary="Create plan days",
     *     operationId="v4_internal_plans_days.store",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Parameter(name="days", in="query", required=true, @OA\Schema(type="integer"), description="Number of days to add to the plan"),
     *     @OA\Parameter(name="add_to_end", in="query", required=false, @OA\Schema(type="boolean"), description="If new days to add should be added to end of list of days"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_internal_plans_days"))
     *     )
     * )
     *
     * @OA\Schema (
     *   type="array",
     *   schema="v4_internal_plans_days",
     *   title="User created plan days",
     *   description="The v4 plan days creation response.",
     *   @OA\Items(ref="#/components/schemas/PlanDay")
     * )
     * @return mixed
     */
    public function storeDay(Request $request, $plan_id)
    {
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);
        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('user_id', $user->id)->where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $days = intval(checkParam('days', true));
        $current_days_size = sizeof($plan->days);
        $total_days = $current_days_size + $days;
        $days = $total_days > $this->days_limit ? $this->days_limit - $current_days_size : $days;


        $created_plan_days = [];

        $data = [];
        for ($i = 0; $i < intval($days); $i++) {
            $data[] = [
                'plan_id' => $plan->id,
                'name' => 'plan_' . $plan->id,
                'user_id' => $user->id
            ];
        }
        Playlist::insert($data);
        $new_playlists = Playlist::select(['id'])
            ->where('name', 'plan_' . $plan->id)
            ->where('plan_id', $plan->id)
            ->where('user_id', $user->id)
            ->get()->pluck('id');

        $add_to_end = checkBoolean('add_to_end');

        $plan_days_data = $new_playlists->map(function ($item, $index) use ($plan, $add_to_end, $current_days_size) {
            return [
                'plan_id'               => $plan->id,
                'playlist_id'           => $item,
                'order_column' => $add_to_end ? $current_days_size + ($index + 1) : 0,
            ];
        })->toArray();
        Playlist::whereIn('id', $new_playlists)->update(['name' => '', 'updated_at' => 'created_at']);
        PlanDay::insert($plan_days_data);


        $created_plan_days = PlanDay::where('plan_id', $plan->id)->whereIn('playlist_id', $new_playlists)->get();

        return $this->reply($created_plan_days);
    }

    /**
     * Complete a plan day.
     *
     *  @OA\Post(
     *     path="/plans/day/{day_id}/complete",
     *     tags={"Plans"},
     *     summary="Complete a plan day",
     *     operationId="v4_internal_plans_days.complete",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="day_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/PlanDay/properties/id")),
     *     @OA\Parameter(name="complete", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_complete_day"))
     *     )
     * )
     *
     * @OA\Schema (
     *   schema="v4_complete_day",
     *   description="The v4 plan day complete response",
     *   @OA\Property(property="message", type="string"),
     *   @OA\Property(property="percentage_completed", ref="#/components/schemas/UserPlan/properties/percentage_completed")
     * )
     * @return mixed
     */
    public function completeDay(Request $request, $day_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this
                ->setStatusCode(SymfonyResponse::HTTP_UNAUTHORIZED)
                ->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan_day = PlanDay::where('id', $day_id)->first();

        if (!$plan_day) {
            return $this
                ->setStatusCode(SymfonyResponse::HTTP_NOT_FOUND)
                ->replyWithError('Plan Day Not Found');
        }

        $user_plan = UserPlan::getByPlanIdAndUserId($plan_day->plan_id, $user->id);

        if (!$user_plan) {
            return $this
                ->setStatusCode(SymfonyResponse::HTTP_NOT_FOUND)
                ->replyWithError('User Plan Not Found');
        }

        // This allows clients to mark a day complete without needing to send a value
        $complete = $request->exists('complete') ? checkBoolean('complete') : true;

        $result = null;
        try {
            \DB::transaction(function () use ($complete, $plan_day, $user) {
                if ($complete) {
                    $plan_day->complete($user->id);
                } else {
                    $plan_day->unComplete($user->id);
                }

                return $plan_day;
            });
        } catch (QueryException $e) {
            // Catch only the error code for a duplicate entry
            if ($e->getCode() == MySQLErrorCode::DUPLICATE_ENTRY) {
                \Log::info(
                    "Exception to complete Plan Day [user: {$user->id} plan ID: {$user_plan->id} plan day ID: {$day_id}]"
                );
            }  else {
                throw $e;
            }
        }

        $result = $complete ? 'completed' : 'not completed';

        $user_plan->calculatePercentageCompleted()->save();

        return $this->reply([
            'percentage_completed' => (int) $user_plan->percentage_completed,
            'message' => 'Plan Day ' . $result
        ]);
    }

    /**
     * Delete days from a plan.
     *
     *  @OA\Delete(
     *     path="/plans/{plan_id}/day",
     *     tags={"Plans"},
     *     summary="Delete days from a plan",
     *     description="",
     *     operationId="v4_internal_plans_days.delete'",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(type="string"))
     *     )
     * )
     *
     * @param  int $plan_id
     *
     */

    public function deleteDays(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(SymfonyResponse::HTTP_UNAUTHORIZED)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(SymfonyResponse::HTTP_NOT_FOUND)->replyWithError('Plan Not Found');
        }

        $user_plan = UserPlan::where('plan_id', $plan->id)->where('user_id', $user->id)->first();

        if (!$user_plan) {
            return $this->setStatusCode(SymfonyResponse::HTTP_NOT_FOUND)->replyWithError('User Plan Not Found');
        }
        
        $days = checkParam('days', true);
        $days_ids = explode(',', $days);

        if(count($days_ids) > 20){
            return $this->setStatusCode(SymfonyResponse::HTTP_BAD_REQUEST)->replyWithError('Too many days provided. Maximum is 20.');
        }

        if (!PlanDay::removePlanDaysByPlanId($plan->id, $days_ids)) {
            return $this->setStatusCode(SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR)->replyWithError("Plan Days can't be deleted");
        }

        return $this->reply('Plan days were succesfully deleted');
    }

    /**
     * Reset the specified plan.
     *
     * @OA\Post(
     *     path="/plans/{plan_id}/reset",
     *     tags={"Plans"},
     *     summary="Reset a plan",
     *     description="",
     *     operationId="v4_internal_plans.reset",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\RequestBody(@OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              required={"start_date"},
     *              @OA\Property(property="start_date", type="string", ref="#/components/schemas/UserPlan/properties/start_date")
     *          )
     *     )),
     *     @OA\Parameter(name="save_progress", in="query"),
     *     @OA\Response(response=200, ref="#/components/responses/plan")
     * )
     *
     * @param  int $plan_id
     *
     * @return array|\Illuminate\Http\Response
     */
    public function reset(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $user_plan = UserPlan::where('plan_id', $plan->id)->where('user_id', $user->id)->first();

        if (!$user_plan) {
            return $this->setStatusCode(404)->replyWithError('User Plan Not Found');
        }

        $start_date = checkParam('start_date', true);
        $save_progress = checkParam('save_progress', false) ?? false;
        $save_progress = filter_var($save_progress, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $plan = \DB::transaction(function () use ($user, $plan, $user_plan, $save_progress, $start_date) {
            $user_plan->reset($start_date, $save_progress, $user->id)->save();
            return fractal(
                $plan,
                new PlanTransformer(
                    [
                        'user' => $user,
                        'user_plan' => $user_plan,
                        'days' => PlanDay::getWithDaysById($plan->id, $user->id)
                    ]
                ),
                new ArraySerializer()
            );
        });

        return $this->reply($plan);
    }

    /**
     * Restart a plan the user follows: clear their progress, set a new start date and, when sent, the Bible they
     * chose for the new attempt, all in one transaction on the users database.
     *
     *  @OA\Post(
     *     path="/plans/{plan_id}/restart",
     *     tags={"Plans"},
     *     summary="Restart a plan",
     *     description="The user must already follow the plan. Deletes the user's completed items and days for the plan, sets percentage_completed to 0, sets start_date and, when sent, user_bible (not sent keeps the stored Bible). Never removes the plan from the user's plans. Either everything is saved or nothing is.",
     *     operationId="v4_internal_plans.restart",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              required={"start_date"},
     *              @OA\Property(property="start_date", type="string", format="date", example="2026-10-10", description="YYYY-MM-DD"),
     *              @OA\Property(property="user_bible", ref="#/components/schemas/v4_plan_user_bible/properties/user_bible")
     *          )
     *     )),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_plan_restart"))
     *     )
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_plan_restart",
     *   title="Restart plan response",
     *   @OA\Property(property="start_date", type="string", format="date", example="2026-10-10"),
     *   @OA\Property(property="percentage_completed", ref="#/components/schemas/UserPlan/properties/percentage_completed"),
     *   @OA\Property(
     *      property="user_bible",
     *      ref="#/components/schemas/v4_plan_user_bible/properties/user_bible",
     *      description="The Bible saved for the plan after the restart: the one sent, or the stored one when none was sent (null if none)"
     *   ),
     *   @OA\Property(property="message", type="string", example="Plan restarted")
     * )
     *
     * @param  int $plan_id
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function restart(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        // Restart never creates the row: a plan the user does not follow is started with Start.
        $user_plan_exists = UserPlan::where('plan_id', $plan->id)->where('user_id', $user->id)->exists();

        if (!$user_plan_exists) {
            return $this->setStatusCode(404)->replyWithError('User Plan Not Found');
        }

        // Every input is validated before anything is written, so a 422 leaves the plan exactly as it was.
        try {
            $start_date = self::normalizeRestartStartDate(checkParam('start_date', true));
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $user_bible = $this->resolveUserBible(false);

        // The transaction must be on dbp_users, where every plan table lives. \DB::transaction() (as in reset())
        // would open it on the default content connection, a separate database server, and roll none of these
        // writes back.
        // The row is re-read under a lock so a Stop arriving between the check above and this point cannot
        // delete it unnoticed; Restart is repeatable, so a deadlock is safely retried (3 attempts).
        $user_plan = DB::connection('dbp_users')->transaction(function () use ($plan, $user, $start_date, $user_bible) {
            $user_plan = UserPlan::where('plan_id', $plan->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$user_plan) {
                return null;
            }

            // Deletes the user's completed items and days for the plan, zeroes the percentage, sets start_date.
            $user_plan->reset($start_date, false, $user->id);
            if ($user_bible !== null) {
                $user_plan->user_bible = $user_bible;
            }
            // A restart is an interaction even when no column changes (a repeat call), and the completions it
            // just deleted were what kept the plan near the top of the last_interaction sort.
            $user_plan->updated_at = $user_plan->freshTimestamp();
            $user_plan->save();

            return $user_plan;
        }, 3);

        if (!$user_plan) {
            return $this->setStatusCode(404)->replyWithError('User Plan Not Found');
        }

        // Built from the in-memory row: refresh()/fresh() do not work with UserPlan's composite key.
        return $this->reply([
            'start_date' => $start_date,
            'percentage_completed' => (int) $user_plan->percentage_completed,
            'user_bible' => $user_plan->user_bible,
            'message' => 'Plan restarted'
        ]);
    }

    /**
     * The DB-free start_date check for restart(), kept static so it can be unit tested.
     *
     * Stricter than Start and Reset, which store any string: Restart deletes progress, so a value the database
     * would silently turn into 0000-00-00 is rejected before anything is written.
     *
     * @param mixed $value what checkParam('start_date', true) returned
     *
     * @return string the date, YYYY-MM-DD
     * @throws \InvalidArgumentException with the 422 message when it is not a real date in that format
     */
    public static function normalizeRestartStartDate($value) : string
    {
        $message = 'start_date must be a date in YYYY-MM-DD format.';

        if (!is_string($value)) {
            throw new \InvalidArgumentException($message);
        }

        // Header values arrive untrimmed (body and query values are trimmed by middleware).
        $value = trim($value);

        // The round trip rejects dates that do not exist (2026-02-30 would otherwise roll over to March)
        // and loose forms PHP accepts for the format, such as 2026-1-5. Years before 1000 are outside MySQL's
        // DATE range, which the non-strict connection would store as-is or as zeros.
        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value || (int) $date->format('Y') < 1000) {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * Stop the specified plan.
     *
     *  @OA\Delete(
     *     path="/plans/{plan_id}/stop",
     *     tags={"Plans"},
     *     summary="Stop a plan",
     *     description="",
     *     operationId="v4_internal_plans.stop",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(type="string"))
     *     )
     * )
     *
     * @param  int $plan_id
     *
     * @return array|\Illuminate\Http\Response
     */
    public function stop(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $user_plan = UserPlan::where('plan_id', $plan->id)->where('user_id', $user->id)->first();

        if (!$user_plan) {
            return $this->setStatusCode(404)->replyWithError('User Plan Not Found');
        }

        $user_plan->reset();
        $user_plan->save();
        if ($user->id !== $plan->user_id) {
            $user_plan->delete();
        }

        return $this->reply(fractal(
            $plan,
            new PlanBasicTransformer(
                [
                    'v' => $this->v,
                    'key' => $this->key,
                    'user' => $user,
                ]
            ),
            new ArraySerializer()
        ));
    }

    /**
     * Set the Bible the user chose for a plan they follow, without copying the plan (unlike translate).
     *
     *  @OA\Put(
     *     path="/plans/{plan_id}/bible",
     *     tags={"Plans"},
     *     summary="Set the user's Bible for a plan",
     *     description="The user must already have saved or started the plan. Sending the value already stored changes nothing.",
     *     operationId="v4_internal_plans.bible_update",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="application/json",
     *          @OA\Schema(
     *              required={"user_bible"},
     *              @OA\Property(property="user_bible", ref="#/components/schemas/v4_plan_user_bible/properties/user_bible")
     *          )
     *     )),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_plan_user_bible"))
     *     )
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_plan_user_bible",
     *   title="The user's Bible for a plan",
     *   @OA\Property(
     *      property="user_bible",
     *      type="string",
     *      maxLength=12,
     *      nullable=true,
     *      example="ENGESV",
     *      description="A Bible id (not a fileset id). Must match an existing Bible; it is stored and returned in the Bible's own case. Not checked against the plan's passages or the key's access groups."
     *   ),
     *   @OA\Property(property="message", type="string", example="Plan Bible updated")
     * )
     *
     * @param  int $plan_id
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function updateBible(Request $request, $plan_id)
    {
        $user_plan = $this->findUserPlanForBible($request, $plan_id);
        if (!$user_plan instanceof UserPlan) {
            return $user_plan;
        }

        $user_plan->user_bible = $this->resolveUserBible(true);
        // Eloquent issues no UPDATE when the value is unchanged, so updated_at (and the
        // last_interaction sort) only moves when the choice really changes.
        $user_plan->save();

        return $this->reply(['user_bible' => $user_plan->user_bible, 'message' => 'Plan Bible updated']);
    }

    /**
     * Clear the Bible the user chose for a plan. Repeatable: clearing an empty choice returns the same reply.
     *
     *  @OA\Delete(
     *     path="/plans/{plan_id}/bible",
     *     tags={"Plans"},
     *     summary="Clear the user's Bible for a plan",
     *     operationId="v4_internal_plans.bible_destroy",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_plan_user_bible"))
     *     )
     * )
     *
     * @param  int $plan_id
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function destroyBible(Request $request, $plan_id)
    {
        $user_plan = $this->findUserPlanForBible($request, $plan_id);
        if (!$user_plan instanceof UserPlan) {
            return $user_plan;
        }

        $user_plan->user_bible = null;
        $user_plan->save();

        return $this->reply(['user_bible' => null, 'message' => 'Plan Bible removed']);
    }

    /**
     * The checks shared by updateBible and destroyBible, in the same order and with the same
     * errors as reset and stop: project membership (401), plan (404), the user's row (404).
     *
     * @return UserPlan|\Illuminate\Http\JsonResponse the user's row, or the error response
     */
    private function findUserPlanForBible(Request $request, $plan_id)
    {
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $user_plan = UserPlan::where('plan_id', $plan->id)->where('user_id', $user->id)->first();

        if (!$user_plan) {
            return $this->setStatusCode(404)->replyWithError('User Plan Not Found');
        }

        return $user_plan;
    }

    /**
     * Read and validate the `user_bible` input (header, then body or query string, via checkParam).
     * Aborts 422 in the same shape Start already uses for a missing start_date, before any write.
     *
     * @param bool $required true for PUT /plans/{id}/bible; false for Start, where it is optional
     *
     * @return string|null the Bible's id as stored in `bibles` (canonical case), or null when not
     *                     sent and not required
     */
    private function resolveUserBible(bool $required) : ?string
    {
        try {
            $user_bible = self::normalizeUserBible(checkParam('user_bible'));
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        if ($user_bible === null) {
            if ($required) {
                abort(
                    422,
                    "You need to provide the missing parameter 'user_bible'. Please append it to the url or the request Header."
                );
            }
            return null;
        }

        // bibles.id is unique; the case-insensitive collation lets `engesv` match, and value('id')
        // returns the stored spelling so that is what gets saved.
        $bible_id = Bible::whereId($user_bible)->value('id');
        if ($bible_id === null) {
            abort(422, 'user_bible does not match any Bible.');
        }

        return $bible_id;
    }

    /**
     * The DB-free part of the user_bible check, kept static so it can be unit tested.
     *
     * checkParam() has already dropped null, "", "0", 0, false and empty arrays, and body/query
     * values arrive trimmed (TrimStrings, ConvertEmptyStringsToNull). Header values do not, so
     * they are trimmed here, and a value that is blank after trimming counts as not sent.
     *
     * @param mixed $value what checkParam('user_bible') returned
     *
     * @return string|null the trimmed id, or null when not sent
     * @throws \InvalidArgumentException with the 422 message for a non-string or too-long value
     */
    public static function normalizeUserBible($value) : ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('user_bible must be a string.');
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // bibles.id and user_plans.user_bible are varchar(12); rejecting longer values here
        // avoids a DB query that could never match.
        if (mb_strlen($value) > 12) {
            throw new \InvalidArgumentException('user_bible may not be longer than 12 characters.');
        }

        return $value;
    }

    /**
     *  @OA\Post(
     *     path="/plans/{plan_id}/draft",
     *     tags={"Plans"},
     *     summary="Change draft status in a plan.",
     *     operationId="v4_internal_plans.draft",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(name="plan_id", in="path", required=true, @OA\Schema(ref="#/components/schemas/Plan/properties/id")),
     *     @OA\Parameter(name="draft", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(type="string"))
     *     )
     * )
     */
    public function draft(Request $request, $plan_id)
    {
        // Validate Project / User Connection
        $user = $request->user();
        $user_is_member = $this->compareProjects($user->id, $this->key);

        if (!$user_is_member) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $plan = Plan::where('user_id', $user->id)->where('id', $plan_id)->first();

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $draft = checkBoolean('draft');
        $playlist_ids = DB::connection('dbp_users')->select('select playlist_id from plan_days where plan_id = ?', [$plan_id]);
        DB::connection('dbp_users')
            ->table('user_playlists')
            ->whereIn('id', Arr::pluck($playlist_ids, 'playlist_id'))
            ->update(['draft' => $draft]);

        $plan->draft = $draft;

        $plan->save();

        return $this->reply('Plan draft status changed');
    }
    /**
     *  @OA\Schema (
     *   type="object",
     *   schema="v4_plan",
     *   @OA\Property(property="id", ref="#/components/schemas/Plan/properties/id"),
     *   @OA\Property(property="name", ref="#/components/schemas/Plan/properties/name"),
     *   @OA\Property(property="featured", ref="#/components/schemas/Plan/properties/featured"),
     *   @OA\Property(property="thumbnail", ref="#/components/schemas/Plan/properties/thumbnail"),
     *   @OA\Property(property="suggested_start_date", ref="#/components/schemas/Plan/properties/suggested_start_date"),
     *   @OA\Property(property="created_at", ref="#/components/schemas/Plan/properties/created_at"),
     *   @OA\Property(property="updated_at", ref="#/components/schemas/Plan/properties/updated_at"),
     *   @OA\Property(property="start_date", ref="#/components/schemas/UserPlan/properties/start_date"),
     *   @OA\Property(property="percentage_completed", ref="#/components/schemas/UserPlan/properties/percentage_completed"),
     *   @OA\Property(property="user", ref="#/components/schemas/v4_plan_index_user"),
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_plan_index_user",
     *   description="The user who created the plan",
     *   @OA\Property(property="id", type="integer"),
     *   @OA\Property(property="name", type="string")
     * )
     *
     * @OA\Schema (
     *   type="object",
     *   schema="v4_plan_detail",
     *   allOf={
     *      @OA\Schema(ref="#/components/schemas/v4_plan"),
     *   },
     *   @OA\Property(property="days",type="array",@OA\Items(ref="#/components/schemas/PlanDay")),
     *   @OA\Property(
     *      property="user_bible",
     *      ref="#/components/schemas/v4_plan_user_bible/properties/user_bible",
     *      description="Only on GET /plans/{plan_id}?include_user_bible=true, and on Start or Save when user_bible was sent"
     *   )
     * )
     *
     *
     * @OA\Response(
     *   response="plan",
     *   description="Plan Object",
     *   @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/v4_plan_detail"))
     * )
     */

    private function getPlan($plan_id, $user)
    {
        $user_id = !empty($user) ? $user->id : null;
        return Plan::getWithDaysAndUserById($plan_id, $user_id);
    }

    /**
     *
     * @OA\Get(
     *     path="/plans/{plan_id}/translate",
     *     tags={"Plans"},
     *     summary="Translate a user's plan",
     *     operationId="v4_internal_plans.translate",
     *     security={{"api_token":{}}},
     *     @OA\Parameter(
     *          name="plan_id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Plan/properties/id"),
     *          description="The plan id"
     *     ),
     *     @OA\Parameter(
     *          name="bible_id",
     *          in="query",
     *          required=true,
     *          @OA\Schema(ref="#/components/schemas/Bible/properties/id"),
     *          description="The id of the bible that will be used to translate the plan"
     *     ),
     *     @OA\Parameter(
     *          name="show_details",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Give full details of the translated plan"
     *     ),
     *     @OA\Parameter(
     *          name="show_text",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Give full details about the text verse for each playlist item"
     *     ),
     *     @OA\Parameter(
     *          name="save_completed_items",
     *          in="query",
     *          @OA\Schema(type="boolean"),
     *          description="Save progress for the translated plan"
     *     ),
     *     @OA\Response(response=200, ref="#/components/schemas/v4_plan_translated_detail")
     * )
     *
     * @param $plan_id
     *
     * @return mixed
     *
     *
     */
    public function translate(Request $request, $plan_id, $user = null, $compare_projects = true, $draft = true)
    {
        $user = $user ? $user : $request->user();

        // Validate Project / User Connection
        if ($compare_projects && !empty($user) && !$this->compareProjects($user->id, $this->key)) {
            return $this->setStatusCode(401)->replyWithError(trans('api.projects_users_not_connected'));
        }

        $bible_id = checkParam('bible_id', true);
        $bible = cacheRemember('bible_translate', [$bible_id], now()->addDay(), function () use ($bible_id) {
            return Bible::whereId($bible_id)->first();
        });

        if (!$bible) {
            return $this->setStatusCode(404)->replyWithError('Bible Not Found');
        }

        $plan = $this->plan_service->getPlanById((int) $plan_id);

        if (!$plan) {
            return $this->setStatusCode(404)->replyWithError('Plan Not Found');
        }

        $user_id = empty($user) ? 0 : $user->id;
        $show_details = checkBoolean('show_details');
        $show_text = checkBoolean('show_text');
        $save_completed_items = checkBoolean('save_completed_items');

        if ($show_text) {
            $show_details = $show_text;
        }

        $plan = $this->plan_service->translate($plan_id, $bible, $user_id, $draft, $save_completed_items, true, true);

        if ($show_details === true) {
            $this->plan_service->setPlaylistItemsForEachPlaylist($plan, $user_id);
        }

        // If it is true, it will create the verse_text property for each play list item that belong to a day
        if ($show_text === true) {
            $this->plan_service->setVerseTextToEachPlaylistItem($plan);
        }

        return $this->reply(fractal(
            $plan,
            new PlanTranslateTransformer(
                [
                    'user' => $user,
                    'v' => $this->v,
                    'key' => $this->key,
                    'show_details' => $show_details
                ]
            ),
            new ArraySerializer()
        ));
    }
}
