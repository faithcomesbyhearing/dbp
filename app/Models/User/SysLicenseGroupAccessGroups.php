<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\User\SysLicenseGroupAccessGroups
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Sys License Group Access Groups View",
 *     title="SysLicenseGroupAccessGroups",
 *     @OA\Xml(name="SysLicenseGroupAccessGroups")
 * )
 *
 */
class SysLicenseGroupAccessGroups extends Model
{
    protected $connection = 'dbp';
    public $table = 'sys_license_group_access_groups_view';
    public $fillable = [];

    protected $primaryKey = 'access_group';

    /**
     *
     * @OA\Property(
     *   title="lg_id",
     *   type="integer",
     *   description="The id for each access group"
     * )
     *
     * @property integer $lg_id
     */
    protected $lg_id;

    /**
     *
     * @OA\Property(
     *   title="access_group_id",
     *   type="integer",
     *   description="The id for each access group"
     * )
     *
     * @property integer $access_group_id
     */
    protected $access_group_id;
    
    /**
     * @OA\Property(
     *   title="lg_name",
     *   type="string",
     *   description="The license group name for each access group"
     * )
     */
    protected $lg_name;

    /**
     * @OA\Property(
     *   title="path",
     *   type="string",
     *   description="The path for each access group to know if the access group is from permiission pattern or directly from access group"
     * )
     */
    protected $path;

    /**
     * @OA\Property(
     *   title="pp_id",
     *   type="integer",
     *   description="The permission pattern id for each access group"
     * )
     */
    protected $pp_id;

    /**
     * @OA\Property(
     *   title="pp_name",
     *   type="string",
     *   description="The permission pattern name for each access group"
     * )
     */
    protected $pp_name;
}
