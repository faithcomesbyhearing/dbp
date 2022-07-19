<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Bible\Bible;
use App\Models\Bible\BibleFilesetConnection;
use App\Models\Bible\BibleTranslation;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\BibleFilesetTag;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleFileSecondary;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFilesetCopyrightRole;
use App\Models\Language\Language;
use App\Models\User\Key;
use App\Models\Organization\Organization;

/**
 * App\Models\Bible\BibleFilesetLookup
 * @mixin \Eloquent
 *
 * @method static BibleFilesetLookup whereStocknumber($value)
 * @property string $stocknumber
 * @method static BibleFilesetLookup whereFilesetid($value)
 * @property string $filesetid
 * @method static BibleFilesetLookup whereHashId($value)
 * @property string $hash_id
 * @method static BibleFilesetLookup whereAssetId($value)
 * @property string $asset_id
 * @method static BibleFilesetLookup whereBibleid($value)
 * @property string $bibleid
 * @method static BibleFilesetLookup whereType($value)
 * @property string $type
 * @method static BibleFilesetLookup whereLanguage($value)
 * @property string $language
 * @method static BibleFilesetLookup whereVersion($value)
 * @property string $version
 * @method static BibleFilesetLookup whereLicensor($value)
 * @property string $licensor
 *
 * @OA\Schema (
 *     type="object",
 *     description="BibleFilesetLookup",
 *     title="Bible Fileset",
 *     @OA\Xml(name="BibleFilesetLookup")
 * )
 *
 */
class BibleFilesetLookup extends Model
{
     protected $connection = 'dbp';
    public $incrementing = false;
    protected $hidden = ['stocknumber', 'filesetid', 'hash_id', 'bibleid'];
    protected $fillable = ['type', 'language', 'version', 'licensor'];


    protected $stocknumber;

    protected $filesetid;

    protected $hash_id;

    protected $asset_id;

    protected $bibleid;

    protected $type;

    protected $language;

    protected $version;

    protected $licensor;

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
        return $this->hasMany(BibleFilesetTag::class, 'hash_id', 'hash_id');
    }


    public function scopeContentAvailable(Builder $query) : Builder
    {
        return $query->from('organizations')
            ->join('bible_fileset_copyright_organizations', function ($join) {
                $join->on('bible_fileset_copyright_organizations.organization_id', '=', 'organizations.id')
                    ->where(
                        'bible_fileset_copyright_organizations.organization_role',
                        BibleFilesetCopyrightRole::LICENSOR
                    );
            })
            ->join('bible_filesets', 'bible_fileset_copyright_organizations.hash_id', 'bible_filesets.hash_id')
            ->join('bible_fileset_connections', 'bible_fileset_connections.hash_id', 'bible_filesets.hash_id')
            ->join('bibles', 'bibles.id', 'bible_fileset_connections.bible_id')
            ->join('languages', 'languages.id', 'bibles.language_id')
            ->join('bible_fileset_tags', function ($join) {
                $join->on('bible_filesets.hash_id', '=', 'bible_fileset_tags.hash_id')
                    ->where('bible_fileset_tags.name', 'stock_no');
            })
            ->join('organization_translations', function ($join) {
                $join->on(
                    'organization_translations.organization_id',
                    '=',
                    'bible_fileset_copyright_organizations.organization_id'
                )
                    ->where('organization_translations.language_id', Language::ENGLISH_ID);
            })
            ->join('bible_translations', function ($join) {
                $join->on('bible_translations.bible_id', '=', 'bibles.id')
                    ->where('bible_translations.language_id', Language::ENGLISH_ID);
            });
    }

    /**
     * Get fileset list available for a user key given
     *
     * @param string $key
     * @param int $limit
     * @param string $type
     *
     * @return LengthAwarePaginator
     */
    public static function getContentAvailableByKey(string $key, int $limit, string $type = null) : LengthAwarePaginator
    {
        $dbp_users = config('database.connections.dbp_users.database');
        $dbp_prod = config('database.connections.dbp.database');

        $download_access_group_array_ids = getDownloadAccessGroupList();

        $user_key_id = Key::getIdByKey($key);

        $select_distinct_columns = [
            'bible_fileset_tags.description',
            'bibles.id',
            'bible_filesets.id',
            'bible_filesets.set_type_code',
            'bible_filesets.hash_id',
            'languages.name',
            'bible_translations.name',
            'organization_translations.name'
        ];

        return BibleFilesetLookup::select([
                'bible_fileset_tags.description AS stocknumber',
                'bibles.id AS bibleid',
                'bible_filesets.id AS filesetid',
                'bible_filesets.set_type_code AS type',
                'bible_filesets.hash_id AS hash_id',
                'languages.name AS language',
                'bible_translations.name AS version',
                'organization_translations.name AS licensor'
            ])
            ->distinct($select_distinct_columns)
            ->contentAvailable()
            ->whereNotIn('bible_filesets.set_type_code', ['text_format'])
            ->whereRaw('bible_filesets.id NOT LIKE ?', '%DA16')
            ->join(
                $dbp_prod . '.access_group_filesets as agfv',
                function ($join_agfv) use ($download_access_group_array_ids) {
                    $join_agfv
                        ->on('agfv.hash_id', 'bible_filesets.hash_id')
                        ->whereIn('agfv.access_group_id', $download_access_group_array_ids);
                }
            )
            ->join(
                $dbp_users . '.access_group_api_keys as agak',
                function ($join_agak) use ($user_key_id, $download_access_group_array_ids) {
                    $join_agak
                        ->on('agak.access_group_id', 'agfv.access_group_id')
                        ->where('agak.key_id', $user_key_id)
                        ->whereIn('agak.access_group_id', $download_access_group_array_ids);
                }
            )
            ->when($type, function ($query) use ($type) {
                $set_type_code_array = BibleFileset::getsetTypeCodeFromMedia($type);
                $query->whereIn('bible_filesets.set_type_code', $set_type_code_array);
            })
            ->paginate($limit, $select_distinct_columns);
    }
}
