<?php

namespace App\Models\Bible;

use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * App\Models\Bible\BibleFilesetTag
 *
 * @property-read \App\Models\Bible\BibleFileset $fileset
 * @property-read \App\Models\Organization\Organization $organization
 * @mixin \Eloquent
 * @property string $set_id
 * @property string $hash_id
 *
 * @OA\Schema (
 *     type="object",
 *     required={"filename"},
 *     description="The Bible fileset tag model communicates general metadata about the filesets",
 *     title="BibleFilesetSize",
 *     @OA\Xml(name="BibleFilesetSize")
 * )
 *
 */
class BibleFileTag extends Model
{
    const TAG_THUMBNAIL = 'thumbnail';
    const TAG_YOUTUBE_VIDEO = 'youtube_video_id';
    const TAG_YOUTUBE_PLAYLIST = 'youtube_playlist_id';

    protected $connection = 'dbp';
    public $table = 'bible_file_tags';
    public $primaryKey = 'file_id';
    public $incrementing = false;
    protected $hidden = ['created_at', 'updated_at','admin_only'];
    protected $fillable = ['tag','value'];

    /**
     *
     * @OA\Property(
     *   title="tag",
     *   type="string",
     *   description="The name of the tag, serves as the key/category",
     *   example="bitrate",
     *   maxLength=191
     * )
     *
     * @method static BibleFileTags whereTag($value)
     * @property string $tag
     *
     */
    protected $tag;

    /**
     *
     * @OA\Property(
     *   title="value",
     *   type="string",
     *   example="gf_jhn_01_02.jpg",
     *   description="The content of the tag, serves as the value of the key value"
     * )
     *
     * @method static BibleFileTags whereValue($value)
     * @property string $value
     *
     */
    protected $value;

    /**
     *
     * @OA\Property(
     *   title="admin_only",
     *   type="boolean",
     *   description="If the tag is only to be visible to admin / archivist users"
     * )
     *
     * @method static BibleFileTags whereAdminOnly($value)
     * @property string $admin_only
     *
     */
    protected $admin_only;

    /**
     *
     * @OA\Property(
     *   title="file_id",
     *   type="string",
     *   description="Reference to file entity"
     * )
     *
     * @method static BibleFileTags whereFileId($value)
     * @property string $file_id
     *
     */
    protected $file_id;

    public function file()
    {
        return $this->belongsTo(BibleFile::class);
    }

    public function scopeJoinBibleFile(Builder $query)
    {
        return $query->join('bible_files', 'bible_files.id', 'bible_file_tags.file_id');
    }

    /**
     * Get list of thumbnails values related to a bible file and given hash ids, chapter list and verse list
     *
     * @param Array $hash_ids
     * @param Array $chapters
     * @param Array $verses
     *
     * @return Collection
     */
    public static function getThumbnailsByHashChapterAnVerse(
        Array $hash_ids,
        Array $chapters,
        Array $verses
    ) : Collection {
        return self::select(
            'bible_file_tags.value',
            'bible_files.chapter_start',
            'bible_files.verse_start',
            'bible_files.hash_id',
            'bible_files.book_id',
        )
            ->joinBibleFile()
            ->whereIn('bible_files.hash_id', $hash_ids)
            ->whereIn('bible_files.chapter_start', $chapters)
            ->whereIn('bible_files.verse_start', $verses)
            ->where('bible_file_tags.tag', self::TAG_THUMBNAIL)
            ->get();
    }
}
