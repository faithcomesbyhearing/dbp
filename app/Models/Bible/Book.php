<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleBook;

/**
 * App\Models\Bible\Book
 * @mixin \Eloquent
 *
 * @property-read BookTranslation $currentTranslation
 * @property-read BookTranslation[] $translations
 * @property-read BookTranslation $vernacularTranslation
 * @property-read BookTranslation $translation
 * @method static Book whereVerses($value)
 * @method static Book whereTestamentOrder($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Bible\Bible[] $bible
 *
 * @method static Book whereId($value)
 * @property string $id
 * @method static Book whereIdUsfx($value)
 * @property string $id_usfx
 * @method static Book whereIdOsis($value)
 * @property string $id_osis
 * @method static Book whereProtestantOrder($value)
 * @property int $protestant_order
 * @method static Book whereBookOrder($value)
 * @property int $testament_order
 * @method static Book whereBookTestament($value)
 * @property string $book_testament
 * @method static Book whereBookGroup($value)
 * @property string $book_group
 * @method static Book whereChapters($value)
 * @property int|null $chapters
 * @property int|null $verses
 * @method static Book whereName($value)
 * @property string $name
 * @method static Book whereNotes($value)
 * @property string $notes
 * @method static Book whereDescription($value)
 * @property string $description
 * @method static Book whereCreatedAt($value)
 * @property \Carbon\Carbon|null $created_at
 * @method static Book whereUpdatedAt($value)
 * @property \Carbon\Carbon|null $updated_at
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Book model communicates information about the canonical books of the Bible",
 *     title="Book",
 *     @OA\Xml(name="Book")
 * )
 *
 */
