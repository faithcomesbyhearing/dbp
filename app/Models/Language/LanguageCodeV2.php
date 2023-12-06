<?php

namespace App\Models\Language;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Language\LanguageCode
 *
 * @property-read \App\Models\Language\Language $language
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="Information regarding alternative language coding systems",
 *     title="Language Codes",
 *     @OA\Xml(name="LanguageCode")
 * )
 *
 */
class LanguageCodeV2 extends Model
{
    protected $connection = 'dbp';
    protected $table = 'language_codes_v2';
    protected $fillable = ['id', 'language_ISO_639_3_id', 'family_id'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="string",
     * )
     *
     * @property int $id
     * @method static LanguageCodeV2 whereId($value)
     *
     */
    protected $id;
    /**
     *
     * @OA\Property(
     *   title="language_ISO_639_3_id",
     *   type="string",
     * )
     *
     * @property string $language_ISO_639_3_id
     *
     */
    protected $language_ISO_639_3_id;
    /**
     *
     * @OA\Property(
     *   title="family_id",
     *   type="string",
     *   description="The family_id of the language"
     * )
     *
     * @property string $family_id
     * @method static LanguageCodeV2 whereFamilyId($value)
     *
     */
    protected $family_id;
    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   example="ab",
     *   description="The name for the language"
     * )
     *
     * @property string $name
     * @method static LanguageCodeV2 whereName($value)
     *
     */
    protected $name;
    /**
     *
     * @OA\Property(
     *   title="english_name",
     *   type="string",
     *   example="ab",
     *   description="The english_name for the language"
     * )
     *
     * @property string $english_name
     * @method static LanguageCodeV2 whereEnglishName($value)
     *
     */
    protected $english_name;

    /**
     *
     * @property \Carbon\Carbon $created_at
     * @method static LanguageCodeV2 whereCreatedAt($value)
     *
     */
    protected $created_at;

    /**
     *
     * @property \Carbon\Carbon $updated_at
     * @method static LanguageCodeV2 whereUpdatedAt($value)
     *
     */
    protected $updated_at;

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}
