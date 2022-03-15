<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * App\Models\User\AccessGroupFunction
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Access Group Key",
 *     title="AccessGroupKey",
 *     @OA\Xml(name="AccessGroupKey")
 * )
 *
 */
class AccessGroupKey extends Model
{
    protected $connection = 'dbp_users';
    public $table = 'access_group_api_keys';
    public $fillable = ['access_group_id','key_id'];

    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   description="The name for each access group"
     * )
     *
     * @method static AccessGroupKey whereName($value)
     * @property string $access_group_id
     */
    protected $access_group_id;

    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   description="The name for each access group"
     * )
     *
     * @method static AccessGroupKey whereName($value)
     * @property string $key_id
     */
    protected $key_id;

    public function access()
    {
        return $this->belongsTo(AccessGroup::class, 'access_group_id', 'id');
    }

    public function type()
    {
        return $this->hasManyThrough(AccessType::class, AccessGroupType::class, 'id', 'id', 'key_id', 'access_type_id');
    }

    public function user()
    {
        return $this->belongsTo(Key::class);
    }

    public function filesets()
    {
        return $this->hasMany(AccessGroupFileset::class, 'access_group_id', 'access_group_id')->unique();
    }

    /**
     * Get a list of access group ids associated with the api key
     *
     * @param $api_key
     * @return Array
     */
    public static function getAccessGroupIdsByApiKey(string $api_key) : Collection
    {
        return  AccessGroupKey::select('access_group_id')
            ->join('user_keys', function ($join) use ($api_key) {
                $join->on('user_keys.id', '=', 'access_group_api_keys.key_id')
                    ->where('user_keys.key', $api_key);
            })
            ->get()
            ->pluck('access_group_id');
    }
}
