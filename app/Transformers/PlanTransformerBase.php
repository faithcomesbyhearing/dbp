<?php

namespace App\Transformers;

use App\Models\Playlist\PlaylistItems;
use App\Models\Bible\BibleFileset;

class PlanTransformerBase extends BaseTransformer
{
    protected $params = [];

    public function __construct($params = [])
    {
        parent::__construct();
        $this->params = $params;
    }

    /**
     * Get the book name from a given book id and bible object.
     * But it will try before to fetch the book name from indexed array.
     *
     * @param Array $book_name_indexed_by_id
     * @param Bible $bible
     * @param string $item_book_id
     *
     * @return string
     */
    protected function getBookNameFromItem(&$book_name_indexed_by_id, $bible, $item_book_id)
    {
        if (isset($book_name_indexed_by_id[$item_book_id]) &&
            !is_null($book_name_indexed_by_id[$item_book_id])
        ) {
            return $book_name_indexed_by_id[$item_book_id];
        } else {
            $book_name_indexed_by_id[$item_book_id] = optional(
                $bible->books->where('book_id', $item_book_id)->first()
            )->name;
            return $book_name_indexed_by_id[$item_book_id];
        }
    }

    /**
     * Get the data about the fileset property that belongs to the translation data property
     *
     * @param BibleFileset $fileset
     * @return Array
     */
    public function parseTranslationDataFileset(?BibleFileset $fileset) : Array
    {
        return $fileset ? [
            "id" => $fileset->id,
            "asset_id" => $fileset->asset_id,
            "set_type_code" => $fileset->set_type_code,
            "set_size_code" => $fileset->set_size_code,
            "bitrate" => $fileset->bitrate,
            "codec" => $fileset->codec,
            "container" => $fileset->container,
            "stock_no" => $fileset->stock_no,
            "timing_est_err" => $fileset->timing_est_err,
            "volume" => $fileset->volume,
            "meta" => $fileset->meta
        ] : null;
    }

    /**
     * Get the data structure about the translation data
     *
     * @param Array $item_translations
     * @return Array
     */
    protected function parseTranslationData(Array $item_translations) : Array
    {
        return array_map(function (PlaylistItems $item_translation) use (&$book_name_indexed_by_id) {
            $bible = optional($item_translation->fileset->bible)->first();
            $book_name = $bible
                ? $this->getBookNameFromItem($book_name_indexed_by_id, $bible, $item_translation->book_id)
                : null;

            $bible_translation_item = null;
            $book_name_translation_item = null;
            if (isset($item_translation->translation_item)) {
                $bible_translation_item = optional($item_translation->translation_item->fileset->bible)
                    ->first();
                $book_name_translation_item = $bible_translation_item
                    ? $this->getBookNameFromItem(
                        $book_name_indexed_by_id,
                        $bible_translation_item,
                        $item_translation->translation_item->book_id
                    )
                    : null;
            }

            $result = [
                "id" => $item_translation->id,
                "fileset_id" => $item_translation->fileset_id,
                "book_id" => $item_translation->book_id,
                "chapter_start" => $item_translation->chapter_start,
                "chapter_end" => $item_translation->chapter_end,
                "verse_start" => $item_translation->verse_start,
                "verse_end" => $item_translation->verse_end,
                "verses" => $item_translation->verses,
                "duration" => $item_translation->duration,
                "bible_id" => $bible ? $bible->id : null,
                "fileset" => $this->parseTranslationDataFileset($item_translation->fileset),
                "completed" => $item_translation->completed,
                "full_chapter" => $item_translation->full_chapter,
                "path" => $item_translation->path,
                "metadata" => [
                    "bible_id" => $bible->id,
                    "bible_name" => optional(
                        $bible->translations->where('language_id', $GLOBALS['i18n_id'])->first()
                    )->name,
                    "bible_vname" => optional($bible->vernacularTranslation)->name,
                    "book_name" => $book_name
                ]
            ];

            if (isset($item_translation->translation_item) && !empty($item_translation->translation_item)) {
                $result["translation_item"] = [
                    "id" => $item_translation->translation_item->id,
                    "fileset_id" => $item_translation->translation_item->fileset_id,
                    "book_id" => $item_translation->translation_item->book_id,
                    "chapter_start" => $item_translation->translation_item->chapter_start,
                    "chapter_end" => $item_translation->translation_item->chapter_end,
                    "verse_start" => $item_translation->translation_item->verse_start,
                    "verse_end" => $item_translation->translation_item->verse_end,
                    "verses" => $item_translation->translation_item->verses,
                    "duration" =>
                    $item_translation->translation_item->duration,
                    "completed" => $item_translation->translation_item->completed,
                    "full_chapter" => $item_translation->translation_item->full_chapter,
                    "path" => $item_translation->translation_item->path,
                    "metadata" => [
                        "bible_id" => $bible_translation_item->id,
                        "bible_name" => optional(
                            $bible_translation_item->translations->where(
                                'language_id',
                                $GLOBALS['i18n_id']
                            )->first()
                        )->name,
                        "bible_vname" => optional($bible_translation_item->vernacularTranslation)->name,
                        "book_name" => $book_name_translation_item
                    ]
                ];
            }

            return $result;
        }, $item_translations);
    }
}
