<?php

namespace App\Transformers;

class PlaylistItemsTransformer extends PlanTransformerBase
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($playlist_item)
    {
        $bible = optional(optional($playlist_item->fileset)->bible)->first();
        $book_name = $bible
            ? $this->getBookNameFromItem($bible, $playlist_item->book_id)
            : null;

        return [
            "id" => $playlist_item->id,
            "fileset_id" => $playlist_item->fileset_id,
            "book_id" => $playlist_item->book_id,
            "chapter_start" => $playlist_item->chapter_start,
            "chapter_end" => $playlist_item->chapter_end,
            "verse_start" => $playlist_item->verse_sequence,
            "verse_start_alt" => $playlist_item->verse_start,
            "verse_end" => $playlist_item->verse_end,
            "verses" => $playlist_item->verses,
            "duration" => $playlist_item->duration,
            "completed" => $playlist_item->completed,
            "full_chapter" => $playlist_item->full_chapter,
            "path" => $playlist_item->path,
            "metadata" => $bible ? [
                "bible_id" => $bible->id,
                "bible_name" => optional(
                    $bible->translations->where('language_id', $GLOBALS['i18n_id'])->first()
                )->name,
                "bible_vname" => optional($bible->vernacularTranslation)->name,
                "book_name" => $book_name
            ] : null,
        ];
    }
}
