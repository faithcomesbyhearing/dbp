<?php

namespace App\Models\Bible;

use App\Models\Organization\Asset;
use App\Models\Organization\Organization;
use App\Models\User\AccessGroupFileset;
use App\Models\Bible\BibleFilesetSize;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * App\Models\Bible\BibleFileset
 * @mixin \Eloquent
 *
 * @method static BibleFileset whereId($value)
 * @property string $id
 * @method static BibleFileset whereSetTypeCode($value)
 * @property string $set_type_code
 * @method static BibleFileset whereSetSizeCode($value)
 * @property string $set_size_code
 * @method static Bible whereCreatedAt($value)
 * @property \Carbon\Carbon|null $created_at
 * @method static Bible whereUpdatedAt($value)
 * @property \Carbon\Carbon|null $updated_at
 *
 * @OA\Schema (
 *     type="object",
 *     description="BibleFileset",
 *     title="Bible Fileset",
 *     @OA\Xml(name="BibleFileset")
 * )
 *
 */
class BibleFileset extends Model
{
    public const AUDIO = 'audio';
    public const VIDEO = 'video';
    public const TEXT = 'text';

    public const TYPE_AUDIO_DRAMA = 'audio_drama';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_AUDIO_STREAM = 'audio_stream';
    public const TYPE_AUDIO_DRAMA_STREAM = 'audio_drama_stream';
    public const TYPE_VIDEO_STREAM = 'video_stream';
    public const TYPE_TEXT_FORMAT = 'text_format';
    public const TYPE_TEXT_PLAIN = 'text_plain';
    public const TYPE_TEXT_USX = 'text_usx';

    public const NEW_TEXT_PLAIN_FILESET_LENGTH = 10;
    public const OLD_TEXT_PLAIN_FILESET_LENGTH = 6;
    public const V1_AUDIO_16_KBPS_FILESET_LENGTH = 12;
    public const V1_SUFIX_AUDIO_16_KBPS = 'DA16';
    public const V2_SUFIX_AUDIO_16_KBPS = '-opus16';

    protected $connection = 'dbp';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $hidden = ['created_at', 'updated_at', 'response_time', 'hidden', 'bible_id', 'hash_id'];
    protected $fillable = ['name', 'set_type', 'organization_id', 'variation_id', 'bible_id', 'set_copyright'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="string",
     *   description="The fileset id",
     *   example="ENGESV",
     *   minLength=6,
     *   maxLength=16
     * )
     *
     */
    protected $id;

    protected $hash_id;

    protected $asset_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFilesetType/properties/set_type_code")
     *
     *
     */
    protected $set_type_code;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFilesetSize/properties/set_size_code")
     *
     *
     */
    protected $set_size_code;


    protected $created_at;

    protected $updated_at;

    protected $bible_files_indexed_by_book_and_chapter;
    /**
     * @OA\Property(
     *   title="license_group_id",
     *   type="integer",
     *   description="The liceense group id",
     * )
     */
    protected $license_group_id;

    public function copyright()
    {
        return $this->hasOne(BibleFilesetCopyright::class, 'hash_id', 'hash_id');
    }

    public function copyrightOrganization()
    {
        return $this->hasMany(BibleFilesetCopyrightOrganization::class, 'hash_id', 'hash_id');
    }

    public function permissions()
    {
        return $this->hasMany(AccessGroupFileset::class, 'hash_id', 'hash_id');
    }

    public function bible()
    {
        return $this->hasManyThrough(Bible::class, BibleFilesetConnection::class, 'hash_id', 'id', 'hash_id', 'bible_id');
    }

    public function translations()
    {
        return $this->hasManyThrough(BibleTranslation::class, BibleFilesetConnection::class, 'hash_id', 'bible_id', 'hash_id', 'bible_id');
    }

    public function connections()
    {
        return $this->hasOne(BibleFilesetConnection::class, 'hash_id', 'hash_id');
    }

    public function organization()
    {
        return $this->hasManyThrough(Organization::class, Asset::class, 'id', 'id', 'asset_id', 'organization_id');
    }

    public function files()
    {
        return $this->hasMany(BibleFile::class, 'hash_id', 'hash_id');
    }

    public function secondaryFiles()
    {
        return $this->hasMany(BibleFileSecondary::class, 'hash_id', 'hash_id');
    }

    public function verses()
    {
        return $this->hasMany(BibleVerse::class, 'hash_id', 'hash_id');
    }

