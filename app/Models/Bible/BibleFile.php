<?php

namespace App\Models\Bible;

use DB;
use Illuminate\Database\Eloquent\Model;
use App\Models\Language\Language;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Bible\BibleBook;
use App\Models\Bible\Bible;

/**
 * App\Models\Bible\BibleFile
 *
 * @property-read \App\Models\Bible\Bible $bible
 * @property-read \App\Models\Bible\Book $book
 * @property-read \App\Models\Bible\BibleFileTimestamp $firstReference
 * @property-read \App\Models\Language\Language $language
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Bible\BibleFileTimestamp[] $timestamps
 * @mixin \Eloquent
 * @property-read \App\Models\Bible\BibleFileset $fileset
 * @property-read \App\Models\Bible\BibleFileTitle $title
 * @property-read \App\Models\Bible\BibleFileTitle $currentTitle
 * @property-read \App\Models\Bible\BibleFilesetConnection $connections
 *
 * @method static BibleFile whereId($value)
 * @property $id
 * @method static BibleFile whereBookId($value)
 * @property $book_id
 * @method static BibleFile whereChapterStart($value)
 * @property $chapter_start
 * @method static BibleFile whereChapterEnd($value)
 * @property $chapter_end
 * @method static BibleFile whereVerseStart($value)
 * @property $verse_start
 * @method static BibleFile whereVerseEnd($value)
 * @property $verse_end
 * @method static BibleFile whereVerseText($value)
 * @property $verse_text
 * @method static BibleFile whereFileName($value)
 * @property $file_name
 * @method static BibleFile whereFileSize($value)
 * @property $file_size
 * @method static BibleFile whereDuration($value)
 * @property $duration
 *
 * @OA\Schema (
 *     type="object",
 *     required={"filename"},
 *     description="The Bible File Model communicates information about biblical files stored in S3",
 *     title="BibleFile",
 *     @OA\Xml(name="BibleFile")
 * )
 *
 */
class BibleFile extends Model
{
    protected $connection = 'dbp';
    protected $table = 'bible_files';
    protected $hidden = ['created_at','updated_at'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The id",
     *   minimum=0,
     *   example=4
     * )
     *
     */
    protected $id;

    protected $hash_id;
    /**
     *
     * @OA\Property(
     *   title="book_id",
     *   type="string",
     *   example="MAT",
     *   description="The book_id",
     * )
     *
     */
    protected $book_id;
    /**
     *
     * @OA\Property(
     *   title="chapter_start",
     *   type="string",
     *   description="The chapter_start",
     *   example="4"
     * )
     *
     */
    protected $chapter_start;
    /**
     *
     * @OA\Property(
     *   title="chapter_end",
     *   type="string",
     *   description="If the Bible File spans multiple chapters this field indicates the last chapter of the selection",
     *   nullable=true,
     *   example="5"
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
     *   example="5"
     * )
     *
     */
    protected $verse_start;

    /**
     *
     * @OA\Property(
     *   title="verse_end",
     *   type="string",
     *   description="If the Bible File spans multiple verses this value will indicate the last verse in that reference. This value is inclusive, so for the reference John 1:1-4. The value would be 4 and the reference would contain verse 4.",
     *   nullable=true,
     *   example="5"
     * )
     *
     */
    protected $verse_end;

    /**
     *
     * @OA\Property(
     *   title="verse_text",
     *   type="string",
     *   description="If the BibleFile model returns text instead of a file_name this field will contain it.",
     *   example="And God said unto Abraham, And as for thee, thou shalt keep my covenant, thou, and thy seed after thee throughout their generations."
     * )
     *
     */
    protected $verse_text;

    /**
     *
     * @OA\Property(
     *   title="file_name",
     *   type="string",
     *   description="The file_name",
     *   example="ACHBSU_70_MAT_1.html",
     *   maxLength=191
     * )
     *
     */
    protected $file_name;

    /**
     *
     * @OA\Property(
     *   title="file_size",
     *   type="integer",
     *   description="The file size",
     *   example=5486618
     * )
     *
     */
    protected $file_size;

    /**
     *
     * @OA\Property(
     *   title="duration",
     *   type="integer",
     *   description="If the file has a set length of time, this field indicates that time in milliseconds",
     *   nullable=true,
     *   minimum=0,
     *   example=683
     * )
     *
     */
    protected $duration;

    public function language()
    {
        return $this->hasOne(Language::class);
    }

    public function fileset()
    {
        return $this->belongsTo(BibleFileset::class, 'hash_id', 'hash_id');
    }

    public function connections()
    {
        return $this->belongsTo(BibleFilesetConnection::class);
    }

