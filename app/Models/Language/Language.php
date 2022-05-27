<?php

namespace App\Models\Language;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleFilesetConnection;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\Video;
use App\Models\Country\CountryLanguage;
use App\Models\Country\CountryRegion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use App\Models\Country\Country;
use App\Models\Resource\Resource;

/**
 * App\Models\Language\Language
 *
 * @mixin \Eloquent
 * @property-read Alphabet[] $alphabets
 * @property-read Bible[] $bibleCount
 * @property-read Bible[] $bibles
 * @property-read Country[] $countries
 * @property-read LanguageCode[] $codes
 * @property-read LanguageDialect[] $dialects
 * @property-read LanguageTranslation $currentTranslation
 * @property-read LanguageClassification[] $classifications
 * @property-read Video[] $films
 * @property-read AlphabetFont[] $fonts
 * @property-read LanguageCode $iso6392
 * @property-read Language[] $languages
 * @property-read LanguageDialect $parent
 * @property-read Country|null $primaryCountry
 * @property-read CountryRegion $region
 * @property-read \App\Models\Resource\Resource[] $resources
 * @property-read LanguageTranslation[] $translations
 *
 * @property int $id
 * @property string|null $glotto_id
 * @property string|null $iso
 * @property string $iso2B
 * @property string $iso2T
 * @property string $name
 * @property string $maps
 * @property string $development
 * @property string $use
 * @property string $location
 * @property string $area
 * @property number $population
 * @property string $notes
 * @property string $typology
 * @property string $description
 * @property string $latitude
 * @property string $longitude
 * @property string $status
 * @property string $country_id
 * @property string $rolv_code
 *
 * @method static Language whereId($value)
 * @method static Language whereGlottoId($value)
 * @method static Language whereIso($value)
 * @method static whereIso2b($value)
 * @method static whereIso2t($value)
 * @method static whereName($value)
 * @method static whereMaps($value)
 * @method static whereDevelopment($value)
 * @method static whereUse($value)
 * @method static whereLocation($value)
 * @method static whereArea($value)
 * @method static wherePopulation($value)
 * @method static whereNotes($value)
 * @method static whereTypology($value)
 * @method static whereDescription($value)
 * @method static whereLatitude($value)
 * @method static whereLongitude($value)
 * @method static whereStatus($value)
 * @method static whereCountryId($value)
 * @method static whereRolvCode($value)
 *
 * @OA\Schema (
 *     type="object",
 *     description="Language",
 *     title="Language",
 *     @OA\Xml(name="Language")
 * )
 *
 */
class Language extends Model
{
    const ENGLISH_ID = 6414;

    protected $connection = 'dbp';
    public $table = 'languages';
    protected $fillable = [
        'glotto_id',
        'iso',
        'name',
        'maps',
        'development',
        'use',
        'location',
        'area',
        'population',
        'population_notes',
        'notes',
        'typology',
        'writing',
        'description',
        'family_pk',
        'father_pk',
        'child_dialect_count',
        'child_family_count',
        'child_language_count',
        'latitude',
        'longitude',
        'pk',
        'status_id',
        'status_notes',
        'country_id',
        'scope',
        'rolv_code',
    ];

    /**
     * ID
     *
     * @OA\Property(
     *     title="id",
     *     description="The incrementing ID for the language",
     *     type="integer",
     *     example=6411
     * )
     *
     */
    protected $id;

    /**
     * Glotto ID
     *
     * @OA\Property(
     *     title="glotto_id",
     *     description="The glottolog ID for the language",
     *     type="string",
     *     example="stan1288",
     *     @OA\ExternalDocumentation(
     *         description="For more info please refer to the Glottolog",
     *         url="http://glottolog.org/"
     *     ),
     * )
     *
     *
     */
    protected $glotto_id;

    /**
     * Iso
     *
     * @OA\Property(
     *     title="iso",
     *     description="The iso 639-3 for the language",
     *     type="string",
     *     example="spa",
     *     maxLength=3,
     *     @OA\ExternalDocumentation(
     *         description="For more info",
     *         url="https://en.wikipedia.org/wiki/ISO_639-3"
     *     ),
     * )
     *
     *
     */
    protected $iso;

    /**
     * iso2B
     *
     *  OpenAPI Note: this property was removed from the documentation because the values are mostly null
     *     title="iso 2b",
     *     description="The iso 639-2, B variant for the language",
     *     type="string",
     *     example="spa",
     *     maxLength=3
     *
     */
    protected $iso2B;

