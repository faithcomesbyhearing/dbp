<?php

namespace App\Transformers;

class PlaylistTransformer extends PlanTransformerBase
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($playlist)
    {
        $result = [
            "id" => $playlist->id,
            "name" => $playlist->name,
            "featured" => $playlist->featured,
            "draft" => $playlist->draft,
            "created_at" => $playlist->created_at,
            "updated_at" => $playlist->updated_at,
            "external_content" => $playlist->external_content,
            "following" => $playlist->following,
            "items" => $playlist->relationLoaded('items') ? $playlist->items->map(function ($item) {
                $bible = optional(optional($item->fileset)->bible)->first();
                $book_name = $bible
                    ? $this->getBookNameFromItem($bible, $item->book_id)
                    : null;

                $result_item = [
                    "id" => $item->id,
                    "fileset_id" => $item->fileset_id,
                    "book_id" => $item->book_id,
                    "chapter_start" => $item->chapter_start,
                    "chapter_end" => $item->chapter_end,
                    "verse_start" => $item->verse_sequence,
                    "verse_start_alt" => $item->verse_start,
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
                    ] : null,
                ];

                if (!isset($item->verse_text)) {
                    unset($result_item["verse_text"]);
                }

                if (!isset($item->item_timestamps)) {
                    unset($result_item["item_timestamps"]);
                }

                return $result_item;
            }) : [],
            "path" => route(
                'v4_internal_playlists.hls',
                [
                    'playlist_id'  => $playlist->id,
                    'v' => $this->params['v'],
                    'key' => $this->params['key']
                ]
            ),
            "total_duration" => $playlist->total_duration,
            "verses" => $playlist->verses,
            "user" => [
                "id" => $playlist->user ? $playlist->user->id : null,
                "name" => $playlist->user ? $playlist->user->name : null
            ],

        ];

        if (isset($playlist->translation_data) && !empty($playlist->translation_data)) {
            $result["translation_data"] = $this->parseTranslationData($playlist->translation_data);
        }

        if (isset($playlist->translated_percentage) && !empty($playlist->translated_percentage)) {
            $result["translated_percentage"] = $playlist->translated_percentage;
        }

        if (!$playlist->relationLoaded('items')) {
            unset($result["items"]);
        }

        return $result;
    }
}
