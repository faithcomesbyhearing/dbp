<?php

namespace App\Models\Plan;

use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\Playlist\PlaylistItemsComplete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * App\Models\Plan
 * @mixin \Eloquent
 *
 * @property int $plan_id
 * @property int $playlist_id
 *
 * @OA\Schema (
 *     type="object",
 *     description="The day of a Plan",
 *     title="Plan day"
 * )
 *
 */
class PlanDay extends Model implements Sortable
{
    use SortableTrait;

    protected $connection = 'dbp_users';
    public $table         = 'plan_days';
    protected $fillable   = ['plan_id', 'playlist_id'];
    protected $hidden     = ['plan_id', 'created_at', 'updated_at', 'order_column'];

    /**
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The plan day id"
     * )
     */
    protected $id;

    /**
     * @OA\Property(ref="#/components/schemas/Playlist/properties/id")
     */
    protected $playlist_id;

    protected $appends = ['completed'];

    /**
     * @OA\Property(
     *   property="completed",
     *   title="completed",
     *   type="boolean",
     *   description="If the plan day is completed"
     * )
     */
    public function getCompletedAttribute()
    {
        // if the object has the set virtual attribute is not necessary to do the query
        if (isset($this->attributes['completed']) && !is_null($this->attributes['completed'])) {
            return (bool) $this->attributes['completed'];
        }

        $user = Auth::user();
        if (empty($user)) {
            return false;
        }

        $complete = PlanDayComplete::where('plan_day_id', $this->attributes['id'])
            ->where('user_id', $user->id)->first();

        return !empty($complete);
    }

    public function verifyDayCompleted()
    {
        $user = Auth::user();
        $playlist_items_count = PlaylistItems::where('playlist_items.playlist_id', $this['playlist_id'])->count();
        $playlist_items_completed_count =
            PlaylistItems::where('playlist_items.playlist_id', $this['playlist_id'])
            ->join('playlist_items_completed', function ($join) use ($user) {
                $join->on('playlist_items_completed.playlist_item_id', '=', 'playlist_items.id')
                    ->where('playlist_items_completed.user_id', $user->id);
            })
            ->count();
        if ($playlist_items_count && $playlist_items_completed_count === $playlist_items_count) {
            $this->complete();
        }
        return  [
            'total_items' => $playlist_items_count,
            'total_items_completed' => $playlist_items_completed_count
        ];
    }

    /**
     * Validate if the playlist attached to the current day has filesets attached.
     *
     * @return bool
     */
    public function hasContentAvailable(Playlist $playlist_to_eval = null) : bool
    {
        if (!is_null($playlist_to_eval) && $this['playlist_id'] === $playlist_to_eval->id) {
            return isset($playlist_to_eval->items) ? sizeof($playlist_to_eval->items) > 0 : false;
        }

        $plan_day_items = collect(
            \DB::connection($this->connection)
            ->select(
                \DB::raw(
                    'SELECT EXISTS (
                        SELECT 1 FROM playlist_items WHERE playlist_id = ?
                    ) as has_content'
                ),
                [$this['playlist_id']]
            )
        )->first();

