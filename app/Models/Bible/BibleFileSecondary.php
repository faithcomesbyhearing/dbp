<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Bible\BibleFileSecondary
 *
 * @mixin \Eloquent
 * @property-read \App\Models\Bible\BibleFileset $fileset

 *
 * @method static BibleFileSecondary whereHashId($value)
 * @property $hash_id
 * @method static BibleFile whereFileName($value)
 * @property $fileName
 * @method static BibleFile whereFileType($value)
 * @property $fileType

 *
 * @OA\Schema (
 *     type="object",
 *     required={"filename"},
 *     description="The Bible File Secondary Model communicates information additional files stored on S3",
 *     title="BibleFileSecondary",
 *     @OA\Xml(name="BibleFileSecondary")
 * )
 *
 */
class BibleFileSecondary extends Model
{
    protected $connection = 'dbp';
    protected $table = 'bible_files_secondary';
    protected $hidden = ['created_at','updated_at'];

    /**
     *
     * @OA\Property(
     *   title="hash_id",
     *   type="string",
     *   example="ac222eb840de",
     *   description="The fileset hash id",
     * )
     *
     */
    protected $hash_id;

    /**
     *
     * @OA\Property(
     *   title="file_name",
     *   type="string",
     *   example="Art/125x125/INDSHLO1DA.jpg",
     *   description="The secondary files for fileset",
     * )
     *
     */
    protected $file_name;

    /**
     *
     * @OA\Property(
     *   title="file_type",
     *   type="string",
     *   description="The file type",
     *   example="art"
     * )
     *
     */
    protected $file_type;

    public function fileset()
    {
        return $this->belongsTo(BibleFileset::class, 'hash_id', 'hash_id');
    }
}
