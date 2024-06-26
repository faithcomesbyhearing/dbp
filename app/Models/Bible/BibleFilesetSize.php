<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Bible\BibleFilesetSize
 *

 * @property string $set_size_code
 * @property string $name
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Bible\BibleFilesetConnection $filesetConnection
 * @method static BibleFilesetSize whereCreatedAt($value)
 * @method static BibleFilesetSize whereId($value)
 * @method static BibleFilesetSize whereUpdatedAt($value)
 * @mixin \Eloquent
 *
 *
 * @OA\Schema (
 *     type="object",
 *     required={"filename"},
 *     description="The Bible fileset size model communicates information about generalized fileset sizes",
 *     title="BibleFilesetSize",
 *     @OA\Xml(name="BibleFilesetSize")
 * )
 *
 */
class BibleFilesetSize extends Model
{
    public const SIZE_COMPLETE = 'C';
    public const SIZE_NEW_TESTAMENT = 'NT';
    public const SIZE_NEW_TESTAMENT_OLD_TESTAMENT_PORTION = 'NTOTP';
    public const SIZE_NEW_TESTAMENT_PORTION = 'NTP';
    public const SIZE_NEW_TESTAMENT_PORTION_OLD_TESTAMENT_PORTION = 'NTPOTP';
    public const SIZE_OLD_TESTAMENT = 'OT';
    public const SIZE_OLD_TESTAMENT_NEW_TESTAMENT_PORTION = 'OTNTP';
    public const SIZE_OLD_TESTAMENT_PORTION = 'OTP';
    public const SIZE_PORTION = 'P';
    public const SIZE_STORIES = 'S';

    protected $connection = 'dbp';
    protected $table = 'bible_fileset_sizes';

    /**
     *
     * @OA\Property(
     *   title="id",
     *   type="integer",
     *   description="The id",
     *   minimum=0,
     *   example=4
     * )
     *
     * @method static BibleFilesetSize whereId($value)
     * @property int $id
     */
    protected $id;

    /**
     *
     * @OA\Property(
     *   title="set_size_code",
     *   type="string",
     *   description="The id",
     *   maxLength=9,
     *   example="NTPOTP"
     * )
     *
     *
     * @method static BibleFilesetSize whereSetSizeCode($value)
     * property string $set_size_code
     */
    protected $set_size_code;

    /**
     *
     * @OA\Property(
     *   title="name",
     *   type="string",
     *   description="The name",
     *   maxLength=191,
     *   example="New Testament & Old Testament Portions"
     * )
     *
     * @method static BibleFilesetSize whereName($value)
     * property string $name
     */
    protected $name;

    public function filesetConnection()
    {
        return $this->hasOne(BibleFilesetConnection::class);
    }
}
