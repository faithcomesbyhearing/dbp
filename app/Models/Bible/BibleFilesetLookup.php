<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
use App\Models\User\AccessGroup;

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

    /**
     * Get fileset list available for a user key given
     *
     * @param int $limit
     * @param Collection $group_id_list_by_user_key
     * @param string $type
     *
     * @return LengthAwarePaginator
     */
    public static function getDownloadContentByKey(
        int $limit,
        Collection $access_group_by_user_key,
        string $type = null
    ) : LengthAwarePaginator {

        $download_access_group_array_ids = AccessGroup::select('id')
            ->whereIn('id', $access_group_by_user_key)
            ->whereIn('id', getDownloadAccessGroupList())
            ->get()
            ->pluck('id')
            ->toArray();

        return BibleFileset::select([
            'bible_filesets.id AS filesetid',
            'bible_filesets.set_type_code AS type',
            'languages.name AS language',
            'organization_translations.name AS licensor'
        ])
        ->join(
            'bible_fileset_copyright_organizations',
            'bible_fileset_copyright_organizations.hash_id',
            'bible_filesets.hash_id'
        )->join('bible_fileset_connections', 'bible_fileset_connections.hash_id', 'bible_filesets.hash_id')
        ->join('bibles', 'bibles.id', 'bible_fileset_connections.bible_id')
        ->join('languages', 'languages.id', 'bibles.language_id')
        ->join('organization_translations', function (JoinClause $join) {
            $join->on(
                'organization_translations.organization_id',
                '=',
                'bible_fileset_copyright_organizations.organization_id'
            )
                ->where('organization_translations.language_id', Language::ENGLISH_ID);
        })
        ->where('bible_fileset_copyright_organizations.organization_role', BibleFilesetCopyrightRole::LICENSOR)
        ->when($type, function (Builder $query) use ($type) {
            $query->whereIn('bible_filesets.set_type_code', BibleFileset::getsetTypeCodeFromMedia($type));
        })
        ->whereNotIn('bible_filesets.set_type_code', ['text_format'])
        ->where('bible_filesets.id', 'NOT LIKE', '%DA16')
        ->hasAccessGroup($download_access_group_array_ids)
        ->orderBy('bible_filesets.id')
        ->paginate($limit);
    }
}
