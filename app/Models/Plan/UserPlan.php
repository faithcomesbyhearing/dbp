<?php

namespace App\Models\Plan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\Traits\ModelBase;
use App\Models\Playlist\PlaylistItems;
use App\Models\Playlist\PlaylistItemsComplete;
use App\Models\Plan\PlanDayComplete;

/**
 * @OA\Schema (
 *     type="object",
 *     description="The User Plan data",
 *     title="UserPlan"
 * )
 */
class UserPlan extends Model
{
    use ModelBase;

    protected $connection = 'dbp_users';
    protected $primaryKey = ['user_id', 'plan_id'];
    public $incrementing = false;
    public $table         = 'user_plans';
    protected $fillable   = ['plan_id', 'user_id', 'start_date', 'percentage_completed'];
    protected $hidden     = ['plan_id', 'created_at', 'updated_at'];

    /**
     * Status of a user's relationship with a plan, exposed by the plans index when `user_status=true`.
     *
     * Resolved from playlist_items_completed, the same source calculatePercentageCompleted() uses,
     * instead of the stored percentage_completed column. That column is an integer and rounds on
     * write: one completed day of a 365-day plan is stored as 0 and 364 of 365 as 100, so it cannot
     * tell "truly finished" apart from "almost".
     *
     * The start date alone separates saved from started: a user_plans row with no start_date is
     * saved (the plan is in the user's plans but was never started), whatever is completed in it.
     * That row is left by creating a plan, by translating one, and by the creator stopping their own
     * plan (progress and start_date cleared, row kept). Clients only complete items of a started
     * plan; the API does not enforce that, so a row with no start date and completed items can
     * exist (old data, or translate copying progress) and still reads saved. The absence of a
     * user_plans row is what distinguishes a plan the user has no relationship with, and that is
     * reported as null by the caller rather than as a status value here.
     */
    const STATUS_SAVED       = 'saved';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';

    /**
     *
     * @OA\Property(
     *   title="start_date",
     *   type="string",
     *   format="date",
     *   description="The start date of the plan"
     * )
     *
     */
    protected $start_date;

