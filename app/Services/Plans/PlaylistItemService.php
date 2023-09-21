<?php

namespace App\Services\Plans;

use Illuminate\Database\Eloquent\Collection;
use App\Models\Bible\BibleFile;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleVerse;
use App\Models\Bible\StreamBandwidth;
use App\Models\Playlist\PlaylistItems;

class PlaylistItemService 
{
    /**
     * Get the bible filesets attached to the fileset attached to each palylist item
     *
     * @param array $playlist_items_to_create
     *
     * @return Collection list filesets indexed by ID
     */
    public function getBibleFilesetsFromPlaylistItems(array $playlist_items_to_create) : Collection
    {
        $fileset_ids = [];

        foreach ($playlist_items_to_create as $item_to_create) {
            $fileset_ids[$item_to_create['fileset_id']] = true;
        }

        return BibleFileset::select(['id', 'hash_id', 'set_type_code'])
            ->whereIn('id', array_keys($fileset_ids))
            ->get()
            ->keyBy('id');
    }

    /**
     * Get the bible files attached to the fileset attached to each palylist item
     *
     * @param array $playlist_items_to_create
     * @param Collection $bible_filesets - bible fileset collection indexed by filese ID
     *
     * @return array
     */
    public function getBibleFilesFromPlaylistItems(
        array $playlist_items_to_create,
        Collection $bible_filesets
    ) : array|Collection {
        $bible_files = BibleFile::select(['id', 'hash_id', 'book_id', 'chapter_start', 'duration'])
            ->with('streamBandwidth.transportStreamTS')
            ->with('streamBandwidth.transportStreamBytes');

        $flag_constraint = false;
        foreach ($playlist_items_to_create as $item_to_create) {
            $bible_files->orWhere(
                function ($query_or_duration) use ($item_to_create, $bible_filesets, &$flag_constraint) {
                    if (isset($bible_filesets[$item_to_create['fileset_id']])) {
                        $flag_constraint = true;
                        $query_or_duration
                            ->where('chapter_start', '>=', $item_to_create['chapter_start'])
                            ->where('chapter_start', '<=', $item_to_create['chapter_end'])
                            ->where('hash_id', $bible_filesets[$item_to_create['fileset_id']]->hash_id)
                            ->where('book_id', $item_to_create['book_id']);
                    }
                }
            );
        }

        return $flag_constraint ? $bible_files->get() : [];
    }

    /**
     * Filter the bible files array according to the chapter values of the playlist item
     *
     * @param array $bible_files
     * @param array $item_to_create
     *
     * @return array
     */
    public function filterBibleFilesByChapter(array $bible_files, array $item_to_create) : ?array
    {
        return array_filter(
            $bible_files,
            function ($bible_file) use ($item_to_create) {
                return $bible_file->chapter_start >= $item_to_create['chapter_start'] &&
                    $bible_file->chapter_start <= $item_to_create['chapter_end'];
            }
        );
    }

    /**
     * Get transportStream from a bible file
     *
     * @param StreamBandwidth $current_band_width
     * @param array $item_to_create
     * @param BibleFile $bible_file
     *
     * @return Collection | array The processed transport stream.
     */
    public function getTransportStreamFromBibleFile(
        StreamBandwidth $current_band_width,
        array $item_to_create,
        ?BibleFile $bible_file
    ) : Collection | array {
        $transportStream = sizeof($current_band_width->transportStreamBytes)
            ? $current_band_width->transportStreamBytes
            : $current_band_width->transportStreamTS;

        if ($item_to_create['verse_end'] && $item_to_create['verse_start']) {
            $transportStream = PlaylistItems::processVersesOnTransportStream(
                $item_to_create['chapter_start'],
                $item_to_create['chapter_end'],
                (int) $item_to_create['verse_start'],
                (int) $item_to_create['verse_end'],
                $transportStream,
                $bible_file
            );
        }

        return $transportStream;
    }

    /**
     * Update the duration property for each playlist item
     * @param array &$playlist_items_to_create
     *
     * @return void
     */
    public function calculateDurationForPlaylistItems(array &$playlist_items_to_create) : void
    {
        $bible_filesets = $this->getBibleFilesetsFromPlaylistItems($playlist_items_to_create);
        $bible_files = $this->getBibleFilesFromPlaylistItems($playlist_items_to_create, $bible_filesets);

        $bible_files_indexed = [];

        foreach ($bible_files as $bible_file) {
            $bible_files_indexed[$bible_file->hash_id][$bible_file->book_id][] = $bible_file;
        }

        foreach ($playlist_items_to_create as &$item_to_create) {
            $bible_fileset_attached = $bible_filesets[$item_to_create['fileset_id']];

            $duration = null;

            if (isset($bible_files_indexed[$bible_fileset_attached->hash_id][$item_to_create['book_id']])) {
                $hash_id = $bible_fileset_attached->hash_id;

                $bible_files_to_perform_filtered = $this->filterBibleFilesByChapter(
                    $bible_files_indexed[$hash_id][$item_to_create['book_id']],
                    $item_to_create
                );
                $duration = 0;

                if ($bible_fileset_attached->set_type_code === 'audio_stream' ||
                    $bible_fileset_attached->set_type_code === 'audio_drama_stream'
                ) {
                    foreach ($bible_files_to_perform_filtered as $bible_file) {
                        $currentBandwidth = $bible_file->streamBandwidth->first();
                        $transportStream = $this->getTransportStreamFromBibleFile(
                            $currentBandwidth,
                            $item_to_create,
                            $bible_file
                        );

                        foreach ($transportStream as $stream) {
                            $duration += $stream->runtime;
                        }
                    }
                } else {
                    foreach ($bible_files_to_perform_filtered as $bible_file) {
                        $duration += $bible_file->duration ?? 180;
                    }
                }
            }
            $item_to_create['duration'] = $duration;
        }

        unset($item_to_create);
    }

    /**
     * Update the verses property for each playlist item
     * @param array &$playlist_items_to_create
     * @param Collection $bible_filesets
     *
     * @return void
     */
    public function calculateVersesForPlaylistItems(array &$playlist_items_to_create, Collection $bible_filesets) : void
    {
        $book_ids = [];
        foreach ($playlist_items_to_create as $item_to_create) {
            $book_ids[$item_to_create['book_id']] = true;
        }

        $bible_verses = BibleVerse::select([\DB::raw('COUNT(id) as verse_count'), 'book_id', 'chapter'])
            ->whereIn('hash_id', $bible_filesets->pluck('hash_id'))
            ->whereIn('book_id', array_keys($book_ids))
            ->groupBy(['book_id', 'chapter'])
            ->get();

        $bible_verses_indexed = [];

        foreach ($bible_verses as $bible_verse) {
            $bible_verses_indexed[$bible_verse->book_id][(int)$bible_verse->chapter] = $bible_verse->verse_count;
        }

        foreach ($playlist_items_to_create as &$item_to_create) {
            $verses = null;
            if (isset($bible_verses_indexed[$item_to_create['book_id']])) {
                $verses = 0;
                foreach ($bible_verses_indexed[$item_to_create['book_id']] as $chapter => $verse_count) {
                    if ((int) $chapter >= $item_to_create['chapter_start'] &&
                        (int) $chapter <= $item_to_create['chapter_end']
                    ) {
                        $verses = $verses + (int) $verse_count;
                    }
                }
            }
            $item_to_create['verses'] = $verses;
        }

        unset($item_to_create);
    }
}