    public function meta()
    {
        return $this->hasMany(BibleFilesetTag::class, 'hash_id', 'hash_id')->where('admin_only', '<>', 1);
    }

    public function fonts()
    {
        return $this->hasManyThrough(Font::class, BibleFilesetFont::class, 'hash_id', 'id', 'hash_id', 'font_id');
    }

    protected static function subqueryFilesetTypeTextPlainAssociated(?string $bible_id) : Builder
    {
        return BibleFileset::select('bible_filesets.*')
            ->join('bible_fileset_connections as connection', 'connection.hash_id', 'bible_filesets.hash_id')
            ->where('connection.bible_id', $bible_id)
            ->where('set_type_code', BibleFileset::TYPE_TEXT_PLAIN)
            ->where('archived', false)
            ->where('content_loaded', true);
    }
    /**
     * Retrieves an associated fileset of type 'text plain' based on set size codes.
     *
     * This method initially tries to find a fileset with a specific set size code.
     * If no matching fileset is found, it defaults to a fileset with a 'complete' size.
     *
     * @return BibleFileset|null The associated BibleFileset if found, or null otherwise.
     */
    public static function filesetTypeTextPlainAssociated(?string $fileset_id) : BibleFileset|null
    {
        $fileset_associated = BibleFileset::select(['hash_id', 'id', 'set_size_code'])
            ->with('bible')
            ->where("id", $fileset_id)
            ->first();

        $bible_id = $fileset_associated->bible->first()->id;

        $query = self::subqueryFilesetTypeTextPlainAssociated($bible_id);

        $fileset = $query
            ->where('set_size_code', $fileset_associated["set_size_code"])
            ->first();

        if ($fileset && $fileset["id"]) {
            return $fileset;
        }

        // E.g fileset_associated = NT and text plain = NTP
        $query = self::subqueryFilesetTypeTextPlainAssociated($bible_id);
        $fileset = $query
            ->where('set_size_code', 'LIKE', '%'.$fileset_associated["set_size_code"].'%')
            ->first();

        if ($fileset && $fileset["id"]) {
            return $fileset;
        }

        // If no specific fileset is found, default to SIZE_COMPLETE (six character fileset)
        $query = self::subqueryFilesetTypeTextPlainAssociated($bible_id);
        return $query
            ->where('set_size_code', BibleFilesetSize::SIZE_COMPLETE)
            ->first();
    }

    public function scopeWithBible($query, $bible_name, $language_id, $organization)
    {
        return $query
            ->join('bible_fileset_connections as connection', 'connection.hash_id', 'bible_filesets.hash_id')
            ->join('bibles', function ($q) use ($language_id) {
                $q->on('connection.bible_id', 'bibles.id')
                    ->when($language_id, function ($subquery) use ($language_id) {
                        $subquery->where('bibles.language_id', $language_id);
                    });
            })
            ->leftJoin('languages', 'bibles.language_id', 'languages.id')
            ->join('language_translations', function ($q) {
                $q->on('languages.id', 'language_translations.language_source_id')
                    ->on('languages.id', 'language_translations.language_translation_id');
            })
            ->leftJoin('alphabets', 'bibles.script', 'alphabets.script')
            ->leftJoin('bible_translations as english_name', function ($q) use ($bible_name) {
                $q->on('english_name.bible_id', 'bibles.id')->where('english_name.language_id', 6414);
                $q->when($bible_name, function ($subQuery) use ($bible_name) {
                    $subQuery->where('english_name.name', 'LIKE', '%' . $bible_name . '%');
                });
            })
            ->leftJoin('bible_translations as autonym', function ($q) use ($bible_name) {
                $q->on('autonym.bible_id', 'bibles.id')->where('autonym.vernacular', true);
                $q->when($bible_name, function ($subQuery) use ($bible_name) {
                    $subQuery->where('autonym.name', 'LIKE', '%' . $bible_name . '%');
                });
            })
            ->leftJoin('bible_organizations', function ($q) use ($organization) {
                $q->on('bibles.id', 'bible_organizations.bible_id')->where('relationship_type', 'publisher');
                if ($organization) {
                    $q->where('bible_organizations.organization_id', $organization);
                }
            });
    }

