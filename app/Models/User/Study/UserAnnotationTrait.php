<?php

namespace App\Models\User\Study;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use App\Models\Playlist\PlaylistItems;

use App\Models\Bible\BibleFilesetConnection;

trait UserAnnotationTrait
{
    /**
     * Get bookmarks related the playlist items that belong to playlist and a given book ID
     *
     * @param Illuminate\Database\Query\Builder $query
     * @param int $playlist_id
     * @param string $book_id
     *
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeWhereBelongPlaylistAndBook(Builder $query, int $playlist_id, string $book_id) : Builder
    {
        // $dbp_users = config('database.connections.dbp_users.database');
        // $dbp_prod = config('database.connections.dbp.database');

        // return $query
        //     ->where($this->table.'.book_id', $book_id)
        //     ->whereExists(function (QueryBuilder $subquery) use ($dbp_users, $dbp_prod, $book_id, $playlist_id) {
        //         return $subquery->select(\DB::raw(1))
        //             ->from($dbp_prod . '.bible_fileset_connections AS bfc')
        //             ->join($dbp_prod . '.bible_filesets AS bf', 'bfc.hash_id', 'bf.hash_id')
        //             ->join($dbp_users . '.playlist_items', function (QueryBuilder $join) use ($book_id) {
        //                 $join
        //                     ->on('bf.id', '=', 'playlist_items.fileset_id')
        //                     ->where('playlist_items.book_id', $book_id)
        //                     ->whereColumn($this->table.'.chapter', '=', 'playlist_items.chapter_start')
        //                     ->whereColumn($this->table.'.bible_id', '=', 'bfc.bible_id');
        //             })
        //             ->where('playlist_items.playlist_id', $playlist_id);
        //     });
        $fileset_ids = PlaylistItems::select('fileset_id')
            ->where('playlist_items.playlist_id', $playlist_id)
            ->where('playlist_items.book_id', $book_id)
            ->groupBy('playlist_items.fileset_id')
            ->get()
            ->pluck('fileset_id');

        $bible_ids = BibleFilesetConnection::select('bible_id')
            ->join('bible_filesets AS bf', 'bible_fileset_connections.hash_id', 'bf.hash_id')
            ->whereIn('bf.id', $fileset_ids)
            ->groupBy('bible_fileset_connections.bible_id')
            ->get()
            ->pluck('bible_id');

        return $query
            ->where($this->table.'.book_id', $book_id)
            ->whereIn($this->table.'.bible_id', $bible_ids);
    }
}
