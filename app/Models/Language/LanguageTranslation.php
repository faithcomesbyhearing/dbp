<?php

namespace App\Models\Language;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use App\Models\Language\Language;

/**
 * App\Models\Language\LanguageTranslation
 *
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="Information regarding language translations",
 *     title="Language Translation",
 *     @OA\Xml(name="LanguageTranslation")
 * )
 *
 */
class LanguageTranslation extends Model
{
    protected $connection = 'dbp';
    protected $hidden = ['language_source_id','created_at','updated_at','priority','description','id'];
    protected $table = 'language_translations';

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The incrementing id of the language",
     *   minimum=0
     * )
     *
     * @method static LanguageTranslation whereId($value)
     * @property int $id
     */
    protected $id;
    /**
     *
     * @OA\Property(
     *   title="language_source_id",
     *   type="integer",
     *   example=17,
     *   description="The incrementing id of the language_source",
     *   minimum=0
     * )
     *
     * @method static LanguageTranslation whereLanguageSourceId($value)
     * @property int $language_source_id
     */
    protected $language_source_id;
    /**
     *
     * @OA\Property(
     *   title="language_translation_id",
     *   type="integer",
     *   example=68,
     *   description="The incrementing id of the language_translation",
     *   minimum=0
     * )
     *
     * @method static LanguageTranslation whereLanguageTranslationId($value)
     * @property int $language_translation_id
     */
    protected $language_translation_id;
    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   example="Abkasies",
     *   description="The language translation name",
     *   minimum=0
     * )
     *
     * @method static LanguageTranslation whereName($value)
     * @property string $name
     */
    protected $name;

    /**
     * @OA\Property(
     *     title="Priority",
     *     description="The priority of the language translation",
     *     type="integer",
     *     example=0,
     *     minimum=0,
     *     maximum=255
     * )
     *
     * @property string $description
     * @method static whereDescription($value)
     */
    protected $priority;

    /**
     *
     * @OA\Property(
     *   title="created_at",
     *   type="string",
     *   description="The timestamp at which the translation was created at"
     * )
     *
     * @method static LanguageTranslation whereCreatedAt($value)
     * @property \Carbon\Carbon|null $created_at
     */
    protected $created_at;
    /**
     *
     * @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp at which the translation was last updated at"
     * )
     *
     * @method static LanguageTranslation whereUpdatedAt($value)
     * @property \Carbon\Carbon|null $updated_at
     */
    protected $updated_at;

    /**
     * Get translation iso
     *
     * @return string
     */
    public function getIsoTranslationAttribute()
    {
        return $this->translationIso->iso ?? '';
    }

    /**
     * Get source iso
     *
     * @return string
     */
    public function getIsoSourceAttribute()
    {
        return $this->sourceIso->iso ?? '';
    }

    public function translationIso()
    {
        return $this->belongsTo(Language::class, 'language_translation_id', 'id')->select(['iso','id']);
    }

    public function sourceIso()
    {
        return $this->belongsTo(Language::class, 'language_source_id', 'id')->select(['iso','id']);
    }

    public function scopeHasPriority(Builder $query) : Builder
    {
        $from_table = getAliasOrTableName($query->getQuery()->from);

        return $query->where($from_table.'.priority', '=', function ($query) use ($from_table) {
            $query->select(\DB::raw('MAX(`priority`)'))
                ->from('language_translations as lang_trans_prior')
                ->whereColumn('lang_trans_prior.language_source_id', '=', $from_table.'.language_source_id')
                ->whereColumn(
                    'lang_trans_prior.language_translation_id',
                    '=',
                    $from_table.'.language_translation_id'
                )
                ->limit(1);
        })
        ;
    }

    public function scopeIsContentAvailable(
        Builder $query,
        Collection $access_group_ids,
        array $bible_fileset_filters = []
    ) : Builder {
        $from_table = getAliasOrTableName($query->getQuery()->from);

        return $query->whereExists(
            function (QueryBuilder $query) use ($access_group_ids, $from_table, $bible_fileset_filters) {
                $query = Language::buildContentAvailabilityQuery($query, $access_group_ids, $bible_fileset_filters);

                return $query->whereColumn(
                    $from_table.'.language_source_id',
                    '=',
                    'b.language_id'
                )->whereIn('agf.access_group_id', $access_group_ids);
            }
        );
    }
}
