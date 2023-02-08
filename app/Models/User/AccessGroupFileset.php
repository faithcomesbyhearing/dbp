<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Models\Bible\BibleFileset;

/**
 * App\Models\User\AccessGroupFileset
 * @mixin \Eloquent
 *
 * @OA\Schema (
 *     type="object",
 *     description="The Access Group Fileset",
 *     title="AccessGroupFileset",
 *     @OA\Xml(name="AccessGroupFileset")
 * )
 *
 */
class AccessGroupFileset extends Model
{
    protected $connection = 'dbp';
    public $table = 'access_group_filesets';
    public $hidden = ['access_group_id'];
    public $fillable = ['hash_id','access_group_id'];


    /**
     *
     * @OA\Property(ref="#/components/schemas/AccessGroup/properties/id")
     *
     * @method static AccessGroupFileset whereName($value)
     * @property string $access_group_id
     */
    protected $access_group_id;

    /**
     *
     * @OA\Property(ref="#/components/schemas/BibleFileset/properties/id")
     *
     * @method static AccessGroupFileset whereHashId($value)
     * @property string $hash_id
     */
    protected $hash_id;

    public function access()
    {
        return $this->belongsTo(AccessGroup::class, 'access_group_id');
    }

    /**
     * Validate if Language or Bible has bible content available
     *
     * @param Builder $query
     * @param string $where_column
     *
     * @return Builder
     */
    public function scopeDoesLanguageHaveBibleContentAvailable(
        Builder $query,
        string $where_column,
        Collection $access_group_ids
    ) {
        return $query->from('access_group_filesets as agf')
            ->select(\DB::raw(1))
            ->join('bible_fileset_connections as bfc', 'agf.hash_id', 'bfc.hash_id')
            ->join('bibles as b', 'bfc.bible_id', 'b.id')
            // ->whereColumn('lang.id', '=', 'b.language_id')
            ->whereColumn($where_column, '=', 'b.language_id')
            ->whereIn('agf.access_group_id', $access_group_ids);
    }

    /**
     * Filter records by media value using set_type_code column that belongs to the bible fileset relationship
     *
     * @param Builder $query
     * @param string $media
     *
     * @return Builder
     */
    public function scopeFilterByMediaFileset(Builder $query, string $media) : Builder
    {
        if (!$this->hasBibleFilesetsJoin($query)) {
            $query->join('bible_filesets as bfst', 'bfst.hash_id', 'bfc.hash_id');
        }

        $set_type_code_array = BibleFileset::getsetTypeCodeFromMedia($media);
        return $query->whereIn('bfst.set_type_code', $set_type_code_array);
    }

    /**
     * Filter records by set_type_code column that belongs to the bible fileset relationship
     *
     * @param Builder $query
     * @param string $set_type_code
     *
     * @return Builder
     */
    public function scopeFilterBySetTypeCodeFileset(Builder $query, string $set_type_code) : Builder
    {
        if (!$this->hasBibleFilesetsJoin($query)) {
            $query->join('bible_filesets as bfst', 'bfst.hash_id', 'bfc.hash_id');
        }

        return $query->where('bfst.set_type_code', $set_type_code);
    }

    public function hasBibleFilesetsJoin(Builder $query) : bool
    {
        $joins = $query->getQuery()->joins;

        if (is_null($joins)) {
            return false;
        }

        $fileset_table_name = with(new BibleFileset)->getTable();
        $fileset_table_name_alias = "$fileset_table_name as bfst";

        foreach ($joins as $join_clause) {
            if ($join_clause->table === $fileset_table_name ||
                $join_clause->table === $fileset_table_name_alias
            ) {
                return true;
            }
        }

        return false;
    }
}