class Book extends Model
{
    protected $connection = 'dbp';
    protected $table = 'books';
    public $incrementing = false;
    public $hidden = ['description','created_at','updated_at','notes'];
    protected $keyType = 'string';

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="string",
     *   description="The USFM 2.4 id for the books of the Bible",
     *   example="MAT",
     *   minLength=3,
     *   maxLength=3
     * )
     *
     *
     */
    protected $id;

    /**
     *
     * @OA\Property(
     *   title="id_usfx",
     *   type="string",
     *   description="The usfx id for the books of the Bible",
     *   minLength=2,
     *   maxLength=2
     * )
     *
     *
     */
    protected $id_usfx;

    /**
     *
     * @OA\Property(
     *   title="id_osis",
     *   type="string",
     *   description="The OSIS id for the books of the Bible",
     *   minLength=2,
     *   maxLength=12
     * )
     *
     *
     */
    protected $id_osis;

    /**
     *
     * @OA\Property(
     *   title="protestant_order",
     *   type="integer",
     *   description="The standard book order for the `protestant_order` in ascending order from Genesis onwards",
     *   minimum=0
     * )
     *
     *
     */
    protected $protestant_order;

    /**
     *
     * @OA\Property(
     *   title="testament_order",
     *   type="integer",
     *   description="The standard book order within a testament in ascending order from Genesis to Malachi, and Matthew to Revelations",
     *   minimum=0
     * )
     *
     *
     */
    protected $testament_order;

    /**
     *
     * @OA\Property(
     *   title="book_testament",
     *   type="string",
     *   description="A short code identifying the testament containing the book",
     *   enum={"OT","NT","AP"},
     *   minLength=2,
     *   maxLength=2
     * )
     *
     *
     */
    protected $book_testament;

    /**
     *
     * @OA\Property(
     *   title="book_group",
     *   type="string",
     *   description="An english name for the section of books that current book can be categorized in",
     *   enum={"Historical Books","Pauline Epistles","General Epistles","Apostolic History","Minor Prophets","Major Prophets","The Law","Wisdom Books","Gospels","Apocalypse"}
     * )
     *
     *
     */
    protected $book_group;

    /**
     *
     * @OA\Property(
     *   title="chapters",
     *   type="array",
     *   nullable=true,
     *   description="The book's number of chapters",
     *   @OA\Items(type="integer")
     * )
     *
     *
     */
    protected $chapters;

    /**
     *
     * @OA\Property(
     *   title="verses",
     *   type="integer",
     *   nullable=true,
     *   description="The book's number of verses"
     * )
     *
     *
     */
    protected $verses;

    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   description="The English name of the book"
     * )
     *
     *
     */
    protected $name;

    /**
     *
     * @OA\Property(
     *   title="notes",
     *   type="string",
     *   description="Any archivist notes about the book"
     * )
     *
     *
     */
    protected $notes;

    /**
     *
     * @OA\Property(
     *   title="description",
     *   type="string",
     *   description="The book's description"
     * )
     *
     *
     */
    protected $description;

    /**
     *
     * @OA\Property(
     *   title="created_at",
     *   type="string",
     *   description="The timestamp for the books creation"
     * )
     *
     *
     */
    protected $created_at;

    /**
     *
     * @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp for the last update of the book"
     * )
     *
     *
     */
    protected $updated_at;

    public function scopeSelectByID($query, $id)
    {
        $query->where('id', $id)->orWhere('id_osis', $id)->orWhere('id_usfx', $id);
    }

    public function scopeFilterByTestament($query, $testament)
    {
        $query->when($testament, function ($q) use ($testament) {
            if (\in_array('NT', $testament)) {
                $q->where('books.book_testament', 'NT');
            }
            if (\in_array('OT', $testament)) {
                $q->where('books.book_testament', 'OT');
            }
        });
    }

    public function getTestamentFirstLetter($book_testament = null)
    {
        if (is_null($book_testament)) {
            $book_testament = $this->book_testament;
        }

        if (!empty($book_testament)) {
            return substr($book_testament, 0, 1);
        }

        return null;
    }

    public function translations()
    {
        return $this->hasMany(BookTranslation::class, 'book_id');
    }

    public function translation($language_id)
    {
        return $this->hasMany(BookTranslation::class, 'book_id')->where('language_id', $language_id);
    }

    public function currentTranslation()
    {
        return $this->hasOne(BookTranslation::class, 'book_id')->where('language_id', $GLOBALS['i18n_id']);
    }

    public function vernacularTranslation()
    {
        return $this->hasOne(BookTranslation::class, 'book_id')->where('language_id', $this->language_id);
    }

    public function bible()
    {
        return $this->belongsToMany(Bible::class, 'bible_books');
    }

    public function bibleBooks()
    {
        return $this->hasMany(BibleBook::class);
    }

    /**
     * Validate if the Book entity has a given column
     *
     * @param string $versification
     *
     * @return bool
     */
    public static function hasVersificationColumn(string $versification) : bool
    {
        return \Schema::connection('dbp')->hasColumn('books', $versification . '_order');
    }

    /**
     * Get the Book records from a given fileset object
     *
     * @param BibleFileset $fileset
     * @param string $versification
     * @param string $fileset_type
     *
     * @return Collection
     */
    public static function getActiveBooksFromFileset(
        BibleFileset $fileset,
        string $versification,
        string $fileset_type = null
    ) : Collection {
        $book_order_column = self::hasVersificationColumn($versification)
            ? $versification
            : 'protestant';

        $is_plain_text = BibleVerse::where('hash_id', $fileset->hash_id)->exists();

        return \DB::connection('dbp')
            ->table('bible_filesets as fileset')
            ->where('fileset.id', $fileset->id)
            ->leftJoin(
                'bible_fileset_connections as connection',
                'connection.hash_id',
                'fileset.hash_id'
            )
            ->leftJoin('bibles', 'bibles.id', 'connection.bible_id')
            ->when($fileset_type, function ($q) use ($fileset_type) {
                $q->where('set_type_code', $fileset_type);
            })
            ->join('bible_books', function ($join) {
                $join->on('bible_books.bible_id', 'bibles.id');
            })
            ->rightJoin('books', 'books.id', 'bible_books.book_id')
            ->when(!$is_plain_text, function ($query) use ($fileset) {
                $query = self::compareFilesetToFileTableBooks($query, $fileset->hash_id);
            })
            ->select([
                'books.id',
                'books.id_usfx',
                'books.id_osis',
                'books.book_testament',
                'books.testament_order',
                'books.book_group',
                'bible_books.chapters',
                'bible_books.name',
                'bible_books.name_short',
                'books.protestant_order',
                BibleBook::getBookOrderSelectColumnExpressionRaw($book_order_column)
            ])
            ->orderBy(BibleBook::BOOK_ORDER_COLUMN)
            ->get();
    }

    /**
     *
     * @param $query
     * @param $hashId
     */
    public static function compareFilesetToFileTableBooks(Builder $query, string $hashId) : Builder
    {
        // If the fileset referencesade dbp.bible_files from that table
        $fileset_book_ids = \DB::connection('dbp')
            ->table('bible_files')
            ->where('hash_id', $hashId)
            ->select(['book_id'])
            ->distinct()
            ->get()
            ->pluck('book_id');

        return $query->whereIn('bible_books.book_id', $fileset_book_ids);
    }
}
