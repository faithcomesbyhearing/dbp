<?php

namespace App\Models\Bible;

use App\Models\Organization\Asset;
use App\Models\Organization\Organization;
use App\Models\User\AccessGroupFileset;
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
        return $this->hasMany(BibleFilesetTag::class, 'hash_id', 'hash_id');
    }

    public function fonts()
    {
        return $this->hasManyThrough(Font::class, BibleFilesetFont::class, 'hash_id', 'id', 'hash_id', 'font_id');
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

    public function scopeUniqueFileset($query, $id = null, $fileset_type = null, $ambigious_fileset_type = false, $testament_filter = null)
    {
        $version = (int) checkParam('v');
        return $query->when($id, function ($query) use ($id, $version) {
            $query->where(function ($query) use ($id, $version) {
                if ($version  <= 2) {
                    $query->where('bible_filesets.id', $id)
                        ->orWhere('bible_filesets.id', substr($id, 0, -4))
                        ->orWhere('bible_filesets.id', 'like', substr($id, 0, 6))
                        ->orWhere('bible_filesets.id', 'like', substr($id, 0, -2) . '%');
                } else {
                    $dbp_database = config('database.connections.dbp.database');
                    $query->where('bible_filesets.id', $id)
                        ->orWhere(function ($query) use ($dbp_database, $id) {
                            $query->whereIn('bible_filesets.hash_id', function ($sub_query) use ($dbp_database, $id) {
                                $sub_query
                                    ->select('hash_id')
                                    ->from($dbp_database . '.bible_fileset_connections')
                                    ->where('bible_id', 'LIKE', $id . '%');
                            });
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
        });
    }

    public function scopeLanguage()
    {
        // return
    }

    public function getArtworkUrlAttribute()
    {
        $storage = \Storage::disk('dbp-web');
        $client = $storage->getDriver()->getAdapter()->getClient();
        $expiry = '+10 minutes';
        $fileset = $this->toArray();
        $url = '';

        if (Str::startsWith($fileset['set_type_code'], 'audio')) {
            $bible_id = optional(BibleFileset::find($fileset['id'])->bible()->first())->id;
            if ($bible_id) {
                $command = $client->getCommand('GetObject', [
                    'Bucket' => config('filesystems.disks.dbp-web.bucket'),
                    'Key'    => "audio/{$bible_id}/{$fileset['id']}/Art/300x300/{$fileset['id']}.jpg"
                ]);

                $url = (string) $client->createPresignedRequest($command, $expiry)->getUri();
            }
        }

        return $url;
    }

    public function getArtworkUrlFromBibleId($bible_id, $isSigned = false)
    {
        $url = '';
        $fileset_id = $this->id ?: $this->generated_id;

        if ($bible_id && $fileset_id) {
            $url = $isSigned === true
                ? $this->getUriFromStorage($bible_id, $fileset_id)
                : "audio/{$bible_id}/{$fileset_id}/Art/300x300/{$fileset_id}.jpg";
        }

        return $url;
    }

    private function getUriFromStorage($bible_id, $fileset_id)
    {
        $storage = \Storage::disk('dbp-web');
        $client = $storage->getDriver()->getAdapter()->getClient();
        $expiry = '+10 minutes';

        $command = $client->getCommand('GetObject', [
            'Bucket' => config('filesystems.disks.dbp-web.bucket'),
            'Key'    => "audio/{$bible_id}/{$fileset_id}/Art/300x300/{$fileset_id}.jpg"
        ]);

        return (string) $client->createPresignedRequest($command, $expiry)->getUri();
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
                    where uk.key = ? and agf.hash_id = bible_filesets.hash_id
            )',
            [$key]
        );
    }

    public static function getsetTypeCodeFromMedia($media)
    {
        $result = [];
        switch ($media) {
            case 'audio':
                $result = [
                    'audio_drama',
                    'audio',
                    'audio_stream',
                    'audio_drama_stream'
                ];
                break;
            case 'video':
                $result = ['video_stream'];
                break;
            case 'text':
                $result = [
                    'text_format',
                    'text_plain',
                    'text_usx'
                ];
                break;
            default:
                break;
        }
        return $result;
    }
}
