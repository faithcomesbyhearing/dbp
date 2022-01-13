<?php

namespace App\Models\Playlist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Traits\ModelBase;

class PlaylistItemsComplete extends Model
{
    use ModelBase;

    protected $connection = 'dbp_users';
    public $table         = 'playlist_items_completed';
    protected $primaryKey = ['user_id', 'playlist_item_id'];
    protected $fillable   = ['user_id', 'playlist_item_id'];
    public $incrementing  = false;
    public $timestamps = false;

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     *
     * @param mixed $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * Remove the Play List Items completed records that belong to one or more Play lists and an User
     *
     * @param Array $playlist_ids
     * @param int   $user_id
     *
     * @return bool
     */
    public static function removeItemsByPlayListsAndUser(Array $playlist_ids, int $user_id) : bool
    {
        return self::whereExists(function ($sub_query) use ($playlist_ids) {
            return $sub_query->select(\DB::raw(1))
                ->from('playlist_items as pli', 'pli.playlist_id', 'pld.playlist_id')
                ->whereIn('pli.playlist_id', $playlist_ids)
                ->whereColumn('pli.id', '=', 'playlist_items_completed.playlist_item_id');
        })
            ->where('user_id', $user_id)
            ->delete();
    }
}
