<?php

namespace App\Models\Playlist;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleFileTimestamp;
use App\Models\Bible\BibleVerse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * App\Models\Playlist
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $playlist_id
 * @property string $fileset_id
 * @property string $book_id
 * @property int $chapter_start
 * @property int $chapter_end
 * @property int $verse_start
 * @property int $verse_end
 * @property int $verses
 * @property int $duration
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Playlist Item",
 *     title="Playlist Item"
 * )
 *
 */

class PlaylistItems extends Model implements Sortable
{
    use SortableTrait;

    protected $connection = 'dbp_users';
    public $table         = 'playlist_items';
    protected $fillable   = [
        'playlist_id',
        'fileset_id',
        'book_id',
        'chapter_start',
        'chapter_end',
        'verse_start',
        'verse_end',
        'verse_sequence',
        'duration',
        'verses'
    ];
    protected $hidden     = ['playlist_id', 'created_at', 'updated_at', 'order_column'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The playlist item id"
     * )
     *
     */
    protected $id;

    /**
     *
     * @OA\Property(
     *   title="playlist_id",
     *   type="integer",
     *   description="The playlist id"
     * )
     *
     */
    protected $playlist_id;

    /**
     *
     * @OA\Property(
     *   title="fileset_id",
     *   type="string",
     *   description="The fileset id"
     * )
     *
     */
    protected $fileset_id;
    /**
     *
     * @OA\Property(
     *   title="book_id",
     *   type="string",
     *   description="The book_id",
     * )
     *
     */
    protected $book_id;
    /**
     *
     * @OA\Property(
     *   title="chapter_start",
     *   type="integer",
     *   description="The chapter_start",
     *   minimum=0,
     *   maximum=150,
     *   example=4
     * )
     *
     */
    protected $chapter_start;
    /**
     *
     * @OA\Property(
     *   title="chapter_end",
     *   type="integer",
     *   description="If the Bible File spans multiple chapters this field indicates the last chapter of the selection",
     *   nullable=true,
     *   minimum=0,
     *   maximum=150,
     *   example=5
     * )
     *
     */
    protected $chapter_end;
    /**
     *
     * @OA\Property(
     *   title="verse_start",
     *   type="string",
     *   description="The starting verse at which the BibleFile reference begins",
     *   minimum=1,
     *   maximum=176,
     *   example=5
     * )
     *
     */
    protected $verse_start;
    /**
     *
     * @OA\Property(
     *   title="verse_sequence",
     *   type="integer",
     *   description="The starting verse at which the BibleFile reference begins",
     *   minimum=1,
     *   maximum=176,
     *   example=5
     * )
     *
     */
    protected $verse_sequence;
    /**
     *
     * @OA\Property(
     *   title="verse_end",
     *   type="integer",
     *   description="If the Bible File spans multiple verses this value will indicate the last verse in that reference. This value is inclusive, so for the reference John 1:1-4. The value would be 4 and the reference would contain verse 4.",
     *   nullable=true,
     *   minimum=1,
     *   maximum=176,
     *   example=5
     * )
     */
    protected $verse_end;

    /**
     *
     * @OA\Property(
     *   title="duration",
     *   type="integer",
     *   description="The playlist item calculated duration"
     * )
     *
     */
    protected $duration;

    /**
     *
     * @OA\Property(
     *   title="verses",
     *   type="integer",
     *   description="The playlist item verses count"
     * )
     *
     */
    protected $verses;

    /** @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp the playlist item was last updated at",
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
     *   description="The timestamp the playlist item was created at"
     * )
     *
     * @method static Note whereCreatedAt($value)
     * @public Carbon $created_at
     */
    protected $created_at;

    public function calculateDuration()
    {
        // Currently, this method may not work because it is not supporting to use the cache methods
        // that are executing into the getDuration method
        $playlist_item = (object) $this->attributes;
        $this->attributes['duration'] = $this->getDuration($playlist_item) ?? 0;
        return $this;
    }



