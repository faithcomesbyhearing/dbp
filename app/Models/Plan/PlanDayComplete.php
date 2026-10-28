<?php

namespace App\Models\Plan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Traits\ModelBase;

class PlanDayComplete extends Model
{
    use ModelBase;

    protected $connection = 'dbp_users';
    public $table         = 'plan_days_completed';
    protected $primaryKey = ['user_id', 'plan_day_id'];
    protected $fillable   = ['user_id', 'plan_day_id'];
    public $incrementing  = false;
    public $timestamps = false;

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

    /**
     * Remove the plan days completed records that belong to a Plan and an User
     *
     * @param int $plan_id
     * @param int $user_id
     *
     * @return bool
     */
    public static function removeDaysByPlanAndUser(int $plan_id, int $user_id) : bool
    {
        return self::select('plan_day_id')
            ->whereExists(function ($sub_query) use ($plan_id) {
                return $sub_query->select(\DB::raw(1))
                    ->from('plan_days as pld')
                    ->where('pld.plan_id', $plan_id)
                    ->whereColumn('pld.id', '=', 'plan_days_completed.plan_day_id');
            })
            ->where('user_id', $user_id)
            ->delete();
    }

    /**
     * When a user marked each completed day of a plan complete, for GET /plans/{plan_id}?include_completed_at=true.
     *
     * created_at is a TIMESTAMP that the database clock sets on insert: PlanDay::complete never writes it, and its
     * upsert leaves it alone when the day is completed again. UNIX_TIMESTAMP() returns a TIMESTAMP column's stored UTC
     * value whatever the connection's time zone, so the result is UTC even on a server that is not. MIN() keeps the
     * earliest row if duplicates from before the unique key (userPlanDays:cleanup) remain.
     *
     * @param int $plan_id
     * @param int $user_id
     *
     * @return array [plan_day_id => "YYYY-MM-DDTHH:MM:SS.000000Z" or null when the row has no usable time]; days the
     *               user has not completed are absent
     */
    public static function getCompletedAtByPlanAndUserId(int $plan_id, int $user_id) : array
    {
        return self::select([
            'plan_days_completed.plan_day_id',
            \DB::raw('MIN(UNIX_TIMESTAMP(plan_days_completed.created_at)) AS completed_at_unix')
        ])
        ->join('plan_days', 'plan_days.id', '=', 'plan_days_completed.plan_day_id')
        ->where('plan_days.plan_id', $plan_id)
        ->where('plan_days_completed.user_id', $user_id)
        ->groupBy('plan_days_completed.plan_day_id')
        ->pluck('completed_at_unix', 'plan_day_id')
        ->map(function ($completed_at_unix) {
            return self::formatCompletedAt($completed_at_unix === null ? null : (int) $completed_at_unix);
        })
        ->all();
    }

    /**
     * Format a Unix time the way the plan's own created_at appears in the same response (Carbon's JSON form): UTC,
     * six decimals, Z. TIMESTAMP columns hold whole seconds, so the decimals are always zero.
     *
     * A NULL created_at (UNIX_TIMESTAMP gives NULL) or a zero date (it gives 0) is not a real completion time, so it
     * is null rather than 1970-01-01. The column is NOT NULL with a default on dev; prod's was not checked.
     *
     * @param int|null $unix
     *
     * @return string|null
     */
    public static function formatCompletedAt(?int $unix) : ?string
    {
        if ($unix === null || $unix <= 0) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s.000000\Z', $unix);
    }
}