    public function scopeUniqueFileset(
        $query,
        $id = null,
        $fileset_type = null,
        $ambigious_fileset_type = false,
        $testament_filter = null
    ) {
        $version = (int) checkParam('v');
        return $query->when($id, function ($query) use ($id, $version) {
            $query->where(function ($query) use ($id, $version) {
                if ($version  <= 2) {
                    $query->where('bible_filesets.id', $id)
                        ->orWhere('bible_filesets.id', substr($id, 0, -4))
                        ->orWhere('bible_filesets.id', 'like', substr($id, 0, 6))
                        ->orWhere('bible_filesets.id', 'like', substr($id, 0, -2) . '%');
                } else {
                    $query->where('bible_filesets.id', $id)
                        ->whereExists(function ($query) use ($id) {
                            return $query
                                ->select(\DB::raw(1))
                                ->from('bible_fileset_connections')
                                ->where('bible_filesets.id', $id)
                                ->whereColumn('bible_filesets.hash_id', '=', 'bible_fileset_connections.hash_id');
                        });
                }
            });
        })
        ->when($testament_filter, function ($query) use ($testament_filter) {
            if (is_array($testament_filter) && !empty($testament_filter)) {
                $query->whereIn('bible_filesets.set_size_code', $testament_filter);
            }
        })
        ->when($fileset_type, function ($query) use ($fileset_type, $ambigious_fileset_type) {
            if ($ambigious_fileset_type) {
                $query->where('bible_filesets.set_type_code', 'LIKE', $fileset_type . '%');
            } else {
                $query->where('bible_filesets.set_type_code', $fileset_type);
            }
        })
        ->where('bible_filesets.content_loaded', true)
        ->where('bible_filesets.archived', false);
    }

    public function scopeIsContentAvailable(
        Builder $query,
        \Illuminate\Support\Collection $access_groups,
        ?array $access_group_ids = [],
    ) : Builder {
        return $query
            ->where('bible_filesets.content_loaded', true)
            ->where('bible_filesets.archived', false)
            ->leftJoin('license_group as lg', 'lg.id', '=', 'bible_filesets.license_group_id')
            ->join('bible_fileset_types as bftypes', 'bftypes.set_type_code', '=', 'bible_filesets.set_type_code')
            ->where(function (Builder $q) use ($access_groups, $access_group_ids) {
                $q
                    // AGF path: no pattern or pattern=100, and matching AGF record
                    ->where(function (Builder $sub) use ($access_groups, $access_group_ids) {
                        $sub->whereNull('lg.permission_pattern_id')
                            ->orWhere('lg.permission_pattern_id', 100)
                        ->whereExists(function (QueryBuilder $query) use ($access_groups, $access_group_ids) {
                            $query->selectRaw('1')
                                ->from('access_group_filesets', 'agf')
                                ->join('access_groups as agroups', 'agroups.id', '=', 'agf.access_group_id')
                                ->whereIn('agf.access_group_id', $access_groups)
                                ->when(!empty($access_group_ids), function ($query) use ($access_group_ids) {
                                    $query->whereIn('agf.access_group_id', $access_group_ids);
                                })
                                ->whereColumn('bftypes.mode_id', '=', 'agroups.mode_id')
                                ->whereColumn('agf.hash_id', '=', 'bible_filesets.hash_id');
                        });
                    })

                    // OR PPAG path: other pattern, and matching PPAG record
                    ->orWhere(function (Builder $sub) use ($access_groups, $access_group_ids) {
                        $sub->whereNotNull('lg.permission_pattern_id')
                            ->where('lg.permission_pattern_id', '<>', 100)
                            ->whereExists(function (QueryBuilder $query) use ($access_groups, $access_group_ids) {
                                $query->selectRaw('1')
                                    ->from('permission_pattern_access_group', 'ppag')
                                    ->join('access_groups as agroups', 'agroups.id', '=', 'ppag.access_groups_id')
                                    ->whereIn('ppag.access_groups_id', $access_groups)
                                    ->when(!empty($access_group_ids), function ($query) use ($access_group_ids) {
                                        $query->whereIn('ppag.access_groups_id', $access_group_ids);
                                    })
                                    ->whereColumn('ppag.permission_pattern_id', '=', 'lg.permission_pattern_id')
                                    ->whereColumn('bftypes.mode_id', '=', 'agroups.mode_id');
                            });
                    });
            });
    }

