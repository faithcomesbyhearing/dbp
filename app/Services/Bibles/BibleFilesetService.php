<?php

namespace App\Services\Bibles;

use App\Models\Bible\Bible;
use App\Models\Bible\BibleFileset;
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

        $complete_fileset = $valid_filesets->where('set_type_code', $type)->where('set_size_code', 'C')->first();

        if ($complete_fileset) {
            $available_filesets[] = $complete_fileset;
        }

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
}
