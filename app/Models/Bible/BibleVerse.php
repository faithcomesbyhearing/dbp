<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 *
 * @OA\Schema (
 *     type="object",
 *     description="The BibleVerse model stores the unformatted Bible Text for searching & JSON returned verses",
 *     title="BibleVerse",
 *     @OA\Xml(name="BibleVerse")
 * )
 *
 * @package App\Models\Bible
 */
class BibleVerse extends Model
{
    protected $connection = 'dbp';
    protected $table = 'bible_verses';
    protected $hidden = ['id', 'hash_id'];
    public $timestamps = false;

    /**
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The incrementing id for the Bible Verse"
     * )
     */
    protected $id;

    protected $hash_id;

    /**
     * @OA\Property(ref="#/components/schemas/Book/properties/id")
     */
    protected $book_id;

    /**
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/chapter_start")
     */
    protected $chapter;

    /**
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/verse_start")
     */
    protected $verse_number;

    /**
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/verse_sequence")
     */
    protected $verse_sequence;

    /**
     * @OA\Property(
     *   title="verse_text",
     *   type="string",
     *   description="The text of the Bible Verse"
     * )
     */
    protected $verse_text;

    public function fileset()
    {
        return $this->belongsTo(BibleFileset::class, 'hash_id', 'hash_id');
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function concordance()
    {
        return $this->hasMany(BibleConcordance::class);
    }

    public function scopeWithVernacularMetaData($query, $bible, $testament_filter = null)
    {
        $numeral_system_id = $bible ? $bible->numeral_system_id : null;
        $query->when($numeral_system_id, function ($query) use ($numeral_system_id) {

            return $query
            ->join('numeral_system_glyphs as glyph_chapter', function ($join) use ($numeral_system_id) {
                $join->on('bible_verses.chapter', 'glyph_chapter.value')
                ->where('glyph_chapter.numeral_system_id', $numeral_system_id);
            })
            ->join('numeral_system_glyphs as glyph_start', function ($join) use ($numeral_system_id) {
                $join->on('bible_verses.verse_start', 'glyph_start.value')
                    ->where('glyph_start.numeral_system_id', $numeral_system_id);
            })
            ->join('numeral_system_glyphs as glyph_end', function ($join) use ($numeral_system_id) {
                $join->on('bible_verses.verse_end', 'glyph_end.value')
                    ->where('glyph_end.numeral_system_id', $numeral_system_id);
            });
        })
        ->join('books', function ($join) use ($testament_filter) {
            $join->on('books.id', 'bible_verses.book_id');
            if (is_array($testament_filter) && !empty($testament_filter)) {
                $join->whereIn('books.book_testament', $testament_filter);
            }
        })
        ->join('bible_books', function ($join) use ($bible) {
            $join->on('bible_verses.book_id', 'bible_books.book_id')->where('bible_books.bible_id', $bible->id);
        });
    }

    /**
     * @param Builder $query
     * @param string $language_code
     */
    public function scopeFilterByLanguage(
        Builder $query,
        string $language_code,
    ) : Builder {
        return $query
            ->join('languages', function ($query) use ($language_code) {
                return $query
                ->on('bibles.language_id', '=', 'languages.id')
                ->where('languages.iso', $language_code);
            });
    }

    /**
     * @param Builder $query
     * @param string $bible_id
     */
    public function scopeFilterByBible(
        Builder $query,
        string $bible_id,
    ) : Builder {
        return $query
            ->where('bibles.id', $bible_id);
    }


    /**
     * @param Builder $query
     * @param string $book_id
     * @param string $chapter_id
     * @param string $verse_number
     */
    public function scopeWithBibleFilesets(
        Builder $query,
        string $book_id,
        string $chapter_id,
        string $verse_number = null,
    ) : Builder {
        return $query->where('book_id', $book_id)
            ->where('chapter', $chapter_id)
            ->when($verse_number, function ($query) use ($verse_number) {
                return $query->where('verse_start', $verse_number);
            })
            ->when(empty($verse_number), function ($query) {
                return $query->orderBy('verse_sequence');
            })
            ->join('bible_filesets', 'bible_filesets.hash_id', 'bible_verses.hash_id')
            ->join('bible_fileset_connections', 'bible_filesets.hash_id', 'bible_fileset_connections.hash_id')
            ->join('bibles', 'bibles.id', 'bible_fileset_connections.bible_id')
            ->with(["fileset.bible.filesetsWithoutMeta" => function ($query) use ($book_id, $chapter_id) {
                return $query->whereExists(function ($subquery) use ($book_id, $chapter_id) {
                    return $subquery->select(\DB::raw(1))
                        ->from('bible_files')
                        ->where('bible_files.chapter_start', $chapter_id)
                        ->where('bible_files.book_id', $book_id)
                        ->whereColumn('bible_files.hash_id', '=', 'bible_filesets.hash_id');
                });
            }])
            ->select([
                'bible_verses.verse_start',
                'bible_verses.verse_end',
                'bible_verses.chapter',
                'bible_verses.book_id',
                'bible_verses.verse_text',
                'bible_verses.verse_sequence',
                'bible_verses.hash_id',
                'bibles.language_id',
                'bibles.id AS bible_id',
                'bible_filesets.id AS fileset_id',
                'bible_filesets.set_type_code AS fileset_set_type_code',
                'bible_filesets.set_size_code AS fileset_set_size_code',
            ]);
    }

    /**
     * @param Builder $query
     * @param string $key
     */
    public function scopeIsContentAvailable(Builder $query, Collection $access_group_ids) : Builder
    {
        return $query->whereExists(function ($sub_query) use ($access_group_ids) {
            return $sub_query->select(\DB::raw(1))
                ->from('access_group_filesets AS agf')
                ->join(
                    'bible_filesets as abf',
                    function ($join) {
                        $join->on('abf.hash_id', '=', 'agf.hash_id')
                            ->where('abf.content_loaded', true)
                            ->where('abf.archived', false);
                    }
                )
                ->whereIn('agf.access_group_id', $access_group_ids)
                ->whereColumn('agf.hash_id', '=', 'bible_verses.hash_id');
        });
    }

    /**
     * @param Builder $query
     * @param string $fileset_hash_id
     * @param string $book_id
     * @param int $chapter
     */
    public function scopeFilterByHashIdBookAndChapter(
        Builder $query,
        string $fileset_hash_id,
        string $book_id,
        int $chapter
    ) : Builder {
        return $query
            ->where('hash_id', $fileset_hash_id)
            ->where('bible_verses.book_id', $book_id)
            ->where('chapter', $chapter)
            ->orderBy('verse_sequence')
            ->select([
                'bible_verses.verse_text',
            ]);
    }
}
