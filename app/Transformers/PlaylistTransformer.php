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
                    "bible_id" => $bible ? $bible->id : null,
                    "completed" => $item->completed,
                    "full_chapter" => $item->full_chapter,
                    "path" => $item->path,
                    "item_timestamps" => $item->item_timestamps,
                    "verse_text" => $item->verse_text,
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
            "verses" => $playlist->verses,
            "verses" => 0,
            "user" => [
                "id" => $playlist->user->id,
                "name" => $playlist->user->name
            ]
        ];
    }
}