    private function getDuration($playlist_item)
    {
        $fileset = cacheRemember('bible_fileset', [$playlist_item->fileset_id], now()->addDay(), function () use ($playlist_item) {
            return $this->relationLoaded('fileset')
                ? $this->fileset
                : BibleFileset::whereId($playlist_item->fileset_id)->first();
        });

        if (!$fileset) {
            return 0;
        }

        $bible_files = BibleFile::with('streamBandwidth.transportStreamTS')
            ->with('streamBandwidth.transportStreamBytes')->where([
                    'hash_id' => $fileset->hash_id,
                    'book_id' => $playlist_item->book_id,
                ])
            ->where('chapter_start', '>=', $playlist_item->chapter_start)
            ->where('chapter_start', '<=', $playlist_item->chapter_end)
            ->get();
        $duration = 0;
        if ($fileset->set_type_code === 'audio_stream' || $fileset->set_type_code === 'audio_drama_stream') {
            foreach ($bible_files as $bible_file) {
                $currentBandwidth = $bible_file->streamBandwidth->first();
                $transportStream = sizeof($currentBandwidth->transportStreamBytes)
                    ? $currentBandwidth->transportStreamBytes
                    : $currentBandwidth->transportStreamTS;
                if ($playlist_item->verse_end && $playlist_item->verse_start) {
                    $transportStream = self::processVersesOnTransportStream(
                        $playlist_item->chapter_start,
                        $playlist_item->chapter_end,
                        (int) $playlist_item->verse_start,
                        (int) $playlist_item->verse_end,
                        $transportStream,
                        $bible_file
                    );
                }

                foreach ($transportStream as $stream) {
                    $duration += $stream->runtime;
                }
            }
        } else {
            foreach ($bible_files as $bible_file) {
                $duration += $bible_file->duration ?? 180;
            }
        }

        return $duration;
    }

    /**
     * Processes verses on a transport stream.
     *
     * This method takes in start and end chapters and verses, a transport stream, and a Bible file.
     * It then processes the verses based on these parameters and returns the processed transport stream.
     *
     * @param int $chapter_start The starting chapter.
     * @param int $chapter_end The ending chapter.
     * @param int $verse_start The starting verse.
     * @param int $verse_end The ending verse.
     * @param Collection $transportStream The transport stream to process.
     * @param BibleFile $bible_file The Bible file associated with the transport stream.
     *
     * @return Collection | array The processed transport stream.
     *
     */
    public static function processVersesOnTransportStream(
        int $chapter_start,
        int $chapter_end,
        int $verse_start,
        int $verse_end,
        SupCollection $transportStream,
        BibleFile $bible_file
    ) : Collection | array {
        if ($chapter_end === $chapter_start) {
            $transport_stream_array = array_slice($transportStream->all(), 1, $verse_end);
            return array_slice($transport_stream_array, $verse_start - 1);
        }

        $transport_stream_array = $transportStream->all();
        $transport_stream_array = array_slice($transport_stream_array, 1);
        if ($bible_file->chapter_start === $chapter_start) {
            return array_slice($transport_stream_array, $verse_start - 1);
        }
        if ($bible_file->chapter_start === $chapter_end) {
            return array_slice($transport_stream_array, 0, $verse_end);
        }

        return $transportStream;
    }

    protected $appends = ['completed', 'full_chapter', 'path', 'metadata'];


    public function calculateVerses()
    {
        $fileset_id = $this['fileset_id'];
        $book_id  = $this['book_id'];
        $chapter_start  = $this['chapter_start'];
        $chapter_end  = $this['chapter_end'];
        $fileset = cacheRemember('text_bible_fileset', [$fileset_id], now()->addDay(), function () use ($fileset_id) {
            return BibleFileset::where('id', $fileset_id)
                ->whereNotIn('set_type_code', ['text_format'])
                ->first();
        });

        $bible_files = $fileset
            ? BibleFile::where('hash_id', $fileset->hash_id)
                ->where([
                    ['book_id', $book_id],
                    ['chapter_start', '>=', $chapter_start],
                    ['chapter_start', '<', $chapter_end],
                ])
                ->get()
        : [];
        $verses_middle = 0;
        foreach ($bible_files as $bible_file) {
            $verses_middle += ((int)$bible_file->verse_start - 1) + (int)$bible_file->verse_end;
        }
        if (!$this['verse_start'] && !$this['verse_end']) {
            $verses = $verses_middle;
        } else {
            $verses = $verses_middle - ((int)$this['verse_start'] - 1) + (int)$this['verse_end'];
        }

        // Try to get the verse count from the bible_verses table
        if (!$verses) {
            $text_fileset = $fileset
                ? BibleFileset::filesetTypeTextPlainAssociated($fileset->id)
                : null;
            if ($text_fileset) {
                $verses =  BibleVerse::where('hash_id', $text_fileset->hash_id)
                    ->where([
                        ['book_id', $book_id],
                        ['chapter', '>=', $chapter_start],
                        ['chapter', '<=', $chapter_end],
                    ])
                    ->count();
            }
        }

        $this->attributes['verses'] =  $verses;
        return $this;
    }

