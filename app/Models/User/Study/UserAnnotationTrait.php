<?php

namespace App\Models\User\Study;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use App\Models\Playlist\PlaylistItems;
use App\Models\Bible\BibleFilesetConnection;

trait UserAnnotationTrait
{
    /**
     * Get Column name list of the user_notes entity
     *
     * @return Collection
     */
    public static function getColumnListing() : Collection
    {
        $tableName = (new static)->getTable();
        return collect(Schema::connection('dbp_users')->getColumnListing($tableName))
            ->mapWithKeys(function ($item) {
                return [$item => true];
            });
    }

    /**
     * Scope a query to only include playlist items that belong to a specific playlist and book.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int $playlist_id
     * @param  string $book_id
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereBelongPlaylistAndBook(Builder $query, int $playlist_id, string $book_id) : Builder
    {
        // Select the fileset_id and chapter_start for the playlist items that belong to
        // the specified playlist and book.
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

        // Select the bible_id and fileset_id for the bible fileset connections that match the fileset_ids.
        $bible_ids = BibleFilesetConnection::select('bible_id', 'bf.id as fileset_id')
            ->join('bible_filesets AS bf', 'bible_fileset_connections.hash_id', 'bf.hash_id')
            ->whereIn('bf.id', array_keys($fileset_ids))
            ->groupBy('bible_fileset_connections.bible_id', 'bible_fileset_connections.bible_id')
            ->get();

        // Create an array of fileset_ids and their corresponding bible_ids.
        $fileset_and_bible = [];
        foreach ($bible_ids as $bible_fileset) {
            $fileset_and_bible[$bible_fileset->fileset_id] = $bible_fileset->bible_id;
        }

        // Create an array of bible_ids and chapter_starts for the playlist items that have a corresponding
        // bible fileset connection.
        $bible_per_chapter = [];
        foreach ($items as $item) {
            if (isset($fileset_and_bible[$item->fileset_id])) {
                $bible_id = $fileset_and_bible[$item->fileset_id];
                $bible_per_chapter[] = $bible_id.$item->chapter_start;
            }
        }

        // Return the query builder with additional where clauses for the book_id and the bible_id and
        // chapter combination.
        return $query
            ->where($this->table.'.book_id', $book_id)
            ->whereIn(\DB::raw("CONCAT($this->table.bible_id, '', $this->table.chapter)"), $bible_per_chapter);
    }
}
