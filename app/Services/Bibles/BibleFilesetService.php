<?php

namespace App\Services\Bibles;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\BibleFilesetConnection;
use App\Models\Bible\BibleVerse;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class BibleFilesetService
{
    /**
     * @param Collection $fileset_ids collection of bible fileset that must be validateds
     */
    public static function getValidFilesets(Collection $fileset_ids) : Collection
    {
        return BibleFileset::getConditionTagExcludeByIds($fileset_ids, ['opus', 'webm']);
    }

    /**
     * Retrieve all filesets associated with a specific Bible ID, including the available audio types
     * and access group IDs
     *
     * @param string $bible_id
     * @param \Illuminate\Support\Collection $audio_fileset_types
     * @param \Illuminate\Support\Collection $access_group_ids
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getFilesetsByBibleTypeAndAccessGroup(
        string $bible_id,
        Collection $audio_fileset_types,
        Collection $access_group_ids
    ) : EloquentCollection {
        return BibleFileset::whereHas('bible', function ($query) use ($bible_id) {
            $query->where('bibles.id', $bible_id);
        })
            ->with('meta')
            ->isContentAvailable($access_group_ids)
            ->whereIn('set_type_code', $audio_fileset_types)
            ->get();
    }

    /**
     * Get available filesets given a collection and according to a give type and size
     *
     * @param \Illuminate\Support\Collection $valid_filesets
     * @param string $type
     * @param string $size
     *
     * @return bool|BibleFileset
     */
    public static function getFilesetFromValidFilesets(
        ?\Illuminate\Support\Collection $valid_filesets,
        string $type,
        string $testament
    ) : bool|BibleFileset {
        $available_filesets = [];

        $size_filesets = $valid_filesets->where('set_type_code', $type)->where('set_size_code', $testament)->first();

        if ($size_filesets) {
            $available_filesets[] = $size_filesets;
        }

        $size_partial_filesets = $valid_filesets->filter(function ($fileset) use ($type, $testament) {
            return isset($fileset->set_type_code) &&
                isset($fileset->set_size_code) &&
                is_string($testament) &&
                $fileset->set_type_code === $type &&
                strpos($fileset->set_size_code, $testament) !== false;
        });

        if (!empty($size_partial_filesets)) {
            foreach ($size_partial_filesets as $size_partial_fileset) {
                $available_filesets[] = $size_partial_fileset;
            }
        }

        $partial_fileset = $valid_filesets->filter(function ($fileset) use ($type) {
            return isset($fileset->set_type_code) &&
                isset($fileset->set_size_code) &&
                $fileset->set_type_code === $type &&
                $fileset->set_size_code === 'P';
        })->first();

        if ($partial_fileset) {
            $available_filesets[] = $partial_fileset;
        }

        $complete_fileset = $valid_filesets->where('set_type_code', $type)->where('set_size_code', 'C')->first();

        if ($complete_fileset) {
            $available_filesets[] = $complete_fileset;
        }

        if (!empty($available_filesets)) {
            return collect($available_filesets)->sortBy(function ($fileset) {
                return strpos($fileset['id'], '16') !== false;
            })->first();
        }

        return false;
    }

    /**
     * It returns a string which is the concatenated verse_text property of the Verses that meet the given criteria.
     *
     * @param Bible $bible
     * @param string $fileset_hash_id
     * @param string $book_id
     * @param ?string $verse_start
     * @param ?string $verse_end
     *
     * @return string
     */
    public static function getRangeVersesTextFilterBy(
        Bible $bible,
        string $fileset_hash_id,
        string $book_id,
        ?string $verse_start,
        ?string $verse_end,
        int $chapter
    ) : string {
        return BibleVerse::withVernacularMetaData($bible)
            ->filterByHashIdBookAndChapter($fileset_hash_id, $book_id, $chapter)
            ->when($verse_start, function ($query) use ($verse_start) {
                return $query->where('verse_sequence', '>=', (int) $verse_start);
            })
            ->when($verse_end, function ($query) use ($verse_end) {
                return $query->where('verse_sequence', '<=', (int) $verse_end);
            })
            ->get()
            ->implode('verse_text', ' ');
    }

    /**
     * It returns a string which is the verse_text property of the Verse that meet the given criteria.
     *
     * @param Bible $bible
     * @param string $fileset_hash_id
     * @param string $book_id
     * @param string $verse_start
     *
     * @return string
     */
    public static function getVerseTextFilterBy(
        Bible $bible,
        string $fileset_hash_id,
        string $book_id,
        string $verse_start,
        int $chapter
    ) : string {
        return BibleVerse::withVernacularMetaData($bible)
            ->filterByHashIdBookAndChapter($fileset_hash_id, $book_id, $chapter)
            ->where('verse_start', $verse_start)
            ->get()
            ->implode('verse_text', ' ');
    }


    /**
     * Match filesets with text filesets based on size code.
     *
     * This method iterates over a collection of filesets and matches them with corresponding text filesets
     * from a separate collection. The matching is based on the size code of the filesets. It looks for text
     * filesets whose size code is either identical to or contains the size code of the current fileset.
     *
     * @param \Illuminate\Support\Collection $filesets The collection of filesets to be matched.
     * @param \Illuminate\Support\Collection $text_filesets The collection of text filesets to match against.
     * @param \Illuminate\Support\Collection $bible_hashes A collection mapping fileset hash IDs to bible IDs.
     *
     * @return array An associative array where the keys are fileset IDs and the values are arrays of matching text filesets.
     *
     */
    public static function matchFilesetsByTextSize(
        Collection $filesets,
        Collection $text_filesets,
        Collection $bible_hashes
    ) : Array {
        $fileset_text_info = [];
        foreach ($filesets as $fileset) {
            $bibleId = $bible_hashes[$fileset->hash_id] ?? null;
            if ($bibleId && isset($text_filesets[$bibleId])) {
                foreach ($text_filesets[$bibleId] as $plain_fileset) {
                    // Check if the set size code of the text fileset matches the current fileset's size code
                    // or if the text fileset's size code contains the current fileset's size code.
                    // E.g fileset = NT and text plain = NT OR text plain = NTP
                    if ($plain_fileset->set_size_code === $fileset->set_size_code ||
                        str_contains($plain_fileset->set_size_code, $fileset->set_size_code)
                    ) {
                        $fileset_text_info[$fileset->id][] = $plain_fileset;
                    }
                }
            }
        }

        return $fileset_text_info;
    }

    public static function getFilesetsByIds(Collection $ids) : Collection
    {
        return BibleFileset::select(['hash_id', 'id', 'set_size_code'])
            ->whereIn('id', $ids)
            ->get();
    }

    public static function getTextFilesetsByBibleIds(Collection $bible_ids) : Collection
    {
        return BibleFilesetConnection::join('bible_filesets as f', 'f.hash_id', '=', 'bible_fileset_connections.hash_id')
            ->select(['f.*', 'bible_fileset_connections.bible_id'])
            ->where('f.set_type_code', BibleFileset::TYPE_TEXT_PLAIN)
            ->where('f.content_loaded', true)
            ->where('f.archived', false)
            ->whereIn('bible_fileset_connections.bible_id', $bible_ids)
            ->get()
            ->groupBy('bible_id');
    }

    public static function getBibleFilesetConnectionByHashIds(Collection $hash_ids) : Collection
    {
        return BibleFilesetConnection::select(['hash_id', 'bible_id'])
            ->whereIn('hash_id', $hash_ids)
            ->get();
    }
}
