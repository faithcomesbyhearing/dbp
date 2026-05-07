<?php

namespace App\Services\Bibles;

use App\Models\Bible\BibleFile;
use Illuminate\Support\Collection;

class FilesetVerseStartsResolver
{
    private const SEGMENTATION_TYPE_SECTION = 'section';

    /**
     * Filter the in-memory fileset collection down to those that should
     * carry verse_starts: section-segmented audio filesets.
     *
     * Pure in-memory: relies on segmentation_type and set_type_code which
     * are already loaded on every BibleFileset returned by the show query.
     */
    public function qualifyingFilesets(Collection $filesets) : Collection
    {
        return $filesets->filter(function ($fileset) {
            return ($fileset->segmentation_type ?? null) === self::SEGMENTATION_TYPE_SECTION
                && $fileset->isAudio();
        })->values();
    }

    /**
     * Fetch verse_starts data for the qualifying filesets in a single
     * batched query and return a map keyed first by hash_id, then by
     * book_id, so per-book attach lookups are constant-time.
     *
     * @return array<string, array<string, array<int, array{chapter_start: int|null, verse_start: int|null, verse_start_alt: string|null}>>>
     */
    public function resolveForFilesets(Collection $qualifying_filesets) : array
    {
        $hash_ids = $qualifying_filesets->pluck('hash_id')->unique()->values();
        if ($hash_ids->isEmpty()) {
            return [];
        }

        $rows = BibleFile::select(['hash_id', 'book_id', 'chapter_start', 'verse_start', 'verse_sequence'])
            ->whereIn('hash_id', $hash_ids)
            ->orderBy('hash_id')
            ->orderBy('book_id')
            ->orderBy('chapter_start')
            ->orderBy('verse_sequence')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->hash_id][$row->book_id][] = [
                'chapter_start' => $row->chapter_start,
                'verse_start' => $row->verse_sequence,
                'verse_start_alt' => $row->verse_start,
            ];
        }
        return $map;
    }
}