    /**
     * iso2T
     *
     *  OpenAPI Note: this property was removed from the documentation because the values are mostly null
     *     title="iso 2t",
     *     description="The iso 639-2, T variant for the language",
     *     type="string",
     *     example="spa",
     *     maxLength=3
     * )
     *
     */
    protected $iso2T;

    /**
     *  OpenAPI Note: this property was removed from the documentation because the values are mostly null
     *     title="iso1",
     *     description="The iso 639-1 for the language",
     *     type="string",
     *     example="es",
     *     maxLength=3
     * )
     *
     */
    protected $iso1;

    /**
     * @OA\Property(
     *     title="Name",
     *     description="The name of the language",
     *     type="string",
     *     example="Spanish",
     *     maxLength=191
     * )
     *
     */
    protected $name;

    /**
     * @OA\Property(
     *     title="Maps",
     *     description="The general area where the language can be found",
     *     type="string",
     *     example="Andorra and France"
     * )
     *
     */
    protected $maps;

    /**
     *
     * @OA\Property(
     *     title="Development",
     *     description="The development of the growth of the language",
     *     type="string",
     *     example="Fully developed. Bible: 1553-2000."
     * )
     *
     */
    protected $development;

    /**
     * @OA\Property(
     *     title="use",
     *     description="The use of the language",
     *     type="string",
     *     example="60,000,000 L2 speakers."
     * )
     *
     */
    protected $use;

    /**
     * @OA\Property(
     *     title="Location",
     *     description="The location of the language",
     *     type="string"
     * )
     *
     */
    protected $location;

    /**
     * @OA\Property(
     *     title="Area",
     *     description="The area of the language",
     *     type="string",
     *     example="Central, south; Canary Islands. Also in Andorra, ..."
     * )
     *
     */
    protected $area;

    /**
     * @OA\Property(
     *     title="Population",
     *     description="The estimated number of people that speak the language",
     *     type="number",
     *     example=24900
     * )
     *
     */
    protected $population;

    /**
     * @OA\Property(
     *     title="Population Notes",
     *     description="Any notes regarding the estimated number of people",
     *     type="string",
     *     example="58,200,000 in United Kingdom (Crystal 2003). Population total all countries: 334,800,758."
     * )
     *
     */
    protected $population_notes;

    /**
     * @OA\Property(
     *     title="Notes",
     *     description="Any notes regarding the language",
     *     type="string",
     *     example="The Aragonese dialect of Spanish is different from Aragonese language [arg]. Christian."
     * )
     *
     */
    protected $notes;

    /**
     * @OA\Property(
     *     title="Typology",
     *     description="The language's Typology",
     *     type="string",
     *     example="SVO,prepositions,genitives, relatives after noun heads,articles, numerals before noun heads..."
     * )
     *
     */
    protected $typology;

    /**
     * @OA\Property(
     *     title="Description",
     *     description="The description of the language",
     *     type="string",
     *     example="language description"
     * )
     *
     */
    protected $description;

    /**
     * Note: Removed from API
     *     title="Latitude",
     *     description="A generalized latitude for the location of the language",
     *     type="string"
     * )
     *
     */
    protected $latitude;

    /**
     * Note: Removed from API
     *     title="Longitude",
     *     description="A generalized longitude for the location of the language",
     *     type="string"
     * )
     *
     */
    protected $longitude;

    /**
     * @OA\Property(
     *     title="Status",
     *     description="A status of the language",
     *     type="string",
     *     example="6a"
     * )
     *
     */
    protected $status;

    /**
     * @OA\Property(
     *     title="country_id",
     *     description="The primary country where the language is spoken",
     *     type="string",
     *     example="ES"
     * )
     *
     */
    protected $country_id;

    /**
     * @OA\Property(
     *     title="rolv_code",
     *     description="",
     *     type="string",
     *     example=""
     * )
     *
     */
    protected $rolv_code;

    public function scopeIncludeAutonymTranslation($query)
    {
        $query->leftJoin('language_translations as autonym', function ($join) {
            $priority_q = \DB::raw('(select max(`priority`) FROM language_translations WHERE language_translation_id = languages.id AND language_source_id = languages.id LIMIT 1)');
            $join->on('autonym.language_source_id', '=', 'languages.id')
              ->on('autonym.language_translation_id', '=', 'languages.id')
              ->where('autonym.priority', '=', $priority_q);
        });
    }

