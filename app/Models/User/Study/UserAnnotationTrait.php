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
        // $fileset_ids = PlaylistItems::select(['fileset_id', 'chapter_start'])
        $items = PlaylistItems::select(['fileset_id', 'chapter_start'])
            ->where('playlist_items.playlist_id', $playlist_id)
            ->where('playlist_items.book_id', $book_id)
            ->groupBy('playlist_items.fileset_id', 'playlist_items.chapter_start')
            ->get();

        $fileset_ids = [];
        $fileset_and_chapters = [];

        foreach ($items as $item) {
            $fileset_ids[$item->fileset_id] = true;
        }

        $bible_ids = BibleFilesetConnection::select('bible_id', 'bf.id as fileset_id')
            ->join('bible_filesets AS bf', 'bible_fileset_connections.hash_id', 'bf.hash_id')
            ->whereIn('bf.id', array_keys($fileset_ids))
            ->groupBy('bible_fileset_connections.bible_id', 'bible_fileset_connections.bible_id')
            ->get();

        $fileset_and_bible = [];

        foreach ($bible_ids as $bible_fileset) {
            $fileset_and_bible[$bible_fileset->fileset_id] = $bible_fileset->bible_id;
        }

        $bible_per_chapter = [];

        foreach ($items as $item) {
            if (isset($fileset_and_bible[$item->fileset_id])) {
                $bible_id = $fileset_and_bible[$item->fileset_id];
                $bible_per_chapter[] = $bible_id.$item->chapter_start;
            }
        }

        return $query
            ->where($this->table.'.book_id', $book_id)
            ->whereIn(\DB::raw("CONCAT($this->table.bible_id, '', $this->table.chapter)"), $bible_per_chapter);
    }
}
