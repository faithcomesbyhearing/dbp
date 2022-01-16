<?php

namespace App\Transformers;

class PlanDayPlaylistItemsTransformer extends BaseTransformer
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
    public function transform($plan)
    {
        $book_name_indexed_by_id = [];
        return [
            "id" => $plan->id,
            "name" => $plan->name,
            "thumbnail" => $plan->thumbnail,
            "featured" => $plan->featured,
            "suggested_start_date" => $plan->suggested_start_date,
            "draft" => $plan->draft,
            "created_at" => $plan->created_at,
            "updated_at" => $plan->updated_at,
            "start_date" => $plan->start_date,
            "percentage_completed" => $plan->percentage_completed,
            "days" => $plan->days->map(function ($day) use (&$book_name_indexed_by_id) {
                return [
                    "id" => $day->id,
                    "playlist_id" => $day->playlist_id,
                    "completed" => $day->completed,
                    "playlist" => $day->playlist
                        ? [
                            "id" => $day->playlist->id,
                            "name" => $day->playlist->name,
                            "featured" => $day->playlist->featured,
                            "draft" => $day->playlist->draft,
                            "created_at" => $day->playlist->created_at,
                            "updated_at" => $day->playlist->updated_at,
                            "external_content" => $day->playlist->external_content,
                            "following" => $day->playlist->following,
                            "items" => $day->playlist->items->map(function ($item) use (&$book_name_indexed_by_id) {

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
                                    'playlist_id'  => $day->playlist->id,
                                    'v' => $this->params['v'],
                                    'key' => $this->params['key']
                                ]
                            ),
                            "verses" => $day->playlist->verses,
                            "verses" => 0,
                            "user" => [
                                "id" => $day->playlist->user->id,
                                "name" => $day->playlist->user->name
                            ]
                        ]
                    : [],
                ];
            }),
            "user" => [
                "id" => $plan->user->id,
                "name" => $plan->user->name
            ]
        ];
    }
}