    public function getVerseText($verses_by_hash_id = [])
    {
        $text_fileset = null;

        if (!empty($verses_by_hash_id)) {
            $text_fileset = $verses_by_hash_id[$this['fileset_id']][0] ?? null;
        }

        if (empty($text_fileset)) {
            $text_fileset = $this['fileset_id']
                ? BibleFileset::filesetTypeTextPlainAssociated($this['fileset_id'])
                : null;
        }

        $verses = null;
        if ($text_fileset) {
            $where = [
                ['book_id', $this['book_id']],
                ['chapter', '>=', $this['chapter_start']],
                ['chapter', '<=', $this['chapter_end']],
            ];
            if ($this['verse_start'] && $this['verse_end']) {
                $where[] = ['verse_sequence', '>=', (int) $this['verse_start']];
                $where[] = ['verse_sequence', '<=', (int) $this['verse_end']];
            }
            $cache_params = [
                $text_fileset->hash_id,
                $this['book_id'],
                $this['chapter_start'],
                $this['chapter_end'],
                $this['verse_start'],
                $this['verse_end']
            ];
            $verses =  cacheRemember(
                'playlist_item_text',
                $cache_params,
                now()->addDay(),
                function () use ($text_fileset, $where) {
                    return BibleVerse::where('hash_id', $text_fileset->hash_id)
                        ->where($where)
                        ->orderBy('verse_sequence')
                        ->get()
                        ->pluck('verse_text');
                }
            );
        }

        return $verses;
    }

    public function getTimestamps()
    {

        // Check Params
        $fileset_id = $this['fileset_id'];
        $book = $this['book_id'];
        $chapter_start = $this['chapter_start'];
        $chapter_end = $this['chapter_end'];
        $verse_start = $this['verse_start'];
        $verse_end = $this['verse_end'];
        $cache_params = [$fileset_id, $book, $chapter_start, $chapter_end, $verse_start, $verse_end];
        return cacheRemember('playlist_item_timestamps', $cache_params, now()->addDay(), function () use ($fileset_id, $book, $chapter_start, $chapter_end, $verse_start, $verse_end) {
            $fileset = $this->relationLoaded('fileset')
                ? $this->fileset
                : BibleFileset::where('id', $fileset_id)->first();
            if (!$fileset) {
                return null;
            }
            
            $bible_files_query = $fileset->relationLoaded('files')
                ? $fileset->files
                : BibleFile::where('hash_id', $fileset->hash_id);
            $bible_files_query = $bible_files_query
                ->when($book, function ($query) use ($book) {
                    return $query->where('book_id', $book);
                })->where('chapter_start', '>=', $chapter_start)
                ->where('chapter_end', '<=', $chapter_end);
            
            $bible_files = $fileset->relationLoaded('files')
                ? $bible_files_query
                : $bible_files_query->get();

            if ($fileset->relationLoaded('files')) {
                $audioTimestamps = $bible_files
                    ->pluck('timestamps')
                    ->filter(function ($timestamp) {
                        return !empty($timestamp) && !$timestamp->isEmpty();
                    })
                    ->sortBy('verse_sequence');
            } else {
                // Fetch Timestamps
                $audioTimestamps = BibleFileTimestamp::whereIn('bible_file_id', $bible_files->pluck('id'))
                ->orderBy('verse_sequence')
                ->get();
            }

            if ($audioTimestamps->isEmpty() && ($fileset->set_type_code === 'audio_stream' || $fileset->set_type_code === 'audio_drama_stream')) {
                $bible_files_ids = BibleFile::where([
                    'hash_id' => $fileset->hash_id,
                    'book_id' => $book,
                ])
                    ->where('chapter_start', '>=', $chapter_start)
                    ->where('chapter_start', '<=', $chapter_end)
                    ->get()->pluck('id');

                $timestamps = sizeof($bible_files_ids) > 0
                    ? DB::connection('dbp')->select(
                        'select t.* from bible_file_stream_bandwidths as b
                        join bible_file_stream_bytes as s
                        on s.stream_bandwidth_id = b.id
                        join bible_file_timestamps as t
                        on t.id = s.timestamp_id
                        where b.bible_file_id IN (?) and  s.timestamp_id IS NOT NULL',
                        [join(',', $bible_files_ids->toArray())]
                    )
                    : [];
                $audioTimestamps = $timestamps;
            } else {
                $audioTimestamps = $audioTimestamps->toArray();
            }

            if ($verse_start && $verse_end) {
                $audioTimestamps =  Arr::where($audioTimestamps, function ($timestamp) use ($verse_start, $verse_end) {
                    return (int)$timestamp->verse_start >= (int)$verse_start && (int)$timestamp->verse_start <= (int)$verse_end;
                });
            }

            $audioTimestamps = Arr::pluck($audioTimestamps, 'timestamp', 'verse_start');
            return $audioTimestamps;
        });
    }

