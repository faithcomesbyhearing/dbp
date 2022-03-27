<?php

namespace App\Models\Bible;

use DB;
use App\Models\Country\Country;
use App\Models\Language\Alphabet;
use App\Models\Language\NumeralSystem;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Model;
use App\Models\Language\Language;

/**
 * App\Models\Bible\Bible
 * @mixin \Eloquent
 *
 * @property-read \App\Models\Language\Alphabet $alphabet
 * @property-read \App\Models\Bible\BibleBook[] $books
 * @property-read BibleFile[] $files
 * @property-read BibleFileset[] $filesets
 * @property-read \App\Models\Language\Language $language
 * @property-read BibleLink[] $links
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Organization\Organization[] $organizations
 * @property-read BibleTranslation[] $translations
 * @property-read Translator[] $translators
 * @property-read Video[] $videos
 *
 * @property int $priority
 * @property int $open_access
 * @property int $connection_fab
 * @property int $connection_dbs
 * @property string $id
 * @property integer $language_id
 * @property integer $date
 * @property string|null $scope
 * @property string|null $script
 * @property string|null $derived
 * @property string|null $copyright
 * @property string|null $in_progress
 * @property string|null $versification
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @method static Bible wherePriority($value)
 * @method static Bible whereConnectionDbs($value)
 * @method static Bible whereConnectionFab($value)
 * @method static Bible whereOpenAccess($value)
 * @method static Bible whereId($value)
 * @method static Bible whereLanguageId($value)
 * @method static Bible whereDate($value)
 * @method static Bible whereScope($value)
 * @method static Bible whereScript($value)
 * @method static Bible whereDerived($value)
 * @method static Bible whereCopyright($value)
 * @method static Bible whereInProgress($value)
 * @method static Bible whereVersification($value)
 * @method static Bible whereCreatedAt($value)
 * @method static Bible whereUpdatedAt($value)
 *
 * @OA\Schema (
 *     type="object",
 *     description="Bible",
 *     title="Bible",
 *     @OA\Xml(name="Bible")
 * )
 *
 */
class Bible extends Model
{
    /**
     * @var string
     */
    protected $connection = 'dbp';
    protected $keyType = 'string';