        return $plan_day_items->has_content === 1;
    }

    /**
     * Complete plan day and all items for a given user
     *
     * @param int $user_id
     *
     * @return void
     */
    public function complete(int $user_id = null) : void
    {
        if (is_null($user_id)) {
            $user_id = Auth::user()->id;
        }
        $completed_item = PlanDayComplete::firstOrNew([
            'user_id'     => $user_id,
            'plan_day_id' => $this['id']
        ]);
        $completed_item->save();

        $this->completePlaylistItems($this['id'], $user_id);
    }

    /**
     * Complete all items for the current plan day and a given user
     *
     * @param int $plan_day_id
     * @param int $user_id
     *
     * @return bool
     */
    public function completePlaylistItems(int $plan_day_id, int $user_id = null) : bool
    {
        if (is_null($user_id)) {
            $user_id = Auth::user()->id;
        }

        $items_to_complete = PlaylistItems::select(['playlist_items.id'])
            ->withItemsToCompleteByPlanDayAndUser($plan_day_id, $user_id)
            ->get();

        $inserts_items_completed = [];
        foreach ($items_to_complete as $item) {
            $inserts_items_completed[] = [
                'user_id' => $user_id,
                'playlist_item_id' => $item->id
            ];
        }

        if (!empty($inserts_items_completed)) {
            return PlaylistItemsComplete::insert($inserts_items_completed);
        }

        return false;
    }

    /**
     * Get the playlist object related with the current day and it will include the items and fileset relationship.
     *
     * @return Playlist|null
     */
    public function getPlaylistWithItemsAndFilesets() : ?Playlist
    {
        return Playlist::with(
            [
                'items' => function ($subquery) {
                    $subquery->with('fileset');
                }
            ]
        )->where('user_playlists.id', $this['playlist_id'])->first();
    }

    /**
     * Remove plan day completed record and all items completed for a given user
     *
     * @param int $user_id
     *
     * @return void
     */
    public function unComplete(int $user_id = null) : void
    {
        if (is_null($user_id)) {
            $user_id = Auth::user()->id;
        }
        $this->unCompletePlaylistItems($this['id'], $user_id);

        PlanDayComplete::where('plan_day_id', $this['id'])
            ->where('user_id', $user_id)
            ->delete();
    }

    /**
     * Remove all items complated for the current plan day and a given user
     *
     * @param int $plan_day_id
     * @param int $user_id
     *
     * @return bool
     */
    public function unCompletePlaylistItems(int $plan_day_id, int $user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = Auth::user()->id;
        }

        return PlaylistItemsComplete::withItemsCompletedByPlanDayAndUser($plan_day_id, $user_id)
        ->delete();
    }

    public function playlist()
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * Get the summary of items completed and items no completed for each Plan day that belongs to specific plan
     * and user
     *
     * @param Builder $query
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeSummaryItemsCompletedByPlanId(Builder $query, int $plan_id, int $user_id) : Builder
    {
        return $query->select(
            \DB::raw(
                'plan_days.id,
                COUNT(plan_days.id) AS total_items,
                COUNT(playlist_items_completed.playlist_item_id) AS total_items_completed'
            )
        )
            ->join('playlist_items', 'playlist_items.playlist_id', 'plan_days.playlist_id')
            ->leftJoin('playlist_items_completed', function ($query_join) use ($user_id) {
                $query_join
                    ->on('playlist_items_completed.playlist_item_id', '=', 'playlist_items.id')
                    ->where('playlist_items_completed.user_id', $user_id);
            })
            ->where('plan_id', $plan_id)
            ->groupBy('plan_days.id');
    }

    /**
     * Get plan days records that has all items completed
     *
     * @param Builder $query
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeDaysToCompleteByPlanId(Builder $query, int $plan_id, int $user_id) : Builder
    {
        return $query->select('plan_days.id')
            ->leftJoin('plan_days_completed', 'plan_days.id', 'plan_days_completed.plan_day_id')
            ->where('plan_days.plan_id', $plan_id)
            ->whereExists(function ($sub_query) use ($plan_id, $user_id) {
                return $sub_query->select(\DB::raw(1))
                    ->from('plan_days as pld')
                    ->join('playlist_items as pli', 'pli.playlist_id', 'pld.playlist_id')
                    ->leftJoin('playlist_items_completed as pldc', function ($query_join) use ($user_id) {
                        $query_join
                            ->on('pldc.playlist_item_id', '=', 'pli.id')
                            ->where('pldc.user_id', $user_id);
                    })
                    ->where('pld.plan_id', $plan_id)
                    ->whereColumn('pld.id', '=', 'plan_days.id')
                    ->groupBy('pld.id')
                    ->havingRaw('COUNT(pld.id) = COUNT(`pldc`.playlist_item_id)');
            })
            ->whereNull('plan_days_completed.plan_day_id');
    }

    /**
     * Get the Play List IDs attached to a Plan and an User
     *
     * @param int $plan_id
     * @param int $user_id
     *
     * @return Array
     */
    public static function getPlanDayIdsByPlanAndUser(int $plan_id, int $user_id) : Array
    {
        return PlanDay::select('playlist_id')
            ->join('plan_days_completed as pdc', 'pdc.plan_day_id', 'plan_days.id')
            ->where('plan_days.plan_id', $plan_id)
            ->where('pdc.user_id', $user_id)
            ->get()
            ->pluck('playlist_id')
            ->all();
    }

    /**
     * Get the days records that belong to a specific plan. The completed attribute is performancing into the query.
     *
     * @param int $plan_id
     *
     * @return Collection
     */
    public static function getWithDaysById(int $plan_id, int $user_id) : Collection
    {
        return self::select([
            'id',
            'plan_id',
            'playlist_id',
            \DB::Raw('IF(plan_days_completed.plan_day_id, true, false) as completed')
        ])
        ->leftJoin('plan_days_completed', function ($query_join) use ($user_id) {
            $query_join
                ->on('plan_days_completed.plan_day_id', '=', 'plan_days.id')
                ->where('plan_days_completed.user_id', $user_id);
        })
        ->where('plan_id', $plan_id)
        ->get();
    }

    /**
     * Get the plan Day with the day completed relationship and the completed attribute is fetching into the query.
     *
     * @param Builder $query
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeWithCompletedDay(Builder $query, int $user_id) : Builder
    {
        return $query->select([
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

    /**
     * Get the plan Day with the Playlist relationship
     *
     * @param Builder $days_query
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeWithPlaylistAndUserById(Builder $days_query, int $user_id) : Builder
    {
        return $days_query->with(['playlist' => function ($playlist_query) use ($user_id) {
            $playlist_query->select([
                'user_playlists.*',
                \DB::Raw('IF(playlists_followers.user_id, true, false) as following')
            ])
            ->with(['user', 'items' => function ($query_items) use ($user_id) {
                if (!empty($user_id)) {
                    $query_items->withPlaylistItemCompleted($user_id);
                }

                $query_items->with(['fileset' => function ($query_fileset) {
                    $query_fileset->with(['bible' => function ($query_bible) {
                        $query_bible->with(['translations', 'vernacularTranslation', 'books.book']);
                    }]);
                }]);
            }])
            ->leftJoin('playlists_followers as playlists_followers', function ($join) use ($user_id) {
                $join
                    ->on('playlists_followers.playlist_id', '=', 'user_playlists.id')
                    ->where('playlists_followers.user_id', $user_id);
            });
        }]);
    }
}
