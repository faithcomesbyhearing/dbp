<?php

namespace App\Models\Bible;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * App\Models\Bible\BibleFilesetConnection
 *
 * @mixin \Eloquent
 *
 * @property-read Bible $bible
 * @property-read BibleFileset $fileset
 * @property-read BibleFilesetSize $size
 * @property-read BibleFilesetType $type
 *
 * @OA\Schema (
 *     type="object",
 *     description="BibleFilesetConnection",
 *     title="BibleFileset Connection",
 *     @OA\Xml(name="BibleFilesetConnection")
 * )
 *
 */
class BibleFilesetConnection extends Model
{
    protected $connection = 'dbp';
    public $incrementing = false;
    public $keyType = 'string';
    public $primaryKey = 'hash_id';

    protected $hash_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Bible/properties/id")
     * @method static BibleFilesetConnection whereBibleId($value)
     * @property string $bible_id
     */
    protected $bible_id;

    /**
     *
     * @method static BibleFilesetConnection whereCreatedAt($value)
     * @property \Carbon\Carbon $created_at
     */
    protected $created_at;

    /**
     *
     * @method static BibleFilesetConnection whereUpdatedAt($value)
     * @property \Carbon\Carbon $updated_at
     */
    protected $updated_at;

    public function fileset()
    {
        return $this->belongsTo(BibleFileset::class, 'hash_id', 'hash_id');
    }

    public function bible()
    {
        return $this->belongsTo(Bible::class, 'id', 'bible_id');
    }

    public function size()
    {
        return $this->belongsTo(BibleFilesetSize::class);
    }

    public function type()
    {
        return $this->belongsTo(BibleFilesetType::class);
    }

    public function scopeIsContentAvailable(Builder $query, Collection $access_group_ids)
    {
        $sub = BibleFileset::query()
            ->selectRaw('1')
            ->isContentAvailable($access_group_ids)
            ->whereColumn('bible_filesets.hash_id', '=', 'bible_fileset_connections.hash_id');

        return $query->whereExists($sub);
    }
}
