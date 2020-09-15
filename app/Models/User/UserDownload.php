<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

class UserDownload extends Model
{
    protected $connection = 'dbp_users';
    public $table         = 'user_downloads';
    protected $fillable  = ['id','user_id','fileset_id', 'book_id', 'chapter', 'created_at'];
    const UPDATED_AT = null;

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The unique id for the userDownload",
     *   minimum=1
     * )
     *
     * @method static UserDownload whereId($value)
     */
    private $id;

    /**
     *
     * @OA\Property(
     *   title="playlist_id",
     *   type="integer",
     *   description="The fileset id"
     * )
     *
     * @property string $fileset_id
     */
    
    protected $fileset_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/User/properties/id")
     * @method static UserDownload whereUserId($value)
     * @property string $user_id
     */
    protected $user_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Book/properties/id")
     * @method static UserDownload whereBookId($value)
     * @property string $book_id
     *
     */
    protected $book_id;

    /**
     *
     * @OA\Property(
     *   title="chapter",
     *   type="integer",
     *   description="This field in combination with the book_id and bible_id can be used to store a user's donwload"
     * )
     *
     * @method static UserDownload whereChapter($value)
     * @property integer $chapter
     */
    protected $chapter;

    protected $created_at;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fileset()
    {
        return $this->belongsTo(BibleFileset::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