    /**
     * Hides values from json return for api
     *
     * created_at and updated at are only used for archival work. pivots contain duplicate data;
     * @var array
     */
    protected $hidden = ['created_at', 'updated_at', 'pivot', 'priority', 'in_progress'];


    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="string",
     *   description="The Archivist created Bible ID string. This will be between six and twelve letters usually starting with the iso639-3 code and ending with the acronym for the Bible",
     *   minLength=6,
     *   maxLength=12,
     *   example="ENGESV"
     * )
     *
     */
    protected $id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Language/properties/id")
     *
     */
    protected $language_id;

    /**
     *
     * @OA\Property(
     *   title="date",
     *   type="integer",
     *   description="The year the Bible was originally published",
     *   minimum=1,
     *   maximum=4,
     *   example=1963
     * )
     *
     */
    protected $date;
    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFilesetSize/properties/set_size_code")
     *
     */
    protected $scope;

    /**
     *
     * Dramatized Audio
     *
     */
    protected $script;

    /**
     *
     * @OA\Property(
     *   title="derived",
     *   type="string",
     *   nullable=true,
     *   description="This field indicates the Bible from which the current Scriptures being described are derived.",
     *   example="English New Revised Standard Version"
     * )
     *
     */
    protected $derived;

    /**
     *
     * @OA\Property(
     *   title="copyright",
     *   type="string",
     *   description="A short copyright description for the bible text.",
     *   maxLength=191,
     *   example="© 1999 Bible Society of Ghana"
     * )
     *
     */
    protected $copyright;

    /**
     *
     * API Note: removed
     *   title="in_progress",
     *   type="string",
     *   description="If the Bible being described is currently in progress.",
     * )
     *
     */
    protected $in_progress;

    /**
     *
     * @OA\Property(
     *   title="versification",
     *   type="string",
     *   description="The versification system for ordering books and chapters",
     *   enum={"protestant","luther","synodal","german","kjva","vulgate","lxx","orthodox","nrsva","catholic","finnish"}
     * )
     *
     */
    protected $versification;

    /**
     *
     * @OA\Property(
     *   title="created_at",
     *   type="string",
     *   description="The timestamp at which the bible was originally created",
     *   example="2018-02-12 13:32:23"
     * )
     *
     */
    protected $created_at;
    /**
     *
     * @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp at which the bible was last updated",
     *   example="2018-02-12 13:32:23"
     * )
     *
     */
    protected $updated_at;

    /**
     * @var array
     */
    protected $fillable = ['id', 'iso', 'date', 'script', 'derived', 'copyright'];
    /**
     * @var bool
     */
    public $incrementing = false;

    public function translations()
    {
        return $this->hasMany(BibleTranslation::class)->where('name', '!=', '');
    }

    public function currentTranslation()
    {
        $language_id = $GLOBALS['i18n_id'] ?? Language::where('iso', 'eng')->first()->id;
        return $this->hasOne(BibleTranslation::class)->where('language_id', $language_id)->where('name', '!=', '');
    }

    public function vernacularTranslation()
    {
        return $this->hasOne(BibleTranslation::class)->where('vernacular', '=', 1)->where('name', '!=', '');
    }

    public function books()
    {
        return $this->hasMany(BibleBook::class);
    }

    public function filesetConnections()
    {
        return $this->hasMany(BibleFilesetConnection::class);
    }

    public function filesets()
    {
        return $this->hasManyThrough(BibleFileset::class, BibleFilesetConnection::class, 'bible_id', 'hash_id', 'id', 'hash_id')
        ->with(['meta' => function ($subQuery) {
            $subQuery->where('admin_only', 0);
        }]);
    }

    public function files()
    {
        return $this->hasMany(BibleFile::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'bible_organizations')->withPivot(['relationship_type']);
    }

    public function links()
    {
        return $this->hasMany(BibleLink::class)->where('visible', true);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function country()
    {
        return $this->hasManyThrough(Country::class, Language::class, 'id', 'id', 'language_id', 'country_id')->select(['countries.id as country_id','countries.continent','countries.name']);
    }

    public function alphabet()
    {
        return $this->hasOne(Alphabet::class, 'script', 'script')->select(['script','name','direction','unicode','requires_font']);
    }

    public function numbers()
    {
        return $this->hasOne(NumeralSystem::class, 'number_id', 'number_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class)->orderBy('order', 'asc');
    }

    private function setConditionFilesets($query, $type_filters)
    {
        if ($type_filters['media']) {
            $query->where('bible_filesets.set_type_code', $type_filters['media']);
        }
        if ($type_filters['media_exclude']) {
            $query->where('bible_filesets.set_type_code', '!=', $type_filters['media_exclude']);
        }
        if ($type_filters['size']) {
            $query->where('bible_filesets.set_size_code', '=', $type_filters['size']);
        }
        if ($type_filters['size_exclude']) {
            $query->where('bible_filesets.set_size_code', '!=', $type_filters['size_exclude']);
        }
    }

    private function setConditionTagExclude($quey, $type_filters)
    {
        $quey->whereDoesntHave('meta', function ($query_meta) use ($type_filters) {
            $query_meta->where('bible_fileset_tags.description', $type_filters['tag_exclude']);
        });
    }

    public function scopeWithRequiredFilesets($query, $type_filters)
    {
        return $query->whereHas('filesets', function ($q) use ($type_filters) {
            if ($type_filters['media']) {
                $q->where('bible_filesets.set_type_code', $type_filters['media']);
            }

            $q->select(\DB::raw(1));
            $q->isContentAvailable($type_filters['key']);
            $this->setConditionFilesets($q, $type_filters);
            $this->setConditionTagExclude($q, $type_filters);
        })->with(['filesets' => function ($q) use ($type_filters) {
            $q->with(['meta' => function ($subQuery) {
                $subQuery->where('admin_only', 0);
            }]);
            $q->isContentAvailable($type_filters['key']);
            $this->setConditionFilesets($q, $type_filters);
            $this->setConditionTagExclude($q, $type_filters);
        }]);
    }

    public function scopeFilterByLanguage($query, $language_codes)
    {
        $query->when($language_codes, function ($q) use ($language_codes) {
            $language_codes = explode(',', $language_codes);
            $languages = Language::whereIn('iso', $language_codes)->orWhereIn('id', $language_codes)->get();
            $q->whereIn('bibles.language_id', $languages->pluck('id'));
        });
    }

    public function scopeMatchByFulltextSearch($query, $search_text)
    {
        // If the search_text is a single word the pattern will be e.g. +contain*
        // and it will find rows that contain words such as 'contain', 'contains',
        // 'containskey' etc.

        // If the search_text contains two words the pattern will be e.g. +next contain*
        // and it will find rows that contain both words but the first word should exact
        // and the other just contains the word such as 'next contain', 'next contains',
        // 'next containskey'
        $formatted_search = "+$search_text*";

        return $query
            ->select(['ver_title.bible_id', 'ver_title.name', 'ver_title.language_id'])
            ->join(
                'bible_translations as ver_title',
                function ($join) use ($formatted_search) {
                    $join->on('ver_title.bible_id', '=', 'bibles.id')
                    ->whereRaw(
                        'match (ver_title.name) against (? IN BOOLEAN MODE)',
                        [$formatted_search]
                    );
                }
            )->orderByRaw(
                'match (ver_title.name) against (? IN BOOLEAN MODE) DESC',
                [$formatted_search]
            );
    }

    public function scopeIsContentAvailable($query, $key)
    {
        $dbp_users = config('database.connections.dbp_users.database');
        $dbp_prod = config('database.connections.dbp.database');

        return $query->whereRaw(
            'EXISTS (select 1
                    from ' . $dbp_users . '.user_keys uk
                    join ' . $dbp_users . '.access_group_api_keys agak on agak.key_id = uk.id
                    join ' . $dbp_prod . '.access_group_filesets agf on agf.access_group_id = agak.access_group_id
                    join ' . $dbp_prod . '.bible_fileset_connections bfc on agf.hash_id = bfc.hash_id
                    where uk.key = ? and bibles.id = bfc.bible_id
            )',
            [$key]
        );
    }
    public function scopeIsTimingInformationAvailable($query)
    {
        $timestamps_counts = BibleFileTimestamp::select('bible_file_timestamps.bible_file_id')
            ->distinct();

        $files_timestamps = BibleFile::select('bible_files.hash_id')
            ->distinct()
            ->joinSub($timestamps_counts, 'timestamps_counts', function ($join) {
                $join->on('timestamps_counts.bible_file_id', '=', 'bible_files.id');
            });

        $bibles_ids_with_timestamps = BibleFilesetConnection::select('bible_fileset_connections.bible_id')
            ->distinct()
            ->joinSub($files_timestamps, 'files_timestamps', function ($join) {
                $join->on('bible_fileset_connections.hash_id', '=', 'files_timestamps.hash_id');
            })
            ->get()
            ->pluck('bible_id');

        return $query->whereIn('bibles.id', $bibles_ids_with_timestamps);
    }
}
