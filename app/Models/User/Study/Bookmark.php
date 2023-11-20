<?php

namespace App\Models\User\Study;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleBook;
use App\Services\Bibles\BibleFilesetService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * App\Models\User\Study
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $book_id
 * @property int $chapter
 * @property string $verse_start
 * @property string $user_id
 * @property int $verse_sequence
 * @property string $bible_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Note whereId($value)
 * @method static Note whereBookId($value)
 * @method static Note whereChapter($value)
 * @method static Note whereVerseStart($value)
 * @method static Note whereUserId($value)
 * @method static Note whereBibleId($value)
 *
 * @OA\Schema (
 *     type="object",
 *     description="The User created Bookmark",
 *     title="Bookmark",
 *     @OA\Xml(name="Bookmark")
 * )
 *
 */
class Bookmark extends Model
{
    use Compoships;
    use UserAnnotationTrait;

    protected $connection = 'dbp_users';
    protected $table = 'user_bookmarks';
    protected $fillable = [
        'id',
        'bible_id',
        'v2_id',
        'user_id',
        'book_id',
        'chapter',
        'verse_start',
        'verse_sequence',
    ];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The unique incrementing id for each Bookmark",
     *   minimum=0
     * )
     */
    protected $id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Book/properties/id")
     */
    protected $book_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/chapter_start")
     */
    protected $chapter;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/verse_start")
     */
    protected $verse_start;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/verse_sequence")
     */
    protected $verse_sequence;

    /**
     *
     * @OA\Property(ref="#/components/schemas/User/properties/id")
     */
    protected $user_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Bible/properties/id")
     */
    protected $bible_id;

    /** @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp the Note was last updated at",
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
     *   description="The timestamp the note was created at"
     * )
     *
     * @method static Note whereCreatedAt($value)
     * @public Carbon $created_at
     */
    protected $created_at;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->hasOne(BibleBook::class, 'book_id', 'book_id')->where(
            'bible_id',
            $this['bible_id']
        );
    }

    public function bibleBook()
    {
        return $this->hasOne(BibleBook::class, ['book_id', 'bible_id'], ['book_id', 'bible_id']);
    }

    public function bible()
    {
        return $this->belongsTo(Bible::class);
    }

    public function tags()
    {
        return $this->hasMany(AnnotationTag::class, 'bookmark_id', 'id');
    }

    public function getVerseTextAttribute()
    {
        $chapter = $this['chapter'];
        $verse_start = $this['verse_start'];
        $bible = $this->bible;

        if (!$bible) {
            return '';
        }

        $testament = $this->bibleBook && $this->bibleBook->book
        ? $this->bibleBook->book->book_testament
        : '';

        $text_fileset = $this->getTextFilesetRelatedByTestament($testament);

        if (!$text_fileset) {
            return '';
        }

        return BibleFilesetService::getVerseTextFilterBy(
            $bible,
            $text_fileset->hash_id,
            $this['book_id'],
            $verse_start,
            $chapter
        );
    }
}
