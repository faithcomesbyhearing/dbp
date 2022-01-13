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
}
