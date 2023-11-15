<?php

namespace App\Models\User\Study;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleBook;
use App\Models\Bible\Book;
use App\Services\Bibles\BibleFilesetService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Awobaz\Compoships\Compoships;

/**
 * App\Models\User\Highlight
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $user_id
 * @property string $bible_id
 * @property string $book_id
 * @property int $chapter
 * @property string|null $highlighted_color
 * @property string $verse_start
 * @property int $verse_end
 * @property int $verse_sequence
 * @property string|null $project_id
 * @property int $highlight_start
 * @property int $highlighted_words
 *
 * @method static Highlight whereId($value)
 * @method static Highlight whereUserId($value)
 * @method static Highlight whereBibleId($value)
 * @method static Highlight whereBookId($value)
 * @method static Highlight whereChapter($value)
 * @method static Highlight whereHighlightedColor($value)
 * @method static Highlight whereVerseStart($value)
 * @method static Highlight whereProjectId($value)
 * @method static Highlight whereHighlightStart($value)
 * @method static Highlight whereHighlightedWords($value)
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Highlight model",
 *     title="Highlight",
 *     @OA\Xml(name="Highlight")
 * )
 *
 */
class Highlight extends Model
{
    use Compoships;
    use UserAnnotationTrait;

    protected $connection = 'dbp_users';
    public $table = 'user_highlights';
    protected $fillable = [
        'user_id',
        'v2_id',
        'bible_id',
        'book_id',
        'project_id',
        'chapter',
        'verse_start',
        'verse_end',
        'verse_sequence',
        'highlight_start',
        'highlighted_words',
        'highlighted_color'
    ];
    protected $hidden = ['user_id', 'project_id'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The highlight id",
     *   minimum=0
     * )
     *
     */
    protected $id;
    /**
     *
     * @OA\Property(
     *   title="user_id",
     *   type="string",
     *   description="The user that created the highlight"
     * )
     *
     */
    protected $user_id;

    /**
     * @OA\Property(ref="#/components/schemas/Bible/properties/id")
     */
    protected $bible_id;
    /**
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
     * @OA\Property(
     *   title="highlighted_color",
     *   type="string",
     *   description="The highlight's highlighted color in either hex, rgb, or rgba notation.",
     *   example="#4488bb"
     * )
     *
     */
    protected $highlighted_color;

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
     * @OA\Property(ref="#/components/schemas/BibleFile/properties/verse_end")
     */
    protected $verse_end;

    /**
     *
     * @OA\Property(type="string")
     * @method static Highlight whereReference($value)
     */
    protected $reference;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Project/properties/id")
     */
    protected $project_id;
    /**
     *
     * @OA\Property(
     *   title="highlight_start",
     *   type="integer",
     *   description="The number of words from the beginning of the verse to start the highlight at. For example, if the verse Genesis 1:1 had a `highlight_start` of 4 and a highlighted_words equal to 2. The result would be: In the beginning `[God created]` the heavens and the earth.",
     *   minimum=0
     * )
     *
     */
    protected $highlight_start;
    /**
     *
     * @OA\Property(
     *   title="highlighted_words",
     *   type="integer",
     *   description="The number of words being highlighted. For example, if the verse Genesis 1:1 had a `highlight_start` of 4 and a highlighted_words equal to 2. The result would be: In the beginning `[God created]` the heavens and the earth.",
     * )
     *
     */
    protected $highlighted_words;



    public function color()
    {
        return $this->belongsTo(HighlightColor::class, 'highlighted_color', 'id');
    }

    public function bible()
    {
        return $this->belongsTo(Bible::class);
    }

    public function bibleBook()
    {
        return $this->hasOne(BibleBook::class, ['book_id', 'bible_id'], ['book_id', 'bible_id']);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function tags()
    {
        return $this->hasMany(AnnotationTag::class, 'highlight_id', 'id');
    }

    public function getFilesetInfoAttribute()
    {
        $chapter = $this['chapter'];
        $verse_start = $this['verse_start'];
        $verse_end = $this['verse_end'] ?? $verse_start;

        $bible = $this->bible;
        if (!$bible) {
            return collect([]);
        }
        $filesets = $bible->filesets;
        if (!$filesets) {
            return collect([]);
        }

        $fileset_types = collect(['audio_stream_drama', 'audio_drama', 'audio_stream', 'audio']);

        $testament = $this->bibleBook && $this->bibleBook->book
            ? $this->bibleBook->book->book_testament
            : '';

        $text_fileset = $this->getTextFilesetRelatedByTestament($testament);

        $audio_filesets = $filesets->filter(function ($fs) {
            return Str::contains($fs->set_type_code, 'audio');
        });

        foreach ($audio_filesets as $fileset) {
            $fileset->addMetaRecordsAsAttributes();
        }

        $available_filesets = $fileset_types->map(
            function ($fileset) use ($audio_filesets, $testament) {
                return BibleFilesetService::getFilesetFromValidFilesets($audio_filesets, $fileset, $testament);
            }
        )->filter(function ($item) {
            return $item;
        });

        $verse_text = '';
        if ($text_fileset) {
            $verse_text = BibleFilesetService::getRangeVersesTextFilterBy(
                $bible,
                $text_fileset->hash_id,
                $this['book_id'],
                $verse_start,
                $verse_end,
                $chapter
            );
        }

        return collect([
            'verse_text' => $verse_text,
            'audio_filesets' => $available_filesets
        ]);
    }

    public static function checkAndReturnColorPreference(Highlight $highlight, string $color_preference = 'rgba')
    {
        $color = null;

        if ($color_preference === 'hex' && $highlight->color) {
            $color = '#' . $highlight->color->hex;
        }
        if ($color_preference === 'rgb' && $highlight->color) {
            $color = 'rgb(' . $highlight->color->red . ',' . $highlight->color->green . ',' . $highlight->color->blue . ')';
        }
        if ($color_preference === 'rgba' && $highlight->color) {
            $color = 'rgba(' . $highlight->color->red . ',' . $highlight->color->green . ',' . $highlight->color->blue . ',' . $highlight->color->opacity . ')';
        }

        return $color;
    }

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
}