    public function scopeIncludeCurrentTranslation($query)
    {
        $query->leftJoin('language_translations as current_translation', function ($join) {
            $priority_q = \DB::raw('(select max(`priority`) from language_translations WHERE language_source_id = languages.id LIMIT 1)');
            $join->on('current_translation.language_source_id', 'languages.id')
                ->where('current_translation.language_translation_id', '=', $GLOBALS['i18n_id'])
                ->where('current_translation.priority', '=', $priority_q);
        });
    }

    public function scopeIncludeExtraLanguageTranslations($query, $include_translations)
    {
        return $query->when($include_translations, function ($query) {
            $query->with('translations');
        });
    }

    public function scopeIncludeExtraLanguages($query, $access_control_identifiers)
    {
        return $query->whereRaw('languages.id in (' . $access_control_identifiers . ')');
    }

    public function scopeIncludeCountryPopulation($query, $country)
    {
        return $query->when($country, function ($query) use ($country) {
            $query->leftJoin('country_language as country_population', function ($join) use ($country) {
                $join->on('country_population.language_id', 'languages.id')
                    ->where('country_population.country_id', $country);
            });
        });
    }

    public function scopeFilterableByIsoCode($query, $code)
    {
        return $query->when($code, function ($query) use ($code) {
            $query->whereIn('iso', explode(',', $code));
        });
    }

    public function scopeFilterableByCountry($query, $country)
    {
        return $query->when($country, function ($query) use ($country) {
            $query->whereHas('countries', function ($query) use ($country) {
                $query->where('country_id', $country);
            });
        });
    }

    public function scopeFilterableByName($query, $name, $full_word = false)
    {
        $name_expression = $full_word ? $name : '%'.$name.'%';
        return $query->when($name, function ($query) use ($name, $name_expression) {
            $query->where('languages.name', 'like', $name_expression);
        });
    }

    public function scopeFilterableByNameOrAutonym($query, $name)
    {
        $formatted_name = "+$name*";

        return $query->when($name, function ($query) use ($formatted_name) {
            $query->whereRaw('match (languages.name) against (? IN BOOLEAN MODE)', [$formatted_name]);
            $query->orWhereRaw('match (autonym.name) against (? IN BOOLEAN MODE)', [$formatted_name]);
        });
    }

    private function getLanguagesAccessGroupQuery($key, $set_type_code = null, $media = null)
    {
        $dbp_users = config('database.connections.dbp_users.database');
        $dbp_prod = config('database.connections.dbp.database');

        return \DB::table('languages as lang')
            ->select('lang.id as id')
            ->whereRaw('MATCH (lang.name) against (? IN BOOLEAN MODE)')
            ->whereExists(function ($query) use ($dbp_users, $dbp_prod, $key, $set_type_code, $media) {
                return $query->select(\DB::raw(1))
                    ->from($dbp_users . '.user_keys as uk')
                    ->join($dbp_users . '.access_group_api_keys as agak', 'agak.key_id', 'uk.id')
                    ->join($dbp_prod . '.access_group_filesets as agf', 'agf.access_group_id', 'agak.access_group_id')
                    ->join($dbp_prod . '.bible_fileset_connections as bfc', 'agf.hash_id', 'bfc.hash_id')
                    ->join($dbp_prod . '.bibles as b', 'bfc.bible_id', 'b.id')
                    ->whereColumn('lang.id', '=', 'b.language_id')
                    ->where('uk.key', $key)
                    ->when($set_type_code || $media, function ($query) use ($dbp_prod) {
                        $query->join($dbp_prod . '.bible_filesets as bfst', 'bfst.hash_id', 'bfc.hash_id');
                    })
                    ->when($set_type_code, function ($query) use ($set_type_code) {
                        $query->where('bfst.set_type_code', $set_type_code);
                    })
                    ->when($media, function ($query) use ($media) {
                        $set_type_code_array = BibleFileset::getsetTypeCodeFromMedia($media);
                        $query->whereIn('bfst.set_type_code', $set_type_code_array);
                    })
                    ;
            });
    }

