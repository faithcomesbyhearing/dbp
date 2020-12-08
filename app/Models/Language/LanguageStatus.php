<?php

namespace App\Models\Language;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Language\LanguageStatus
 *
 * @property-read \App\Models\Language\LanguageStatus $language_status
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @method static LanguageStatus whereId($value)
 * @method static LanguageStatus whereTitle($value)
 * @method static LanguageStatus whereDescription($value)
 *
 * @OA\Schema (
 *     type="object",
 *     description="",
 *     title="Language Status",
 *     @OA\Xml(name="LanguageStatus")
 * )
 *
 */
class LanguageStatus extends Model
{
    protected $connection = 'dbp';
    protected $table = 'language_status';
    protected $fillable = ['id','title','description'];
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="string",
     *   example="0",
     *   description="The id for the language status",
     *   minimum=0
     * )
     *
     */
    protected $id;

    /**
     *
     * @OA\Property(
     *   title="title",
     *   type="string",
     *   example="International",
     *   description="The title"
     * )
     *
     */
    protected $title;

    /**
     *
     * @OA\Property(
     *   title="description",
     *   type="string",
     *   example="The language is widely used between nations in trade, knowledge exchange, and international policy.",
     *   description="The description"
     * )
     *
     */
    protected $description;

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}
