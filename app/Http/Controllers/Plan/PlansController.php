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
     *     @OA\Parameter(ref="#/components/parameters/limit"),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(ref="#/components/parameters/sort_by"),
     *     @OA\Parameter(ref="#/components/parameters/sort_dir"),
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
     *   },
     *   @OA\Property(property="total_days", type="integer")
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

        if($featured && $sort_by === 'last_interaction') {
            return $this->setStatusCode(SymfonyResponse::HTTP_BAD_REQUEST)->replyWithError('Sort by last_interaction is not supported for featured plans');
        }

        $language_id = null;
        if ($iso !== null) {
            $language_id = cacheRemember('v4_language_id_from_iso', [$iso], now()->addDay(), function () use ($iso) {
                return optional(Language::where('iso', $iso)->select('id')->first())->id;
            });
        }

        return $this->reply($this->getPlans($featured, $limit, $sort_by, $sort_dir, $user, $language_id));
    }

    private function getPlans($featured, $limit, $sort_by, $sort_dir, $user, $language_id)
    {
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

        foreach ($plans as $plan) {
            $plan->total_days = sizeof($plan->days);
            unset($plan->days);
        }
        return $plans;
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
     *          description="When true, the response includes `user_bible`, the Bible the token user chose for this plan (null when there is no token, the user has not started the plan, or none was chosen)"
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
        $include_user_bible = checkBoolean('include_user_bible');
        $user_bible = null;
        if ($include_user_bible && !empty($user)) {
            $user_bible = UserPlan::where('plan_id', $plan->id)
                ->where('user_id', $user->id)
                ->value('user_bible');
        }

        return $this->reply(fractal(
            $plan,
            new PlanDayPlaylistItemsTransformer(
                [
                    'v' => $this->v,
                    'key' => $this->key,
                    'show_details' => $show_details,
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
     *     description="The user must already have started the plan. Sending the value already stored changes nothing.",
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
     *      description="Only on GET /plans/{plan_id}?include_user_bible=true, and on Start when user_bible was sent"
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
