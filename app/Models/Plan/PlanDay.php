<?php

namespace App\Models\Plan;

use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

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

    public function complete()
    {
        $user = Auth::user();
        $completed_item = PlanDayComplete::firstOrNew([
            'user_id'               => $user->id,
            'plan_day_id'           => $this['id']
        ]);
        $completed_item->save();
        PlaylistItems::where('playlist_id', $this['playlist_id'])->each(function ($playlist_item) {
            $playlist_item->complete();
        });
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

    public function unComplete()
    {
        $user = Auth::user();
        $completed_item = PlanDayComplete::where('plan_day_id', $this['id'])
            ->where('user_id', $user->id);
        $completed_item->delete();
        PlaylistItems::where('playlist_id', $this['playlist_id'])->each(function ($playlist_item) {
            $playlist_item->unComplete();
        });
    }

    public function playlist()
    {
        return $this->belongsTo(Playlist::class);
    }
}
