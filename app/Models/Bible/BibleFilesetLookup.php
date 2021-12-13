<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleFilesetConnection;
use App\Models\Bible\BibleTranslation;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\BibleFilesetTag;
use App\Models\Bible\BibleFileSecondary;
use App\Models\Bible\BibleFile;

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


    public function scopeContentAvailable($query, $key)
    {
        $dbp_users = config('database.connections.dbp_users.database');
        $dbp_prod = config('database.connections.dbp.database');

        $download_access_group_list = config('settings.download_access_group_list');
        $download_access_group_array_ids = explode(',', $download_access_group_list);

        return $query
            ->from($dbp_prod . '.bible_fileset_lookup as bfl')
            ->join(
                $dbp_prod . '.access_group_filesets_view as agfv',
                function ($join_agfv) use ($download_access_group_array_ids) {
                    $join_agfv
                        ->on('agfv.hash_id', 'bfl.hash_id')
                        ->whereIn('agfv.access_group_id', $download_access_group_array_ids);
                }
            )
            ->join(
                $dbp_users . '.access_group_api_keys as agak',
                function ($join_agak) {
                    $join_agak->on('agak.access_group_id', 'agfv.access_group_id');
                }
            )
            ->whereRaw(
                'agak.key_id = (select id from '.$dbp_users.'.user_keys where '.$dbp_users.'.user_keys.key = ?)',
                [$key]
            )
            ->whereNotIn('bfl.type', ['text_format'])
            ->whereRaw('filesetid NOT LIKE ?', '%DA16');
    }
}
