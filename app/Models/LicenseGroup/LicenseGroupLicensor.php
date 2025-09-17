<?php

namespace App\Models\LicenseGroup;

use App\Models\Bible\BibleFilesetType;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * App\Models\LicenseGroup\LicenseGroupLicensor
 * @mixin \Eloquent
 *
 * @method static LicenseGroupLicensor whereId($value)
 * @property int $id
 * @method static LicenseGroupLicensor whereLicenseGroupId($value)
 * @property int $license_group_id
 * @method static LicenseGroupLicensor whereModeId($value)
 * @property int $mode_id
 * @method static LicenseGroupLicensor whereOrganizationId($value)
 * @property int $organization_id
 * @method static LicenseGroupLicensor whereCreatedAt($value)
 * @property \Carbon\Carbon $created_at
 * @method static LicenseGroupLicensor whereUpdatedAt($value)
 * @property \Carbon\Carbon $updated_at
 *
 * @OA\Schema (
 *     type="object",
 *     description="LicenseGroupLicensor",
 *     title="License Group Licensor",
 *     @OA\Xml(name="LicenseGroupLicensor")
 * )
 */
class LicenseGroupLicensor extends Model
{
    use Compoships;

    protected $connection = 'dbp';
    protected $table = 'license_group_licensor';

    protected $fillable = [
        'license_group_id',
        'organization_id'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    /**
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The license group licensor id"
     * )
     */
    protected $id;

    /**
     * @OA\Property(
     *   title="license_group_id",
     *   type="integer",
     *   description="The license group id"
     * )
     */
    protected $license_group_id;

    /**
     * @OA\Property(
     *   title="organization_id",
     *   type="integer",
     *   description="The organization id"
     * )
     */
    protected $organization_id;

    protected $created_at;
    protected $updated_at;

    public function licenseGroup()
    {
        return $this->belongsTo(LicenseGroup::class, 'license_group_id', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }
}
