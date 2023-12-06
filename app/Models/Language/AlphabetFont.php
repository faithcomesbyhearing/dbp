<?php

namespace App\Models\Language;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Language\AlphabetFont
 *
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="Alphabet Font",
 *     title="Alphabet Font",
 *     @OA\Xml(name="AlphabetFont")
 * )
 *
 */
class AlphabetFont extends Model
{
    protected $connection = 'dbp';
    protected $table = 'alphabet_fonts';
    protected $hidden = ['iso','created_at','updated_at'];

    /**
    *
    * @OA\Property(
    *     title="Alphabet Font ID",
    *     description="The incrementing numeric id for the alphabet fonts",
    *     type="integer",
    *     example=7
    * )
    *
    * @property int $id
    * @method static AlphabetFont whereId($value)
    */
    protected $id;

    /*
    * @property string $script_id
    * @method static AlphabetFont whereScriptId($value)
    */
    protected $script_id;

    /**
    * @property string $font_name
    * @method static AlphabetFont whereFontName($value)
    *
    * @OA\Property(
    *     title="Alphabet Font Name",
    *     description="The Font Name",
    *     type="string",
    *     maxLength=191,
    *     example="Noto Naskh Arabic"
    * )
    *
    */
    protected $font_name;

    /**
    * @property string $font_filename
    * @method static AlphabetFont whereFontFilename($value)
    *
    * @OA\Property(
    *     title="Alphabet Font File Name",
    *     description="The File name for the font",
    *     type="string",
    *     maxLength=191,
    *     example="NotoNaskhArabic-Regular"
    * )
    *
    */
    protected $font_filename;

    /**
    * @property int|null $font_weight
    * @method static AlphabetFont whereFontWeight($value)
    *
    * @OA\Property(
    *     title="Alphabet Font Weight",
    *     description="The boldness of the font",
    *     nullable=true,
    *     type="integer",
    *     minimum=100,
    *     example=400
    * )
    *
    */
    protected $font_weight;

    /**
    * @property string|null $copyright
    * @method static AlphabetFont whereCopyright($value)
    *
    * @OA\Property(
    *     title="Alphabet copyright",
    *     description="The copyright of the font if any",
    *     type="string",
    *     nullable=true,
    *     maxLength=191,
    *     example="Creative Commons"
    * )
    *
    */
    protected $copyright;

    /**
    * @property string|null $url
    * @method static AlphabetFont whereUrl($value)
    *
    * @OA\Property(
    *     title="Alphabet URL",
    *     description="The url to the font file",
    *     type="string",
    *     example="https://cdn.example.com/resources/fonts/NotoNaskhArabic-Regular.ttf"
    * )
    *
    */
    protected $url;

    /**
    * @property string|null $notes
    * @method static AlphabetFont whereNotes($value)
    *
    * @OA\Property(
    *     title="Notes",
    *     description="Any notes for the font file name",
    *     type="string",
    *     example="notes specific to this font",
    *     nullable=true
    * )
    *
    */
    protected $notes;

    /**
    * @property int $italic
    * @method static AlphabetFont whereItalic($value)
    *
    * @OA\Property(
    *     title="Italic",
    *     description="Determines if the font file contains or supports italics",
    *     type="boolean",
    *     nullable=true,
    *     example=false
    * )
    *
    */
    protected $italic;

    public function alphabet()
    {
        return $this->belongsTo(Alphabet::class);
    }

    public function scopeFilterById($query, $id)
    {
        return $query->when($id, function ($q) use ($id) {
            $q->whereId($id);
        });
    }
    public function scopeFilterByFileName($query, $font_name)
    {
        return $query->when($font_name, function ($q) use ($font_name) {
            $q->whereFontName($font_name);
        });
    }
}
