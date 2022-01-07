<?php

namespace App\Models\Plan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\Traits\ModelBase;
use App\Models\Playlist\PlaylistItems;
use App\Models\Playlist\PlaylistItemsComplete;

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
   
    public function calculatePercentageCompleted()
    {
        $completed_per_day = PlanDay::summaryItemsCompletedByPlanId($this->plan_id)->get();

        $this->completeDaysCurrentUserPlan();

        $this->attributes['percentage_completed'] = $completed_per_day->sum('total_items')
            ? $completed_per_day->sum('total_items_completed') / $completed_per_day->sum('total_items') * 100
            : 0;
        return $this;
    }

    /**
     * Complete every Plan Day that belongs to current UserPlan object
     *
     * @return void
     */
    private function completeDaysCurrentUserPlan() : void
    {
        $plan_days_to_complete = PlanDay::daysToCompleteByPlanId($this->plan_id)->get();

        $inserts_plan_days_completed = [];
        foreach ($plan_days_to_complete as $plan_day) {
            $inserts_plan_days_completed[] = [
                'user_id'     => Auth::user()->id,
                'plan_day_id' => $plan_day->id
            ];
        }

        if (!empty($inserts_plan_days_completed)) {
            PlanDayComplete::insert($inserts_plan_days_completed);
        }
    }

    public function reset($start_date = null)
    {
        PlanDay::where('plan_id', $this->plan_id)->get()
            ->each(function ($plan_day) {
                $plan_day->unComplete();
            });
        $this->attributes['percentage_completed'] = 0;
        $this->attributes['start_date'] = $start_date;

        return $this;
    }
}
