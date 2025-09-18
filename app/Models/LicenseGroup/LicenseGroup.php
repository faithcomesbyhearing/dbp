<?php

namespace App\Models\LicenseGroup;

use App\Models\Bible\BibleFileset;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization\Organization;

/**
 * App\Models\LicenseGroup\LicenseGroup
 * @mixin \Eloquent
 *
 * @method static LicenseGroup whereId($value)
 * @property int $id
 * @method static LicenseGroup whereName($value)
 * @property string $name
 * @method static LicenseGroup wherePermissionPatternId($value)
 * @property int|null $permission_pattern_id
 * @method static LicenseGroup whereDescription($value)
 * @property string $description
 * @method static LicenseGroup whereCreatedAt($value)
 * @property \Carbon\Carbon $created_at
 * @method static LicenseGroup whereUpdatedAt($value)
 * @property \Carbon\Carbon $updated_at
 *
 * @OA\Schema (
 *     type="object",
 *     description="LicenseGroup",
 *     title="License Group",
 *     @OA\Xml(name="LicenseGroup")
 * )
 */
class LicenseGroup extends Model
{
    protected $connection = 'dbp';
    protected $table = 'license_group';

    protected $fillable = [
        'name',
        'permission_pattern_id',
        'description'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    /**
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The license group id"
     * )
     */
    protected $id;

    /**
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   description="The license group name",
     *   maxLength=64
     * )
     */
    protected $name;

    /**
     * @OA\Property(
     *   title="permission_pattern_id",
     *   type="integer",
     *   description="The permission pattern id",
     *   nullable=true
     * )
     */
    protected $permission_pattern_id;

    /**
     * @OA\Property(
     *   title="description",
     *   type="string",
     *   description="The license group description"
     * )
     */
    protected $description;

    /**
     * @OA\Property(
     *   title="copyright",
     *   type="string",
     *   description="The copyright text",
     *   nullable=true
     * )
     */
    protected $copyright;

    /**
     * @OA\Property(
     *   title="is_copyright_combined",
     *   type="boolean",
     *   description="Is this a combined copyright from multiple sources",
     *   nullable=true
     * )
     */
    protected $is_copyright_combined;

    protected $created_at;
    protected $updated_at;

    public function filesets()
    {
        return $this->hasMany(BibleFileset::class, 'license_group_id', 'id');
    }

    public function licensors()
    {
        return $this->hasMany(LicenseGroupLicensor::class, 'license_group_id', 'id');
    }

    public function organizations()
    {
        return $this->hasManyThrough(
            Organization::class,
            LicenseGroupLicensor::class,
            'license_group_id', // Foreign key on LicenseGroupLicensor table...
            'id', // Foreign key on Organization table...
            'id', // Local key on BibleFileset table...
            'organization_id' // Local key on LicenseGroupLicensor table...
        );
    }
}