    public static function getsetTypeCodeFromMedia($media)
    {
        $result = [];
        switch ($media) {
            case self::AUDIO:
                $result = [
                    'audio_drama',
                    'audio',
                    'audio_stream',
                    'audio_drama_stream'
                ];
                break;
            case self::VIDEO:
                $result = ['video_stream'];
                break;
            case self::TEXT:
                $result = [
                    'text_format',
                    'text_plain',
                    'text_usx',
                    'text_json'
                ];
                break;
            default:
                break;
        }
        return $result;
    }

    /**
     * Filter record by given ids array
     *
     * @param Builder $query
     * @param Array $fileset_ids
     *
     * @return Builder
     */
    public function scopeFilterByIds(Builder $query, \Illuminate\Support\Collection|Array $fileset_ids) : Builder
    {
        return $query->select('id', 'hash_id')
            ->whereIn('id', $fileset_ids);
    }

    /**
     * Get records that they are not related with the tag
     *
     * @param Builder $query
     * @param Array $tags_exclude
     *
     * @return Builder
     */
    public function scopeConditionTagExclude(Builder $query, Array $tags_exclude) : Builder
    {
        return $query->whereDoesntHave('meta', function ($query_meta) use ($tags_exclude) {
            $query_meta->where('description', $tags_exclude);
        });
    }

    public static function getConditionTagExcludeByIds(\Illuminate\Support\Collection $fileset_ids, Array $tags_exclude) : Collection
    {
        return self::filterByIds($fileset_ids)
            ->conditionTagExclude($tags_exclude)
            ->get()
            ->keyBy('id');
    }

    /**
     * Get a boolean to know if the fileset belongs to audio type
     *
     * @return bool
     */
    public function isAudio() : bool
    {
        return in_array(
            $this['set_type_code'],
            [
                BibleFileset::TYPE_AUDIO_DRAMA,
                BibleFileset::TYPE_AUDIO,
                BibleFileset::TYPE_AUDIO_DRAMA,
                BibleFileset::TYPE_AUDIO_DRAMA_STREAM
            ]
        );
    }

    /**
     * Get a boolean to know if the fileset belongs to video type
     *
     * @return bool
     */
    public function isVideo() : bool
    {
        return Str::contains($this['set_type_code'], BibleFileset::VIDEO);
    }

    /**
     * Add the meta records as attributes of BibleFileset instance
     *
     * @return BibleFileset
     */
    public function addMetaRecordsAsAttributes() : BibleFileset
    {
        $meta_tags_indexed = $this->getMetaTagsIndexedByName();

        if (!empty($meta_tags_indexed)) {
            foreach ($meta_tags_indexed as $name => $description) {
                $this[$name] = $description;
            }
        }

        return $this;
    }

    /**
     * Get the meta records indexed by name attached to the current instance
     *
     * @return array
     */
    public function getMetaTagsIndexedByName() : array
    {
        if (isset($this->meta)) {
            $meta_tags_indexed = self::getDefaultMetaTags();
            foreach ($this->meta as $metadata) {
                if (isset($metadata['name'], $metadata['description'])) {
                    $meta_tags_indexed[$metadata['name']] = $metadata['description'];
                }
            }
            return $meta_tags_indexed;
        }

        return [];
    }


    public static function getDefaultMetaTags() : array
    {
        // December 2022, this is temporary to support older versions of 5fish applications.
        // Revisit in one year after discussing with James Thomas mailto:jamesthomas@globalrecordings.net
        //
        // When it is no longer necessary, it should return an empty array
        return [
            BibleFilesetTag::STOCK_NO_TAG => null
        ];
    }

    protected function subqueryConditionToExcludeOldTextFormat(QueryBuilder $subquery, string $from_table) : QueryBuilder
    {
        return $subquery->select(DB::raw(1))
            ->from('bible_filesets', 'bfctext')
            ->where('bfctext.set_type_code', BibleFileset::TYPE_TEXT_PLAIN)
            ->where('bfctext.content_loaded', true)
            ->where('bfctext.archived', false)
            ->whereColumn('bfctext.set_type_code', '=', $from_table.'.set_type_code')
            ->where(DB::raw(\sprintf('CHAR_LENGTH(%s.id)', $from_table)), '=', self::OLD_TEXT_PLAIN_FILESET_LENGTH)
            ->where(DB::raw('CHAR_LENGTH(bfctext.id)'), '=', self::NEW_TEXT_PLAIN_FILESET_LENGTH)
            ->whereColumn(
                DB::raw(\sprintf('SUBSTRING(bfctext.id, %d, %d)', 1, self::OLD_TEXT_PLAIN_FILESET_LENGTH)),
                '=',
                $from_table.'.id'
            );
    }

