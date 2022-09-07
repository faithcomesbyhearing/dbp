<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Expression;
use App\Models\Bible\Book;

/**
 * App\Models\Bible\BibleBook
 *
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Bible Book Model stores the vernacular book titles and chapters",
 *     title="Bible Book",
 *     @OA\Xml(name="BibleBook")
 * )
 *
 */
class BibleBook extends Model
{
    public const BOOK_ORDER_COLUMN = 'book_order_column';

    protected $connection = 'dbp';
    protected $table = 'bible_books';
    public $incrementing = false;
    public $fillable = ['abbr','book_id', 'name', 'name_short', 'chapters', 'book_seq'];
    public $hidden = ['created_at','updated_at','bible_id'];


    /**
     *
     * @OA\Property(ref="#/components/schemas/Bible/properties/id")
     * @method static BibleBook whereBibleId($value)
     * @property string $bible_id
     *
     */
    protected $bible_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Book/properties/id")
     * @method static BibleBook whereBookId($value)
     * @property string $book_id
     *
     */
    protected $book_id;

    /**
     *
     * @OA\Property(
     *     title="name",
     *     description="The name of the book in the language of the bible",
     *     type="string",
     *     example="1 Corinthians",
     *     maxLength=191
     * )
     *
     * @method static BibleBook whereName($value)
     * @property string $name
     *
     */
    protected $name;

    /**
     *
     * @OA\Property(
     *     title="name_short",
     *     description="If the vernacular name has an abbreviated form, it will be stored hre",
     *     type="string",
     *     example="1 Corinthians",
     *     maxLength=191
     * )
     *
     * @method static BibleBook whereNameShort($value)
     * @property string $name_short
     *
     */
    protected $name_short;

    /**
     *
     * @OA\Property(
     *     title="chapters",
     *     description="A string of the chapters in the book separated by a comma",
     *     type="string",
     *     example="1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16",
     *     maxLength=491
     * )
     *
     * @method static BibleBook whereChapters($value)
     * @property string $chapters
     *
     */
    protected $chapters;

    /**
     *
     * @OA\Property(
     *     title="book_seq",
     *     description="The ordering provided by the licensor in the USX file, this column
     *                  would likely populated for audio and video where there is no text",
     *     type="string",
     *     example="B07",
     *     maxLength=4
     * )
     *
     * @method static BibleBook whereBookSeq($value)
     * @property string $book_seq
     *
     */
    protected $book_seq;

    /**
     * Remove brackets from uncertain book names
     *
     * @param  string $name
     *
     * @return string
     */
    public function getNameAttribute($name)
    {
        return $this->attributes['name'] = trim($name, '[]');
    }

    public function getNameShortAttribute($name_short)
    {
        return $this->attributes['name_short'] = trim($name_short, '[]');
    }


    public function bible()
    {
        return $this->belongsTo(Bible::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Get collection of bible books sorted by bible versification or the book_seq column if it not empty.
     *
     * @param string $bible_id
     * @param string $book_id
     * @param string $bible_versification
     *
     * @return Collection
     */
    public static function getAllSortedByBookSeqOrVersification(
        string $bible_id,
        string $bible_versification,
        ?string $book_id = null
    ) : Collection {
        return BibleBook::select(
            'bible_books.bible_id',
            'bible_books.book_id',
            'bible_books.name',
            'bible_books.name_short',
            'bible_books.chapters',
            'bible_books.book_seq',
            self::getBookOrderSelectColumnExpressionRaw($bible_versification)
        )
            ->where('bible_id', $bible_id)
            ->when($book_id, function ($query) use ($book_id) {
                $query->where('book_id', $book_id);
            })
            ->join('books', 'books.id', 'bible_books.book_id')
            ->with('book')
            ->orderBy(self::BOOK_ORDER_COLUMN)
            ->get()
            ->flatten();
    }

    /**
     * Get the Expression to sort the bible book records WITH alias
     *
     * @param string $bible_versification
     * @param string $alias
     *
     * @return Expression
     */
    public static function getBookOrderSelectColumnExpressionRaw(
        string $bible_versification,
        string $alias = self::BOOK_ORDER_COLUMN
    ) : Expression {
        $case_sql = self::getBookOrderSql($bible_versification);

        return \DB::raw("$case_sql AS $alias");
    }

    /**
     * Get the Expression to sort the bible book records without alias
     *
     * @param string $bible_versification
     *
     * @return Expression
     */
    public static function getBookOrderExpressionRaw(string $bible_versification) : Expression
    {
        return \DB::raw(self::getBookOrderSql($bible_versification));
    }

    /**
     * Get the sql case string to sort the bible book records
     *
     * @param string $bible_versification
     *
     * @return string
     */
    public static function getBookOrderSql(string $bible_versification) : string
    {
        return \sprintf(
            'CASE
                WHEN bible_books.book_seq IS NOT NULL THEN bible_books.book_seq
                WHEN books.%s_order IS NOT NULL THEN books.%s_order
                ELSE bible_books.book_id
            END',
            $bible_versification,
            $bible_versification
        );
    }
}
