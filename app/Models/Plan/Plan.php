<?php

namespace App\Models\Plan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Models\User\User;

/**
 * App\Models\Plan
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $name
 * @property string $user_id
 * @property bool $featured
 * @property bool $draft
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 *
 *
 * @OA\Schema (
 *     type="object",
 *     description="The User created Plan",
 *     title="Plan"
 * )
 *
 */
class Plan extends Model
{
    use SoftDeletes;

    protected $connection = 'dbp_users';
    public $table         = 'plans';
    protected $fillable   = ['user_id', 'name', 'suggested_start_date', 'draft'];
    protected $hidden     = ['user_id', 'deleted_at', 'plan_id', 'language_id'];
    protected $dates      = ['deleted_at'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The plan id",
     *   minimum=0
     * )
     *
     */
    protected $id;
    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   description="The name of the plan"
     * )
     *
     */
    protected $name;
    /**
     *
     * @OA\Property(
     *   title="user_id",
     *   type="string",
     *   description="The user that created the plan"
     * )
     *
     */
    protected $user_id;
    /**
     *
     * @OA\Property(
     *   title="featured",
     *   type="boolean",
     *   description="If the plan is featured"
     * )
     *
     */
    protected $featured;
    /**
     *
     * @OA\Property(
     *   title="thumbnail",
     *   type="string",
     *   description="The image url",
     *   maxLength=191
     * )
     *
     */
    protected $thumbnail;
    /**
     *
     * @OA\Property(
     *   title="suggested_start_date",
     *   type="string",
     *   format="date",
     *   description="The suggested start date of the plan"
     * )
     *
     */
    protected $suggested_start_date;
    /**
     *
     * @OA\Property(
     *   title="draft",
     *   type="boolean",
     *   description="If the plan is draft"
     * )
     *
     */
    protected $draft;
    /** @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp the plan was last updated at",
     *   nullable=true
     * )
     *
     * @method static Note whereUpdatedAt($value)
     * @public Carbon|null $updated_at
     */
    protected $updated_at;
    /**
     *
     * @OA\Property(
     *   title="created_at",
     *   type="string",
     *   description="The timestamp the plan was created at"
     * )
     *
     * @method static Note whereCreatedAt($value)
     * @public Carbon $created_at
     */
    protected $created_at;
    protected $deleted_at;

    public function getFeaturedAttribute($featured)
    {
        return (bool) $featured;
    }

    public function getDraftAttribute($draft)
    {
        return (bool) $draft;
    }

    public function user()
    {
        return $this->belongsTo(User::class)->select('id', 'name');
    }

    public function days()
    {
        return $this->hasMany(PlanDay::class)->orderBy('order_column');
    }

    /**
     * Get the plan object by Id. The plan will be fetched with the user and days relationships.
     * The completed attribute is fetching into the query.
     *
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Plan
     */
    public static function getWithDaysAndUserById(int $plan_id, ?int $user_id = null) : Plan
    {
        $select = ['plans.*'];

        if (!empty($user_id)) {
            $select[] = 'user_plans.start_date';
            $select[] = 'user_plans.percentage_completed';
        }

        return self::with(['days' => function ($days_query) use ($user_id) {
            if (!empty($user_id)) {
                $days_query->select([
                        'id',
                        'plan_id',
                        'playlist_id',
                        \DB::Raw('IF(plan_days_completed.plan_day_id, true, false) as completed')
                    ])
                    ->leftJoin('plan_days_completed', function ($query_join) use ($user_id) {
                        $query_join
                            ->on('plan_days_completed.plan_day_id', '=', 'plan_days.id')
                            ->where('plan_days_completed.user_id', $user_id);
                    });
            }
        }])
        ->with('user')
        ->where('plans.id', $plan_id)
        ->when(!empty($user_id), function ($q) use ($user_id) {
            $q->leftJoin('user_plans', function ($join) use ($user_id) {
                $join->on('user_plans.plan_id', '=', 'plans.id')->where('user_plans.user_id', $user_id);
            });
        })
        ->select($select)
        ->first();
    }
}