    /**
     * Filter bible fileset records to avoid pulling the old six character text_plain fileset
     * when a ten character fileset id exists.
     *
     * @param Builder $query
     */
    public function scopeConditionToExcludeOldTextFormat(Builder $query) : Builder
    {
        $from_table = getAliasOrTableName($query->getQuery()->from);

        return $query
            ->whereNotExists(function (QueryBuilder $subquery) use ($from_table) {
                // Check for the existence of the same text format for both the six-character fileset and
                // the 10-character fileset.
                return $this->subqueryConditionToExcludeOldTextFormat($subquery, $from_table)
                    ->whereColumn('bfctext.set_size_code', '=', $from_table.'.set_size_code');
            })->whereNot(function (Builder $subquery) use ($from_table) {
                // Check for the existence of a complete text format for the six-character fileset, which
                // includes NT and OT sizes of the 10-character fileset. It will ensure that both NT and
                // OT exist to avoid returning the six-character fileset.
                return $subquery->whereExists(function (QueryBuilder $subquery_exists) use ($from_table) {
                    return $this->subqueryConditionToExcludeOldTextFormat($subquery_exists, $from_table)
                        ->where($from_table.'.set_size_code', '=', BibleFilesetSize::SIZE_COMPLETE)
                        ->where('bfctext.set_size_code', 'LIKE', '%'.BibleFilesetSize::SIZE_NEW_TESTAMENT.'%');
                })->whereExists(function (QueryBuilder $subquery_exists) use ($from_table) {
                    return $this->subqueryConditionToExcludeOldTextFormat($subquery_exists, $from_table)
                        ->where($from_table.'.set_size_code', '=', BibleFilesetSize::SIZE_COMPLETE)
                        ->where('bfctext.set_size_code', 'LIKE', '%'.BibleFilesetSize::SIZE_OLD_TESTAMENT.'%');
                });
            })->whereNot(function (Builder $subquery) use ($from_table) {
                // Check for the existence of partial text formats for the six-character fileset
                // (e.g., size_type=NTOTP) with NT and OT sizes of the 10-character fileset. It will ensure
                // that both NT and OT exist to avoid returning the six-character fileset.
                return $subquery->whereExists(function (QueryBuilder $subquery_exists) use ($from_table) {
                    return $this->subqueryConditionToExcludeOldTextFormat($subquery_exists, $from_table)
                        ->where($from_table.'.set_size_code', 'LIKE', '%'.BibleFilesetSize::SIZE_NEW_TESTAMENT.'%')
                        ->where('bfctext.set_size_code', 'LIKE', '%'.BibleFilesetSize::SIZE_NEW_TESTAMENT.'%');
                })->whereExists(function (QueryBuilder $subquery_exists) use ($from_table) {
                    return $this->subqueryConditionToExcludeOldTextFormat($subquery_exists, $from_table)
                        ->where($from_table.'.set_size_code', 'LIKE', '%'.BibleFilesetSize::SIZE_OLD_TESTAMENT.'%')
                        ->where('bfctext.set_size_code', 'LIKE', '%'.BibleFilesetSize::SIZE_OLD_TESTAMENT.'%');
                });
            });
    }

    /**
     * Filter bible fileset records to avoid pulling the old 16kbps content (DA16) fileset
     * when a opus16 content are available.
     *
     * @param Builder $query
     */
    public function scopeConditionToExcludeOldDA16Format(Builder $query) : Builder
    {
        $from_table = getAliasOrTableName($query->getQuery()->from);

        return $query
            ->whereNotExists(function (QueryBuilder $subquery) use ($from_table) {
                return $subquery->select(\DB::raw(1))
                    ->from('bible_filesets', 'bfcaudio')
                    ->whereIn('bfcaudio.set_type_code', [BibleFileset::TYPE_AUDIO, BibleFileset::TYPE_AUDIO_DRAMA])
                    ->whereColumn('bfcaudio.set_type_code', '=', $from_table.'.set_type_code')
                    ->where(
                        DB::raw(\sprintf(
                            'CHAR_LENGTH(%s.id)',
                            $from_table
                        )),
                        '=',
                        self::V1_AUDIO_16_KBPS_FILESET_LENGTH
                    )
                    ->where(
                        DB::raw(\sprintf(
                            'SUBSTRING(bfcaudio.id, %d, %d)',
                            strlen(self::V2_SUFIX_AUDIO_16_KBPS)*-1,
                            strlen(self::V2_SUFIX_AUDIO_16_KBPS)
                        )),
                        '=',
                        self::V2_SUFIX_AUDIO_16_KBPS
                    )
                    ->whereColumn(
                        DB::raw(\sprintf('SUBSTRING(bfcaudio.id, %d, %d)', 1, self::NEW_TEXT_PLAIN_FILESET_LENGTH)),
                        '=',
                        DB::raw(
                            \sprintf(
                                'SUBSTRING(%s.id, %d, %d)',
                                $from_table,
                                1,
                                self::NEW_TEXT_PLAIN_FILESET_LENGTH
                            ),
                        )
                    );
            });
    }