    private function getLanguagesTranslationsAccessGroupQuery($key, $set_type_code = null, $media = null)
    {
        $dbp_users = config('database.connections.dbp_users.database');
        $dbp_prod = config('database.connections.dbp.database');

        return \DB::table('language_translations as lang_trans')
            ->select('lang_trans.language_source_id as id')
            ->whereRaw('lang_trans.language_source_id = lang_trans.language_translation_id')
            ->whereRaw('MATCH (lang_trans.name) against (? IN BOOLEAN MODE)')
            ->whereExists(function ($query) use ($dbp_users, $dbp_prod, $key, $set_type_code, $media) {
                return $query->select(\DB::raw(1))
                    ->from($dbp_users . '.user_keys as uk')
                    ->join($dbp_users . '.access_group_api_keys as agak', 'agak.key_id', 'uk.id')
                    ->join($dbp_prod . '.access_group_filesets as agf', 'agf.access_group_id', 'agak.access_group_id')
                    ->join($dbp_prod . '.bible_fileset_connections as bfc', 'agf.hash_id', 'bfc.hash_id')
                    ->join($dbp_prod . '.bibles as b', 'bfc.bible_id', 'b.id')
                    ->whereColumn('lang_trans.language_source_id', '=', 'b.language_id')
                    ->where('uk.key', $key)
                    ->where('lang_trans.priority', '=', function ($query) {
                        $query->select(\DB::raw('MAX(`priority`)'))
                            ->from('language_translations as lang_trans_prior')
                            ->whereColumn('lang_trans_prior.language_source_id', '=', 'lang_trans.language_source_id')
                            ->whereColumn('lang_trans_prior.language_source_id', '=', 'lang_trans_prior.language_translation_id')
                            ->limit(1);
                    })
                    ->when($set_type_code || $media, function ($query) use ($dbp_prod) {
                        $query->join($dbp_prod . '.bible_filesets as bfst', 'bfst.hash_id', 'bfc.hash_id');
                    })
                    ->when($set_type_code, function ($query) use ($set_type_code) {
                        $query->where('bfst.set_type_code', $set_type_code);
                    })
                    ->when($media, function ($query) use ($media) {
                        $set_type_code_array = BibleFileset::getsetTypeCodeFromMedia($media);
                        $query->whereIn('bfst.set_type_code', $set_type_code_array);
                    })
                    ;
            });
    }

