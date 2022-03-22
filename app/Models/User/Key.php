<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\User\Key
 * @mixin \Eloquent
 *
 * @method static Key whereUserId($value)
 * @property string $user_id
 * @method static Key whereKey($value)
 * @property string $key
 * @method static Key whereName($value)
 * @property string $name
 * @method static Key whereDescription($value)
 * @property string $description
 * @method static Key whereCreatedAt($value)
 * @property \Carbon\Carbon|null $created_at
 * @method static Key whereUpdatedAt($value)
 * @property \Carbon\Carbon|null $updated_at
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Key's model",
 *     title="Key",
 *     @OA\Xml(name="Key")
 * )
 *
 */
class Key extends Model
{
    protected $connection = 'dbp_users';
    protected $table = 'user_keys';
    protected $fillable = ['key','name','description','user_id'];

    /**
     *
     * @OA\Property(ref="#/components/schemas/User/properties/id")
     */
    protected $user_id;
    /**
     *
     * @OA\Property(
     *   title="key",
     *   type="string",
     *   description="The unique generated api key for Key model",
     *   maxLength=64
     * )
     *
     */
    protected $key;
    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   description="The user provided distinctive name to differentiate different keys provided to the same user.",
     *   maxLength=191
     * )
     *
     */
    protected $name;
    /**
     *
     * @OA\Property(
     *   title="description",
     *   type="string",
     *   description="Any additional identifying information about the key provided and it's use can be stored here"
     * )
     *
     */
    protected $description;
    /**
     *
     * @OA\Property(
     *   title="created_at",
     *   type="string",
     *   description="The timestamp at which the key was created at"
     * )
     *
     */
    protected $created_at;
    /**
     *
     * @OA\Property(
     *   title="updated_at",
     *   type="string",
     *   description="The timestamp at which the key was last updated at"
     * )
     *
     */
    protected $updated_at;

    /**
     * @property-read \App\Models\User\User $user
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function access()
    {
        return $this->belongsToMany(AccessGroup::class, config('database.connections.dbp_users.database').'.access_group_api_keys');
    }

    public static function getIdByKey(string $key) : ?int
    {
        return optional(Key::select(['id'])->where('key', $key)->first())->id;
    }
}