    public function scopeFilterBy(Builder $query, array $filters) : Builder
    {
        $from_table = getAliasOrTableName($query->getQuery()->from);

        return $query
            ->when(isset($filters['set_type_code']), function ($query) use ($filters, $from_table) {
                $query->where($from_table.'.set_type_code', $filters['set_type_code']);
            })
            ->when(isset($filters['media']), function ($query) use ($filters, $from_table) {
                $set_type_code_array = BibleFileset::getsetTypeCodeFromMedia($filters['media']);
                return $query->whereIn($from_table.'.set_type_code', $set_type_code_array);
            });
    }

    /**
     * Determines if a file related to a specific book and chapter exists.
     *
     * This method checks if there's a Bible file associated with a given book and chapter. If the
     * files haven't been indexed by book and chapter yet, it retrieves and caches them in the class
     * property `$bible_files_indexed_by_book_and_chapter` for efficient subsequent lookups.
     *
     * @param string $book_id The identifier of the book.
     * @param int    $chapter The chapter number.
     *
     * @return bool True if a related file exists for the specified book and chapter, false otherwise.
     */
    public function hasFileRelatedBookAndChapter(string $book_id, int $chapter) : bool
    {
        if (!$this['hash_id']) {
            return false;
        }

        if (!$this->bible_files_indexed_by_book_and_chapter) {
            $bible_file_hash_id = $this['hash_id'];

            $this->bible_files_indexed_by_book_and_chapter = cacheRemember(
                'bible_file_indexed_by_book_and_chapter',
                [$bible_file_hash_id],
                now()->addDay(),
                function () use ($bible_file_hash_id) {
                    $files = BibleFile::select(['book_id', 'chapter_start'])
                        ->where('hash_id', $bible_file_hash_id)
                        ->get();

                    $hash_by_book_and_chapter = [];
                    foreach($files as $file) {
                        $hash_by_book_and_chapter[$file->book_id][(int) $file->chapter_start] = true;
                    }
                    return $hash_by_book_and_chapter;
                }
            );
        }

        return  isset($this->bible_files_indexed_by_book_and_chapter[$book_id]) &&
                isset($this->bible_files_indexed_by_book_and_chapter[$book_id][$chapter]);
    }
    /**
     * Scope to check if license group access is available for the given access groups considering the mode_id.
     *
     * @param Builder $query
     * @param \Illuminate\Support\Collection $access_groups
     * @param array|null $access_group_ids
     * @return Builder
     */
    public function scopeIsAccessGroupAvailable(
        Builder $query,
        \Illuminate\Support\Collection $api_user_access_groups,
        ?array $access_group_ids = []
    ) : Builder {
        return $query->whereExists(function (QueryBuilder $subquery) use ($api_user_access_groups, $access_group_ids) {
            return $subquery->selectRaw('1')
                ->from('sys_license_group_access_groups_view', 'slicense')
                ->join('access_groups AS agroups', 'agroups.id', '=', 'slicense.access_group_id')
                ->join('bible_fileset_types AS ftypes', function($join) {
                    // join ftypes to the parent bible_filesets via set_type_code, then match mode_id
                    $join->on('ftypes.set_type_code', '=', 'bible_filesets.set_type_code')
                        ->on('ftypes.mode_id', '=', 'agroups.mode_id');
                })
                ->whereColumn('slicense.lg_id', 'bible_filesets.license_group_id')
                ->whereIn('slicense.access_group_id', $api_user_access_groups)
                ->when(!empty($access_group_ids), function ($query) use ($access_group_ids) {
                    $query->whereIn('slicense.access_group_id', $access_group_ids);
                });
        });
    }
}
