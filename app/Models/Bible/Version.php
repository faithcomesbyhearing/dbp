<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Bible\Version
 *
 * @property-read \App\Models\Bible\Version $version
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="Information regarding bible version",
 *     title="Version",
 *     @OA\Xml(name="Version")
 * )
 *
 */
class Version extends Model
{
    protected $connection = 'dbp';
    protected $table = 'version';
    protected $keyType = 'string';
    protected $fillable = ['id', 'name', 'english_name'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="string",
     * )
     *
     * @property int $id
     * @method static Version whereId($value)
     *
     */
    protected $id;

    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   example="ab",
     *   description="The name for the version"
     * )
     *
     * @property string $name
     * @method static Version whereName($value)
     *
     */

    protected $name;

    /**
     *
     * @OA\Property(
     *   title="english_name",
     *   type="string",
     *   example="ab",
     *   description="The english_name for the version"
     * )
     *
     * @property string $english_name
     * @method static Version whereEnglishName($value)
     *
     */
    protected $english_name;

    public function scopeAll($query, $english_id)
    {
        return $query
            ->select(['id', 'name', 'english_name'])
            ->whereIn('id', function ($query) use ($english_id) {
                $query->select(\DB::raw('SUBSTR(bt.bible_id, 4, 3)'))
                    ->from('bible_translations as bt')
                    ->where('bt.language_id', $english_id);
            });
    }

    public function scopeFilterableByNameOrEnglishName($query, $search_text)
    {
        return $query->when($search_text, function ($query) use ($search_text) {
            $formatted_name = "+$search_text*";
            $query->whereRaw(
                'match (version.name, version.english_name) against (? IN BOOLEAN MODE)',
                [$formatted_name]
            );
        });
    }
}