    /**
     *
     * @OA\Property(
     *   title="percentage_completed",
     *   type="integer",
     *   description="The percentage completed of the plan"
     * )
     *
     */
    protected $percentage_completed;

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     *
     * @param mixed $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }
   
    public function calculatePercentageCompleted() : UserPlan
    {
        $completed_per_day = PlanDay::summaryItemsCompletedByPlanId($this->plan_id, $this->user_id)->get();

        $this->attributes['percentage_completed'] = $completed_per_day->sum('total_items')
            ? $completed_per_day->sum('total_items_completed') / $completed_per_day->sum('total_items') * 100
            : 0;
        return $this;
    }

    /**
     * Reset the user plan according the given start date. If the save progress flag is true the
     * plan progress will be removed.
     *
     * @param string $start_date
     * @param bool   $save_progress
     * @param int    $user_id
     *
     * @return Array
     */
    public function reset(string $start_date = null, bool $save_progress = false, ?int $user_id = null) : UserPlan
    {
        if ($save_progress === false) {
            if (is_null($user_id)) {
                $user = Auth::user();
                $user_id = $user->id;
            }

            self::removePlanDaysCompleteByPlanId($this->plan_id, $user_id);
            $this->attributes['percentage_completed'] = 0;
        }

        $this->attributes['start_date'] = $start_date;

        return $this;
    }

    /**
     * Remove the Plan Days Completed records attached to a Plan and an User
     *
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Array
     */
    public static function removePlanDaysCompleteByPlanId(int $plan_id, int $user_id) : void
    {
        \DB::transaction(function () use ($plan_id, $user_id) {
            $playlist_ids_by_plan = PlanDay::getPlaylistIdsByPlanAndUser($plan_id, $user_id);
            PlaylistItemsComplete::removeItemsByPlayListsAndUser($playlist_ids_by_plan, $user_id);
            PlanDayComplete::removeDaysByPlanAndUser($plan_id, $user_id);
        });
    }

    public static function getByPlaylistIdAndUserId(int $playlist_id, int $user_id) : ?UserPlan
    {
        return UserPlan::join('plans', function ($join) use ($user_id) {
            $join->on('user_plans.plan_id', '=', 'plans.id')->where('user_plans.user_id', $user_id);
        })
            ->join('plan_days', function ($join) use ($playlist_id) {
                $join
                    ->on('plan_days.plan_id', '=', 'plans.id')
                    ->where('plan_days.playlist_id', $playlist_id);
            })
            ->select('user_plans.*')
            ->first();
    }

    public static function getByPlanIdAndUserId(int $plan_id, int $user_id) : ?UserPlan
    {
        return UserPlan::join('plans', function ($join) use ($user_id) {
            $join->on('user_plans.plan_id', '=', 'plans.id')->where('user_plans.user_id', $user_id);
        })->where('user_plans.plan_id', $plan_id)
            ->select('user_plans.*')
            ->first();
    }

    /**
     * Resolve the user's status for a plan from exact playlist item counts.
     *
     * Only reached for a plan the user has a user_plans row for. Checked in this order:
     *
     * saved:       no start date, whatever is completed (the start date alone decides started).
     * completed:   started, every item completed and the plan has at least one item.
     * in_progress: started and not completed, including nothing completed yet and a plan with no
     *              items. A single completed item of a 365-day plan is in_progress even though the
     *              stored percentage rounds to 0, and so is 364 of 365, which rounds to 100.
     *
     * @param int  $total_items
     * @param int  $total_items_completed
     * @param bool $started true when the user_plans row has a start_date
     *
     * @return string
     */
    public static function resolveStatus(int $total_items, int $total_items_completed, bool $started) : string
    {
        if (!$started) {
            return self::STATUS_SAVED;
        }

        // >= rather than === so a stray duplicate completion row can never hide a finished plan
        if ($total_items > 0 && $total_items_completed >= $total_items) {
            return self::STATUS_COMPLETED;
        }

        return self::STATUS_IN_PROGRESS;
    }

    /**
     * Get the user's status for each of the given plans in one query, keyed by plan id.
     *
     * Anchored on user_plans so only plans the user has a row for appear in the result; a plan id
     * missing from the returned array means the user has no relationship with it. Days and items
     * are LEFT JOINed so a plan with no items still resolves. Generalises
     * PlanDay::scopeSummaryItemsCompletedByPlanId() from one plan to a page of plans.
     *
     * user_plans has no unique key on (user_id, plan_id), so the start date is read with MAX: a
     * duplicated pair counts as started when any of its rows has a date.
     *
     * @param array $plan_ids
     * @param int   $user_id
     *
     * @return array [plan_id => status]
     */
    public static function getStatusesByPlanIdsAndUserId(array $plan_ids, int $user_id) : array
    {
        if (empty($plan_ids)) {
            return [];
        }

        $summaries = self::select([
                'user_plans.plan_id',
                \DB::raw('COUNT(playlist_items.id) AS total_items'),
                \DB::raw('COUNT(playlist_items_completed.playlist_item_id) AS total_items_completed'),
                // Not aliased start_date: inside this class $summary->start_date would read the
                // declared protected $start_date property (always null), not the selected column.
                \DB::raw('MAX(user_plans.start_date) AS max_start_date'),
            ])
            ->leftJoin('plan_days', 'plan_days.plan_id', '=', 'user_plans.plan_id')
            ->leftJoin('playlist_items', 'playlist_items.playlist_id', '=', 'plan_days.playlist_id')
            ->leftJoin('playlist_items_completed', function ($join) use ($user_id) {
                $join
                    ->on('playlist_items_completed.playlist_item_id', '=', 'playlist_items.id')
                    ->where('playlist_items_completed.user_id', $user_id);
            })
            ->where('user_plans.user_id', $user_id)
            ->whereIn('user_plans.plan_id', $plan_ids)
            ->groupBy('user_plans.plan_id')
            ->get();

        $statuses = [];
        foreach ($summaries as $summary) {
            $statuses[(int) $summary->plan_id] = self::resolveStatus(
                (int) $summary->total_items,
                (int) $summary->total_items_completed,
                !is_null($summary->max_start_date)
            );
        }

        return $statuses;
    }
}