    public function scopeFilterableByNameAndKey($query, $name, $key, $set_type_code = null, $media = null)
    {
        $formatted_name = "+$name*";

        $lang = $this->getLanguagesAccessGroupQuery($key, $set_type_code, $media);
        $lang_trans = $this->getLanguagesTranslationsAccessGroupQuery($key, $set_type_code, $media);

        $set_type_code_array = BibleFileset::getsetTypeCodeFromMedia($media);

        $lang_union_sql = \DB::table($lang)
            ->unionAll($lang_trans)
            ->toSql();

        return $query->join(
            \DB::raw(
                "(
                    SELECT lang_and_trans_group.id
                    FROM ($lang_union_sql) as lang_and_trans_group
                    GROUP BY lang_and_trans_group.id
                ) as languages_and_translations"
            ),
            'languages_and_translations.id',
            '=',
            'languages.id'
        )->leftJoin('language_translations as autonym', function ($join) {
            $join->on('autonym.language_source_id', '=', 'languages.id')
                ->on('autonym.language_translation_id', '=', 'languages.id')
                ->where('autonym.priority', '=', function ($autonym_query) {
                    $autonym_query->select(\DB::raw('MAX(`priority`)'))
                        ->from('language_translations')
                        ->whereColumn('language_translation_id', '=', 'languages.id')
                        ->whereColumn('language_source_id', '=', 'languages.id')
                        ->limit(1);
                });
        })->leftJoin('language_translations as current_translation', function ($join) {
            $join->on('current_translation.language_source_id', 'languages.id')
                ->whereRaw('current_translation.language_translation_id = ?')
                ->where('current_translation.priority', '=', function ($current_translation_query) {
                    $current_translation_query->select(\DB::raw('MAX(`priority`)'))
                        ->from('language_translations')
                        ->whereColumn('language_source_id', '=', 'languages.id')
                        ->limit(1);
                });
        })
        ->addBinding($formatted_name)
        ->addBinding($key)
        ->when($set_type_code, function ($query) use ($set_type_code) {
            $query->addBinding($set_type_code);
        })
        ->when($media, function ($query) use ($set_type_code_array) {
            $query->addBinding($set_type_code_array);
        })
        ->addBinding($formatted_name)
        ->addBinding($key)
        ->when($set_type_code, function ($query) use ($set_type_code) {
            $query->addBinding($set_type_code);
        })
        ->when($media, function ($query) use ($set_type_code_array) {
            $query->addBinding($set_type_code_array);
        })
        ->addBinding($GLOBALS['i18n_id']);
    }

    public function scopeWithRequiredFilesets($query, $type_filters)
    {
        $organization_id = $type_filters['organization_id'];
        $media = $type_filters['media'];
        $key = $type_filters['key'];

        return $query->whereHas('filesets', function ($query_filesets) use ($key, $organization_id, $media) {
            if ($organization_id) {
                $query_filesets->whereHas('copyright', function ($query_filesets) use ($organization_id) {
                    $query_filesets->where('organization_id', $organization_id);
                });
            }
            $query_filesets->join('bible_filesets', 'bible_filesets.hash_id', 'bible_fileset_connections.hash_id');
            $query_filesets->where('bible_filesets.asset_id', 'dbp-prod');
            if ($media) {
                $query_filesets->where('bible_filesets.set_type_code', 'LIKE', $media . '%');
            } else {
                $query_filesets->where('bible_filesets.set_type_code', '!=', 'text_format');
            }

            $query_filesets->isContentAvailable($key);
        });
    }
    
    public function population()
    {
        return CountryLanguage::where('language_id', $this->id)->select('language_id', 'population')->count();
    }

    public function alphabets()
    {
        return $this->belongsToMany(Alphabet::class, 'alphabet_language', 'language_id', 'script_id')->distinct();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany(LanguageTranslation::class, 'language_source_id', 'id')->orderBy('priority', 'desc');
    }

    public function translation()
    {
        return $this->hasOne(LanguageTranslation::class, 'language_source_id', 'id')->orderBy('priority', 'desc')->select(['language_source_id','name','priority']);
    }

    public function autonym()
    {
        return $this->hasOne(LanguageTranslation::class, 'language_source_id')
                    ->where('language_translation_id', $this->id)
                    ->orderBy('priority', 'desc');
    }

    public function currentTranslation()
    {
        return $this->hasOne(LanguageTranslation::class, 'language_source_id')->where('language_translation_id', $GLOBALS['i18n_id']);
    }

    public function countries()
    {
        return $this->belongsToMany(Country::class, 'country_language');
    }

    public function primaryCountry()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id', 'countries');
    }

    public function region()
    {
        return $this->hasOne(CountryRegion::class, 'country_id');
    }

    public function fonts()
    {
        return $this->hasMany(AlphabetFont::class);
    }

    public function bibles()
    {
        return $this->hasMany(Bible::class);
    }

    public function filesets()
    {
        return $this->hasManyThrough(BibleFilesetConnection::class, Bible::class, 'language_id', 'bible_id', 'id', 'id');
    }

    public function bibleCount()
    {
        return $this->hasMany(Bible::class);
    }

    public function resources()
    {
        return $this->hasMany(Resource::class)->has('links');
    }

    public function films()
    {
        return $this->hasMany(Video::class);
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class);
    }

    public function codes()
    {
        return $this->hasMany(LanguageCode::class, 'language_id', 'id');
    }

    public function iso6392()
    {
        return $this->hasOne(LanguageCode::class);
    }

    public function classifications()
    {
        return $this->hasMany(LanguageClassification::class);
    }

    public function dialects()
    {
        return $this->hasMany(LanguageDialect::class, 'language_id', 'id');
    }

    public function parent()
    {
        return $this->hasOne(LanguageDialect::class, 'dialect_id', 'id');
    }

    /**
     * Get the query to filter the languages by available bible fileset
     * @param Illuminate\Database\Query\Builder $query
     * @param string $key
     *
     * @return Illuminate\Database\Query\Builder
     */
    public function getQueryContentAvailable(QueryBuilder $query, string $key) : QueryBuilder
    {
        $dbp_users = config('database.connections.dbp_users.database');
        $dbp_prod = config('database.connections.dbp.database');

        return $query->select(\DB::raw(1))
            ->from($dbp_users . '.user_keys as uk')
            ->join($dbp_users . '.access_group_api_keys as agak', 'agak.key_id', 'uk.id')
            ->join($dbp_prod . '.access_group_filesets as agf', 'agf.access_group_id', 'agak.access_group_id')
            ->join($dbp_prod . '.bible_fileset_connections as bfc', 'agf.hash_id', 'bfc.hash_id')
            ->join($dbp_prod . '.bibles as b', 'bfc.bible_id', 'b.id')
            ->whereColumn('languages.id', '=', 'b.language_id')
            ->where('uk.key', $key);
    }

    public function scopeIsContentAvailable($query, $key)
    {
        return $query->whereExists(function ($query) use ($key) {
            return $this->getQueryContentAvailable($query, $key);
        });
    }

    public function scopeFilterableByMedia($query, $media)
    {
        $set_type_code_array = BibleFileset::getsetTypeCodeFromMedia($media);

        if (!empty($media) && !empty($set_type_code_array)) {
            $query->whereHas('filesets', function ($query_fileset) use ($set_type_code_array) {
                $query_fileset->whereHas('fileset', function ($query_fileset_single) use ($set_type_code_array) {
                    $query_fileset_single->whereIn('set_type_code', $set_type_code_array);
                });
            });
        }
    }

    /**
     * Filter the languages by available bible fileset and the given media parameter.
     *
     * @param Builder $query
     * @param string $key
     * @param string $media
     *
     * @return Builder
     */
    public function scopeIsContentAvailableAndfilterableByMedia(Builder $query, ?string $key, ?string $media) : Builder
    {
        $dbp_prod = config('database.connections.dbp.database');

        return $query->whereExists(function ($query) use ($dbp_prod, $key, $media) {
            return $this->getQueryContentAvailable($query, $key)
                ->when($media, function ($query) use ($media, $dbp_prod) {
                    $set_type_code_array = BibleFileset::getsetTypeCodeFromMedia($media);
                    $query->join($dbp_prod . '.bible_filesets as bfst', 'bfst.hash_id', 'bfc.hash_id')
                        ->whereIn('bfst.set_type_code', $set_type_code_array);
                });
        });
    }

    public function scopeFilterableBySetTypeCode($query, $set_type_code)
    {
        if (!empty($set_type_code)) {
            $query->whereHas('filesets', function ($query_fileset) use ($set_type_code) {
                $query_fileset->whereHas('fileset', function ($query_fileset_single) use ($set_type_code) {
                    $query_fileset_single->where('set_type_code', $set_type_code);
                });
            });
        }
    }

    public function scopeLanguageListingv2($query, $code)
    {
        $subquery_code_v2 = Language::select(
            [
                'languages.id',
                'languages.iso2B',
                'language_codes_v2.id as code',
                'language_codes_v2.language_ISO_639_3_id as iso',
                'language_codes_v2.name',
                'language_codes_v2.english_name'
            ]
        )
        ->join('language_codes_v2', function ($join_codes_v2) {
            $join_codes_v2->on('language_codes_v2.language_ISO_639_3_id', 'languages.iso');
        })
        ->when($code, function ($subquery) use ($code) {
            return $subquery->where('language_codes_v2.id', '=', $code);
        });

        $subquery_lang = Language::select(
            [
                'languages.id',
                'languages.iso2B',
                'languages.iso',
                'languages.iso as code',
                'languages.name',
                'languages.name as english_name'
            ]
        )
        ->when($code, function ($subquery) use ($code) {
            return $subquery->where('languages.iso', '=', $code);
        });

        $subquery_code_v2_sql = $subquery_code_v2->toSql();
        $subquery_lang_sql = $subquery_lang->toSql();

        $db_connection_name = $query->getConnection()->getName() ?? $this->connection;

        $new_language_query = \DB::connection($db_connection_name)
            ->table(
                \DB::connection($db_connection_name)->raw(
                    "(
                    $subquery_code_v2_sql
                    UNION ALL
                    $subquery_lang_sql
                    ) as languages"
                )
            )->select([
                'languages.id',
                'languages.iso2B',
                'languages.iso',
                'languages.code',
                'languages.name',
                'languages.english_name',
            ])
            ->whereRaw('EXISTS (
                SELECT 1 FROM bible_fileset_connections
                INNER JOIN bibles ON bibles.id = bible_fileset_connections.bible_id
                WHERE languages.id = bibles.language_id
            )');

        if (!empty($code)) {
            $new_language_query->addBinding($code)
                ->addBinding($code);
        }

        return $new_language_query;
    }

    /**
     * Sort the languages records by country_population
     *
     * @param Builder $query
     * @see scopeIncludeCountryPopulation
     *
     * @return Builder
     */
    public function scopeIncludeOrderByCountryPopulation(Builder $query) : Builder
    {
        return $query->orderBy('country_population', 'DESC');
    }
}