    public function bible()
    {
        return $this->hasManyThrough(Bible::class, BibleFilesetConnection::class, 'hash_id', 'id', 'hash_id', 'bible_id');
    }

    public function book()
    {
        if ((int) checkParam('v', false) < 4) {
            return $this->belongsTo(Book::class, 'book_id', 'id')->orderBy('protestant_order');
        }

        $versification = optional($this->bible()->first())->versification;
        return $this->belongsTo(Book::class, 'book_id', 'id')
            ->join('bible_books', 'bible_books.book_id', 'books.id')
            ->orderByRaw(BibleBook::getBookOrderExpressionRaw($versification));
    }

    public function testament()
    {
        return $this->belongsTo(Book::class, 'book_id', 'id')->select(['book_testament','id']);
    }

    public function timestamps()
    {
        return $this->hasMany(BibleFileTimestamp::class);
    }

    public function currentTitle()
    {
        return $this->hasOne(BibleFileTitle::class, 'file_id', 'id');
    }

    public function streamBandwidth()
    {
        return $this->hasMany(StreamBandwidth::class);
    }

    public function scopeJoinBibleFileTimestamps($query)
    {
        return $query->join(
            DB::raw(
                '(  SELECT distinct_timestamps.bible_file_id
                    FROM (  SELECT DISTINCT bible_file_timestamps.bible_file_id
                            FROM bible_file_timestamps ) AS distinct_timestamps
                    GROUP BY distinct_timestamps.bible_file_id
                ) AS timestamps_counts'
            ),
            function ($join) {
                $join->on('timestamps_counts.bible_file_id', '=', 'bible_files.id');
            }
        );
    }

    /**
     * Add join related to the bible_file_tags entity
     *
     * @param Builder $query
     * @param string $book_id
     *
     * @return Builder
     */
    public function scopeJoinFileTag(Builder $query) : Builder
    {
        return $query->leftJoin('bible_file_tags', function ($left_query) {
            $left_query
                ->on('bible_file_tags.file_id', 'bible_files.id')
                ->where('bible_file_tags.tag', BibleFileTag::TAG_YOUTUBE_VIDEO);
        });
    }

    /**
     * Add join related to the bible_fileset_tags entity
     *
     * @param Builder $query
     * @param string $book_id
     *
     * @return Builder
     */
    public function scopeJoinFilesetTags(Builder $query, string $book_id) : Builder
    {
        return $query->leftJoin('bible_fileset_tags', function ($left_query) use ($book_id) {
            $left_query
                ->on('bible_fileset_tags.hash_id', 'bible_files.hash_id')
                ->where('bible_fileset_tags.name', BibleFileTag::TAG_YOUTUBE_PLAYLIST.':'.$book_id);
        });
    }

    /**
     * Add join related to the bible_books entity
     *
     * @param Builder $query
     * @param string $fileset_hash_id
     * @param string $bible_id
     * @param string|null $chapter_id
     * @param string|null $book_id
     *
     * @return Builder
     */
    public function scopeByHashIdJoinBooks(
        Builder $query,
        string $fileset_hash_id,
        Bible $bible,
        ?string $chapter_id,
        ?string $book_id
    ) : Builder {
        $bible_id = optional($bible)->id;

        $select_columns = [
            'bible_files.duration',
            'bible_files.hash_id',
            'bible_files.id',
            'bible_files.book_id',
            'bible_files.chapter_start',
            'bible_files.chapter_end',
            'bible_files.verse_start',
            'bible_files.verse_end',
            'bible_files.file_name',
            'bible_files.file_size',
            'bible_books.name as book_name',
            BibleBook::getBookOrderSelectColumnExpressionRaw($bible->versification, 'book_order'),
            'bible_file_tags.value as bible_tag_value',
        ];

        if ($book_id) {
            $select_columns[] = 'bible_fileset_tags.description as bible_fileset_tag_value';
        }

        return $query
            ->where('bible_files.hash_id', $fileset_hash_id)
            ->join(
                config('database.connections.dbp.database') .
                    '.bible_books',
                function ($q) use ($bible_id) {
                    $q
                        ->on(
                            'bible_books.book_id',
                            'bible_files.book_id'
                        )
                        ->where('bible_books.bible_id', $bible_id);
                }
            )
            ->join(
                config('database.connections.dbp.database') . '.books',
                'books.id',
                'bible_files.book_id'
            )
            ->joinFileTag()
            ->when(!is_null($chapter_id), function ($query) use ($chapter_id) {
                return $query->where(
                    'bible_files.chapter_start',
                    (int) $chapter_id
                );
            })
            ->when($book_id, function ($query) use ($book_id) {
                return $query
                    ->where('bible_files.book_id', $book_id)
                    ->joinFilesetTags($book_id);
            })
            ->select($select_columns);
    }
}