    /**
     * @OA\Property(
     *   property="completed",
     *   title="completed",
     *   type="boolean",
     *   description="If the playlist item is completed"
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

        $complete = PlaylistItemsComplete::where('playlist_item_id', $this->attributes['id'])
            ->where('user_id', $user->id)->first();

        return !empty($complete);
    }

    /**
     * @OA\Property(
     *   property="full_chapter",
     *   title="full_chapter",
     *   type="boolean",
     *   description="If the playlist item is a full chapter item"
     * )
     */
    public function getFullChapterAttribute()
    {
        return (bool) !$this->attributes['verse_start'] && !$this->attributes['verse_end'];
    }

    /**
     * @OA\Property(
     *   property="path",
     *   title="path",
     *   type="string",
     *   description="Hls path of the playlist item"
     * )
     */
    public function getPathAttribute()
    {
        return route('v4_internal_playlists_item.hls', ['playlist_item_id'  => $this->attributes['id'], 'v' => checkParam('v'), 'key' => checkParam('key')]);
    }

    /**
     * @OA\Property(
     *   property="metadata",
     *   title="metadata",
     *   type="object",
     *   description="Bible metadata info",
     *      @OA\Property(property="bible_id", ref="#/components/schemas/Bible/properties/id"),
     *      @OA\Property(property="bible_name", ref="#/components/schemas/BibleTranslation/properties/name"),
     *      @OA\Property(property="bible_vname", ref="#/components/schemas/BibleTranslation/properties/name"),
     *      @OA\Property(property="book_name", ref="#/components/schemas/BookTranslation/properties/name")
     * )
     */
    public function getMetadataAttribute()
    {
        $fileset_id = $this['fileset_id'];
        $book_id = $this['book_id'];

        return cacheRemember(
            'playlist_item_metadata',
            [$fileset_id, $book_id],
            now()->addDay(),
            function () use ($fileset_id, $book_id) {
                $bible_fileset = $this->relationLoaded('fileset')
                    ? $this->fileset
                    : BibleFileset::whereId($fileset_id)->first();

                // check if there exists an invalid fileset for each playlist item (data issue)
                if (isset($bible_fileset)) {
                    $bible = $bible_fileset->bible->first();
                    if (!$bible) {
                        return null;
                    }
                    $bible = Bible::whereId($bible->id)->with(['translations', 'books.book'])->first();
                } else {
                    return null;
                }

                return [
                    'bible_id' => $bible->id,
                    'bible_name' => optional(
                        $bible->translations->where('language_id', $GLOBALS['i18n_id'])->first()
                    )->name,
                    'bible_vname' =>  optional($bible->vernacularTranslation)->name,
                    'book_name' => optional($bible->books->where('book_id', $book_id)->first())->name
                ];
            }
        );
    }

    public function fileset()
    {
        return $this->belongsTo(BibleFileset::class);
    }

    public function complete()
    {
        $user = Auth::user();

        $playlist_items_to_complete = [
            ['user_id' => $user->id, 'playlist_item_id' => $this['id']]
        ];

        PlaylistItemsComplete::upsert(
            $playlist_items_to_complete,
            ['user_id', 'playlist_item_id'],
            ['user_id', 'playlist_item_id']
        );
    }

    public function unComplete()
    {
        $user = Auth::user();
        $completed_item = PlaylistItemsComplete::where('playlist_item_id', $this['id'])
            ->where('user_id', $user->id);
        $completed_item->delete();
    }

