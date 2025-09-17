<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Bible\BibleFilesetMode
 *

 * @property string $id
 * @property string $name
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Bible\BibleFilesetConnection $filesetConnection
 * @method static BibleFilesetMode whereCreatedAt($value)
 * @method static BibleFilesetMode whereId($value)
 * @method static BibleFilesetMode whereUpdatedAt($value)
 * @mixin \Eloquent
 *
 *
 * @OA\Schema (
 *     type="object",
 *     required={"filename"},
 *     description="The Bible fileset mode model communicates information about generalized fileset modes",
 *     title="BibleFilesetMode",
 *     @OA\Xml(name="BibleFilesetMode")
 * )
 *
 */
class BibleFilesetSize extends Model
{
    public const AUDIO = 3;
    public const VIDEO = 5;
    public const TEXT = 1;

    protected $connection = 'dbp';
    protected $table = 'bible_fileset_modes';

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
     *   title="name",
     *   type="string",
     *   description="The name",
     *   example="video"
     * )
     *
     *
     * @method static BibleFilesetMode whereSetSizeCode($value)
     * @property string $name
     */
    protected $name;
    protected $created_at;
    protected $updated_at;
}
