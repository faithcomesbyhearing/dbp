<?php

namespace App\Models\User\Study;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleBook;
use App\Models\User\User;
use App\Services\Bibles\BibleFilesetService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Awobaz\Compoships\Compoships;

/**
 * App\Models\User\Note
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $book_id
 * @property int $chapter
 * @property string $verse_start
 * @property int $verse_sequence
 * @property int|null $verse_end
 * @property string $user_id
 * @property string $bible_id
 * @property string|null $reference_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Note's model",
 *     title="Note",
 *     @OA\Xml(name="Note")
 * )
 *
 */
class Note extends Model
{
    use Compoships;
    use UserAnnotationTrait;

    protected $connection = 'dbp_users';
    protected $table = 'user_notes';
    protected $hidden = ['user_id'];
    protected $fillable = [
        'id',
        'v2_id',
        'user_id',
        'bible_id',
        'book_id',
        'chapter',
        'verse_start',
        'verse_sequence',
        'verse_end',
        'notes',
        'created_at',
        'updated_at'
    ];
    protected $appends = ['bible_name', 'verse_text'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The unique incrementing id for each NoteTag",
     *   minimum=0
     * )
     *
     * @method static Note whereId($value)
     */
    protected $id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Book/properties/id")
     * @method static Note whereBookId($value)
     */
    protected $book_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/chapter_start")
     * @method static Note whereChapter($value)
     */
    protected $chapter;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/verse_sequence")
     * @method static Note whereVerseSequence($value)
     */
    protected $verse_sequence;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/verse_start")
     * @method static Note whereVerseStart($value)
     */
    protected $verse_start;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/verse_end")
     * @method static Note whereVerseEnd($value)
     */
    protected $verse_end;

    /**
     *
     * @OA\Property(ref="#/components/schemas/User/properties/id")
     * @method static Note whereUserId($value)
     */
    protected $user_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Bible/properties/id")
     * @method static Note whereBibleId($value)
     */
    protected $bible_id;

    /**
     *
     * @OA\Property(
     *   title="reference_id",
     *   type="string",
     *   description="The unique incrementing id for each NoteTag"
     * )
     *
     * @method static Note whereReferenceId($value)
     */
    protected $reference_id;

    /**
     *
     * @OA\Property(
     *   title="notes",
     *   type="string",
     *   description="The body of the notes",
     *   nullable=true
     * )
     *
     * @method static Note whereNotes($value)
     */
    protected $notes;

    /**
     *
     * @OA\Property(
     *   title="created_at",
     *   type="string",
     *   description="The timestamp the note was created at"
     * )
     *
     * @method static Note whereCreatedAt($value)
     */
    protected $created_at;

    /**
     *
     * @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp the Note was last updated at",
     *   nullable=true
     * )
     *
     * @method static Note whereUpdatedAt($value)
     */
    protected $updated_at;

    public function getNotesAttribute($note)
    {
        try {
            return Crypt::decrypt($note);
        } catch (DecryptException $e) {
            \Log::channel('errorlog')->error($e->getMessage());
            return '';
        }
    }


    /**
     *
     * @property-read User $user
     *
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bible()
    {
        return $this->belongsTo(Bible::class);
    }

    /**
     *
     * @property-read AnnotationTag[] $tags
     *
     */
    public function tags()
    {
        return $this->hasMany(AnnotationTag::class, 'note_id', 'id');
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

    /**
     * @OA\Property(
     *   property="verse_text",
     *   title="verse_text",
     *   type="string",
     *   description="The text of the Bible Verse"
     * )
     */
    public function getVerseTextAttribute()
    {
        $chapter = $this['chapter'];
        $verse_start = $this['verse_start'];
        $verse_end = $this['verse_end'] ? $this['verse_end'] : $verse_start;
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

        return BibleFilesetService::getRangeVersesTextFilterBy(
            $bible,
            $text_fileset->hash_id,
            $this['book_id'],
            $verse_start,
            $verse_end,
            $chapter
        );
    }

    /**
     * @OA\Property(
     *   property="bible_name",
     *   title="bible_name",
     *   type="string",
     *   description="Bible name"
     * )
     */
    public function getBibleNameAttribute()
    {
        $bible = $this->bible;
        if (!$bible) {
            return '';
        }
        $ctitle = optional($bible->translations->where('language_id', $GLOBALS['i18n_id'])->first())->name;
        $vtitle = optional($bible->vernacularTranslation)->name;
        return ($vtitle ? $vtitle : $ctitle);
    }
}
