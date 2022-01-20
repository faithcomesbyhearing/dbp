<?php

namespace App\Transformers;

class PlaylistTransformer extends BaseTransformer
{
    private $params = [];

    public function __construct($params = [])
    {
        parent::__construct();
        $this->params = $params;
    }

    private function getBookNameFromItem(&$book_name_indexed_by_id, $bible, $item_book_id)
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
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($playlist)
    {
        $book_name_indexed_by_id = [];
        return [
            "id" => $playlist->id,
            "name" => $playlist->name,
            "featured" => $playlist->featured,
            "draft" => $playlist->draft,
            "created_at" => $playlist->created_at,
            "updated_at" => $playlist->updated_at,
            "external_content" => $playlist->external_content,
            "following" => $playlist->following,
            "items" => $playlist->items->map(function ($item) use (&$book_name_indexed_by_id) {

                $bible = optional($item->fileset->bible)->first();
                $book_name = $bible
                    ? $this->getBookNameFromItem($book_name_indexed_by_id, $bible, $item->book_id)
                    : null;

                return [
                    "id" => $item->id,
                    "fileset_id" => $item->fileset_id,
                    "book_id" => $item->book_id,
                    "chapter_start" => $item->chapter_start,
                    "chapter_end" => $item->chapter_end,
                    "verse_start" => $item->verse_start,
                    "verse_end" => $item->verse_end,
                    "verses" => $item->verses,
                    "duration" => $item->duration,
                    "completed" => $item->completed,
                    "bible_id" => $bible ? $bible->id : null,
                    "verse_text" => $item->verse_text,
                    "item_timestamps" => $item->item_timestamps,
                    "full_chapter" => $item->full_chapter,
                    "path" => $item->path,
                    "metadata" => $bible ? [
                        "bible_id" => $bible->id,
                        "bible_name" => optional(
                            $bible->translations->where('language_id', $GLOBALS['i18n_id'])->first()
                        )->name,
                        "bible_vname" => optional($bible->vernacularTranslation)->name,
                        "book_name" => $book_name
                    ] : [],
                ];
            }),
            "path" => route(
                'v4_internal_playlists.hls',
                [
                    'playlist_id'  => $playlist->id,
                    'v' => $this->params['v'],
                    'key' => $this->params['key']
                ]
            ),
            "total_duration" => $playlist->total_duration,
            "translation_data" => array_map(function ($item_translation) use (&$book_name_indexed_by_id) {
                $bible = optional($item_translation->fileset->bible)->first();
                $book_name = $bible
                    ? $this->getBookNameFromItem($book_name_indexed_by_id, $bible, $item_translation->book_id)
                    : null;

                $bible_translation_item = null;
                $book_name_translation_item = null;
                if (isset($item_translation->translation_item)) {
                    $bible_translation_item = optional($item_translation->translation_item->fileset->bible)->first();
                    $book_name_translation_item = $bible_translation_item
                        ? $this->getBookNameFromItem(
                            $book_name_indexed_by_id,
                            $bible_translation_item,
                            $item_translation->translation_item->book_id
                        )
                        : null;
                }

                return [
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
                    "fileset" => [
                        "id" => $item_translation->fileset->id,
                        "asset_id" => $item_translation->fileset->asset_id,
                        "set_type_code" => $item_translation->fileset->set_type_code,
                        "set_size_code" => $item_translation->fileset->set_size_code,
                        "codec" => $item_translation->fileset->codec,
                        "container" => $item_translation->fileset->container,
                        "stock_no" => $item_translation->fileset->stock_no,
                        "timing_est_err" => $item_translation->fileset->timing_est_err,
                        "volume" => $item_translation->fileset->volume,
                        "meta" => $item_translation->fileset->meta
                    ],
                    "translation_item" => $item_translation->translation_item
                        ? [
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
                                    $bible_translation_item->translations->where('language_id', $GLOBALS['i18n_id'])->first()
                                )->name,
                                "bible_vname" => optional($bible_translation_item->vernacularTranslation)->name,
                                "book_name" => $book_name_translation_item
                            ]]
                        : [],
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
            }, $playlist->translation_data),
            "translated_percentage" => $playlist->translated_percentage,
            "verses" => $playlist->verses,
            "user" => [
                "id" => $playlist->user->id,
                "name" => $playlist->user->name
            ],

        ];
    }
}
