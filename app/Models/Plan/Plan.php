<?php

namespace App\Models\Plan;

use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItemsComplete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use App\Models\User\User;
use Illuminate\Database\Query\JoinClause;

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
    protected $fillable   = ['user_id', 'name', 'suggested_start_date', 'draft', 'thumbnail', 'language_id'];
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
     * Get the plan query filtering by ID and User ID. The plan will be fetched with the user relationship.
     *
     * @param Builder $query
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeWithUserById(Builder $query, int $plan_id, ?int $user_id = null) : Builder
    {
        $select = ['plans.*'];

        if (!empty($user_id)) {
            $select[] = 'user_plans.start_date';
            $select[] = 'user_plans.percentage_completed';
        }

        return $query->with('user')
            ->where('plans.id', $plan_id)
            ->when(!empty($user_id), function ($q) use ($user_id) {
                $q->leftJoin('user_plans', function ($join) use ($user_id) {
                    $join->on('user_plans.plan_id', '=', 'plans.id')->where('user_plans.user_id', $user_id);
                });
            })
            ->select($select);
    }

    /**
     * Get the plan query filtering by ID and User ID.
     * The plan will be fetched with the user, days, playlist and playlist items relationships.
     * The completed attribute is fetching into the query.
     *
     * @param Builder $query
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeWithDaysPlaylistItemsAndUserById(Builder $query, int $plan_id, int $user_id) : Builder
    {
        return $query->with(['days' => function ($days_query) use ($user_id) {
            $days_query
                ->withCompletedDay($user_id)
                ->withPlaylistAndUserById($user_id);
        }])
        ->withUserById($plan_id, $user_id);
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
    public static function getWithDaysAndUserById(int $plan_id, ?int $user_id = null) : ?Plan
    {
        return self::with(['days' => function ($days_query) use ($user_id) {
            if (!empty($user_id)) {
                $days_query
                    ->withCompletedDay($user_id);
            }
        }])
        ->withUserById($plan_id, $user_id)
        ->first();
    }

    /**
     * Get the entire plan object by Id. The plan will be fetched with the user, days, playlist and playlist items
     * relationships. The completed attribute is fetching into the query.
     *
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Plan
     */
    public static function getWithDaysPlaylistItemsAndUserById(int $plan_id, int $user_id) : ?Plan
    {
        return self::withDaysPlaylistItemsAndUserById($plan_id, $user_id)->first();
    }

    public static function findOne(int $plan_id) : ?Plan
    {
        return Plan::where('id', $plan_id)->first();
    }

    /**
     * Get total users attached to the current plan
     *
     * @return int
     */
    public function getTotalUsersAttribute() : int
    {
        return $this->countUsers();
    }

    /**
     * Get count user by plan ID
     *
     * @return int
     */
    public function countUsers() : int
    {
        return UserPlan::where('plan_id', $this['id'])->count();
    }

    # Method
/**
     * Get the plan query sorting by last interaction User.
     *
     * @param Builder $query
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeSortByLastInteraction(Builder $query, User $user) : Builder
    {
        $playlist_items_completed_subquery = PlaylistItemsComplete::select(
                'user_playlists.plan_id',
                \DB::raw('MAX(playlist_items_completed.created_at) AS max_created_at')
            )
            ->join('user_playlists', 'user_playlists.id', 'playlist_items_completed.playlist_item_id')
            ->where('playlist_items_completed.user_id', $user->id)
            ->groupBy('user_playlists.plan_id');

        $query->leftJoinSub(
            $playlist_items_completed_subquery,
            'item_completed_interation',
            function (JoinClause $join) {
                $join->on(
                    'item_completed_interation.plan_id',
                    '=',
                    'user_plans.plan_id'
                );
            }
        );

        $user_playlists_subquery = Playlist::select(
                'user_playlists.plan_id',
                \DB::raw('MAX(user_playlists.updated_at) AS max_updated_at')
            )
            ->where('user_playlists.user_id', $user->id)
            ->groupBy('user_playlists.plan_id');

        $query->leftJoinSub(
            $user_playlists_subquery,
            'user_playlists_interation',
            function (JoinClause $join) {
                $join->on(
                    'user_playlists_interation.plan_id',
                    '=',
                    'user_plans.plan_id'
                );
            }
        );

        return $query->addSelect(\DB::raw('GREATEST(
            COALESCE(user_plans.updated_at, "1970-01-01 00:00:00"),
            COALESCE(item_completed_interation.max_created_at, "1970-01-01 00:00:00"),
            COALESCE(user_playlists_interation.max_updated_at, "1970-01-01 00:00:00"),
            COALESCE(plans.updated_at, "1970-01-01 00:00:00")
        ) as last_interaction'));
    }
}
