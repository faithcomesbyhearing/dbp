<?php

namespace App\Models\Playlist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use App\Models\User\User;

/**
 * App\Models\Playlist
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
 *     description="The User created Playlist",
 *     title="Playlist"
 * )
 *
 */
class Playlist extends Model
{
    use SoftDeletes;

    protected $connection = 'dbp_users';
    public $table         = 'user_playlists';
    protected $fillable   = ['user_id', 'name', 'external_content', 'draft', 'plan_id', 'language_id'];
    protected $hidden     = ['user_id', 'deleted_at', 'plan_id', 'language_id'];
    protected $dates      = ['deleted_at'];
    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The playlist id",
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
     *   description="The name of the playlist"
     * )
     *
     */
    protected $external_content;
    /**
     *
     * @OA\Property(
     *   title="external_content",
     *   type="string",
     *   description="The url to external content"
     * )
     *
     */
    protected $name;
    /**
     *
     * @OA\Property(
     *   title="user_id",
     *   type="string",
     *   description="The user that created the playlist"
     * )
     *
     */
    protected $user_id;
    /**
     *
     * @OA\Property(
     *   title="featured",
     *   type="boolean",
     *   description="If the playlist is featured"
     * )
     *
     */
    protected $featured;
    /** @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp the playlist was last updated at",
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
     *   title="draft",
     *   type="boolean",
     *   description="If the playlist is draft"
     * )
     *
     */
    protected $draft;
    /**
     *
     * @OA\Property(
     *   title="created_at",
     *   type="string",
     *   description="The timestamp the playlist was created at"
     * )
     *
     * @method static Note whereCreatedAt($value)
     * @public Carbon $created_at
     */
    protected $created_at;
    protected $deleted_at;
    protected $appends = ['verses'];

    public function getFeaturedAttribute($featured)
    {
        return (bool) $featured;
    }

    public function getDraftAttribute($draft)
    {
        return (bool) $draft;
    }

    /**
     *
     * @OA\Property(
     *   title="verses",
     *   type="integer",
     *   description="The playlist verses count"
     * )
     *
     */
    public function getVersesAttribute()
    {
        if ($this->relationLoaded('items')) {
            return $this->items->sum('verses');
        }

        return PlaylistItems::where('playlist_id', $this['id'])->get()->sum('verses');
    }

    /**
     *
     * @OA\Property(
     *   property="following",
     *   title="following",
     *   type="boolean",
     *   description="If the current user follows the playlist"
     * )
     *
     */
    public function getFollowingAttribute($following)
    {
        return (bool) $following;
    }

    public function user()
    {
        return $this->belongsTo(User::class)->select('id', 'name');
    }

    public function items()
    {
        return $this->hasMany(PlaylistItems::class)->orderBy('order_column');
    }

    public function scopeWithUserAndItemsById(Builder $query, int $playlist_id, int $user_id) : Builder
    {
        return Playlist::select([
            'user_playlists.*',
            \DB::Raw('IF(playlists_followers.user_id, true, false) as following')
        ])
        ->with(['user', 'items' => function ($query_items) use ($user_id) {
            if (!empty($user_id)) {
                $query_items->withPlaylistItemCompleted($user_id);
            }

            $query_items->with(['fileset' => function ($query_fileset) {
                $query_fileset->with('bible');
            }])
            ->conditionTagExcludeFileset(['opus', 'webm']);
        }])
            ->leftJoin('playlists_followers as playlists_followers', function ($join) use ($user_id) {
                $join->on('playlists_followers.playlist_id', '=', 'user_playlists.id')
                    ->where('playlists_followers.user_id', $user_id);
            })
            ->where('user_playlists.id', $playlist_id);
    }

    public static function findByUserAndIds(int $user_id, Array $playlist_ids) : Collection
    {
        return Playlist::with(
            [
                'items' => function ($subquery) use ($user_id) {
                    if (!empty($user_id)) {
                        $subquery->withPlaylistItemCompleted($user_id);
                    }
                    $subquery->with(['fileset' => function ($query_fileset) {
                        $query_fileset->with(['bible' => function ($query_bible) {
                            $query_bible->with([
                                'translations',
                                'vernacularTranslation',
                                'books.book'
                            ]);
                        }]);
                    }]);
                }
            ]
        )->whereIn('user_playlists.id', $playlist_ids)
        ->get()
        ->keyBy('id');
    }

    public static function findWithBibleRelationByUserAndId(int $user_id, int $playlist_id) : ?Playlist
    {
        return Playlist::with(['user', 'items' => function ($query_items) use ($user_id) {
            $query_items->withPlaylistItemCompleted($user_id);

            $query_items->with(['fileset' => function ($query_fileset) {
                $query_fileset->with(['bible' => function ($query_bible) {
                    $query_bible->with(['translations', 'vernacularTranslation', 'books.book']);
                }]);
            }]);
        }])
            ->where('user_playlists.id', $playlist_id)
            ->select(['user_playlists.*', \DB::Raw('false as following')])
            ->first();
    }

    public static function findByUserAndPlan(int $user_id, int $plan_id) : Collection
    {
        return Playlist::where('user_id', $user_id)
            ->where('plan_id', $plan_id)
            ->orderBy('id')
            ->get();
    }

    public static function findWithFollowersByUserAndIds(int $user_id, Array $playlist_ids) : Collection
    {
        return Playlist::with(['user', 'items' => function ($query_items) use ($user_id) {
            if (!empty($user_id)) {
                $query_items->withPlaylistItemCompleted($user_id);
            }

            $query_items->with(['fileset' => function ($query_fileset) {
                $query_fileset->with('bible');
            }])
            ->conditionTagExcludeFileset(['opus', 'webm']);
        }])
            ->leftJoin('playlists_followers as playlists_followers', function ($join) use ($user_id) {
                $join->on('playlists_followers.playlist_id', '=', 'user_playlists.id')
                    ->where('playlists_followers.user_id', $user_id);
            })
            ->whereIn('user_playlists.id', $playlist_ids)
            ->select(['user_playlists.*', \DB::Raw('IF(playlists_followers.user_id, true, false) as following')])
            ->get()
            ->keyBy('id');
    }

    public static function findWithPlaylistItemsByUserAndId(int $user_id, int $playlist_id) : ?Playlist
    {
        return Playlist::with(['user', 'items' => function ($query_items) {
            $query_items->select([
                'id',
                'fileset_id',
                'book_id',
                'chapter_start',
                'chapter_end',
                'playlist_id',
                'verse_start',
                'verse_end',
                'verses',
                'duration',
                \DB::Raw('false as completed'),
            ]);

            $query_items->with(['fileset' => function ($query_fileset) {
                $query_fileset->with(['files.timestamps', 'bible' => function ($query_bible) {
                    $query_bible->with(['translations', 'vernacularTranslation', 'books.book']);
                }]);
            }]);
        }])
            ->leftJoin('playlists_followers as playlists_followers', function ($join) use ($user_id) {
                $join->on('playlists_followers.playlist_id', '=', 'user_playlists.id')
                    ->where('playlists_followers.user_id', $user_id);
            })
            ->where('user_playlists.id', $playlist_id)
            ->select(['user_playlists.*', \DB::Raw('IF(playlists_followers.user_id, true, false) as following')])
            ->first();
    }

    public static function findOne(int $playlist_id) : ?Playlist
    {
        return Playlist::where('id', $playlist_id)->first();
    }
}