    /**
     * Get the Playlist Item with the Playlist Item completed relationship and
     * the completed attribute is fetching into the query.
     *
     * @param Builder $query_items
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeWithPlaylistItemCompleted(Builder $query_items, int $user_id) : Builder
    {
        return $query_items->select([
            'id',
            'fileset_id',
            'book_id',
            'chapter_start',
            'chapter_end',
            'playlist_id',
            'verse_start',
            'verse_end',
            'verse_sequence',
            'verses',
            'duration',
            \DB::Raw('IF(playlist_items_completed.playlist_item_id, true, false) as completed'),
        ])
        ->leftJoin('playlist_items_completed', function ($query_join) use ($user_id) {
            $query_join
                ->on('playlist_items_completed.playlist_item_id', '=', 'playlist_items.id')
                ->where('playlist_items_completed.user_id', $user_id);
        });
    }

    /**
     * Get query with all items that have NOT been completed for a plan day and a specific user
     *
     * @param Builder $query
     * @param int $plan_day_id
     * @param int $user_id
     *
     * @return Builder
     */
    public function scopeWithItemsToCompleteByPlanDayAndUser(
        Builder $query,
        int $plan_day_id,
        int $user_id
    ) : Builder {
        return $query
            ->join('plan_days as pld', 'playlist_items.playlist_id', 'pld.playlist_id')
            ->leftJoin('playlist_items_completed as pldc', function ($query_join) use ($user_id) {
                $query_join
                    ->on('pldc.playlist_item_id', '=', 'playlist_items.id')
                    ->where('pldc.user_id', $user_id);
            })
            ->where('pld.id', $plan_day_id)
            ->whereNull('pldc.playlist_item_id');
    }

    public static function findByIdsWithFilesetRelation(Array $playlist_ids, string $order_by = 'id') : Collection
    {
        return PlaylistItems::select([
            'id',
            'fileset_id',
            'book_id',
            'chapter_start',
            'chapter_end',
            'playlist_id',
            'verse_start',
            'verse_end',
            'verse_sequence',
            'order_column',
            'verses',
            'duration',
            \DB::Raw('false as completed'),
        ])
            ->whereIn('playlist_id', $playlist_ids)
            ->with(['fileset' => function ($query_fileset) {
                $query_fileset->with(['bible' => function ($query_bible) {
                    $query_bible->with([
                        'translations',
                        'vernacularTranslation',
                        'books.book'
                    ]);
                }]);
            }])
            ->orderBy($order_by)
            ->get();
    }

    /**
     * Get the last items of playlist give a ID and limit number
     *
     * @param int $playlist_id
     * @param int $limit
     *
     * @return Collection
     */
    public static function getLastItemsByPlaylistId(
        int $playlist_id,
        int $limit = null
    ) : Collection {
        return PlaylistItems::select([
            'id',
            'fileset_id',
            'book_id',
            'chapter_start',
            'chapter_end',
            'playlist_id',
            'verse_start',
            'verse_end',
            'verse_sequence',
            'order_column',
            'verses',
            'duration',
            \DB::Raw('false as completed'),
        ])
            ->where('playlist_id', $playlist_id)
            ->with(['fileset' => function ($query_fileset) {
                $query_fileset->with(['bible' => function ($query_bible) {
                    $query_bible->with([
                        'translations',
                        'vernacularTranslation',
                        'books.book'
                    ]);
                }]);
            }])
            ->orderBy('id', 'DESC')
            ->when($limit, function ($query_limit) use ($limit) {
                $query_limit->limit($limit);
            })
            ->get()
            ->reverse();
    }

    public function generateUniqueKey() : string
    {
        return implode(
            '-',
            [
                'playlist_id'   => $this['playlist_id'],
                'fileset_id'    => $this['fileset_id'],
                'book_id'       => $this['book_id'],
                'chapter_start' => $this['chapter_start'],
                'chapter_end'   => $this['chapter_end'],
                'verse_start'   => $this['verse_start'] ?? null,
                'verse_end'     => $this['verse_end'] ?? null,
                'verse_sequence'=> $this['verse_sequence'] ?? null,
                'order_column'  => $this['order_column']
            ]
        );
    }

    /**
     * Get unique filesets using given playlist ids
     *
     * @param \Illuminate\Support\Collection $playlist_ids
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getUniqueFilesetsByPlaylistIds(Array|SupCollection $playlist_ids) : SupCollection
    {
        return PlaylistItems::select('fileset_id')
            ->distinct()
            ->whereIn('playlist_id', $playlist_ids)
            ->get()
            ->pluck('fileset_id');
    }
}
