<?php

namespace App\Models\User;

use App\Models\Bible\BibleFileset;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\User\AccessGroup
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Access Group",
 *     title="AccessGroup",
 *     @OA\Xml(name="AccessGroup")
 * )
 *
 */
class AccessGroup extends Model
{
    protected $connection = 'dbp';
    public $table = 'access_groups';
    public $fillable = ['name', 'description'];

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The incrementing id for each access group"
     * )
     *
     * @method static AccessGroup whereId($value)
     * @property integer $name
     */
    protected $id;

    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   description="The name for each access group"
     * )
     *
     * @method static AccessGroup whereName($value)
     * @property string $name
     */
    protected $name;

    /**
     *
     * @OA\Property(
     *   title="description",
     *   type="string",
     *   description="The description for each access group"
     * )
     *
     * @method static AccessGroup whereName($value)
     * @property string $name
     */
    protected $description;

    public function filesets()
    {
        return $this->belongsToMany(BibleFileset::class, 'access_group_filesets', 'hash_id', 'access_group_id', 'id', 'hash_id');
    }

    public function types()
    {
        return $this->belongsToMany(AccessType::class, 'access_group_types');
    }

    public function api()
    {
        return $this->belongsToMany(AccessType::class, 'access_group_types')->whereIn('name', ['text','use-limit-2000','use-limit-200'])->where('allowed', 1);
    }

    public function download()
    {
        return $this->belongsToMany(AccessType::class, 'access_group_types')->whereIn('name', ['download'])->where('allowed', 1);
    }

    public function podcast()
    {
        return $this->belongsToMany(AccessType::class, 'access_group_types')->whereIn('name', ['podcast'])->where('allowed', 1);
    }

    public function user()
    {
        return $this->belongsTo(Key::class);
    }

    public function scopeFindByIdOrName($query, $id)
    {
        return $query->where('id', $id)->orWhere('name', $id);
    }
}
