<?php

namespace App\Transformers;

class PlanTranslateTransformer extends BaseTransformer
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
        return [
            "id"         => $plan->id,
            "name"       => $plan->name,
            "thumbnail"  => $plan->thumbnail,
            "featured"   => $plan->featured,
            "suggested_start_date" => $plan->suggested_start_date,
            "draft"      => $plan->draft,
            "created_at" => $plan->created_at,
            "updated_at" => $plan->updated_at,
            "start_date" => $plan->start_date,
            "percentage_completed" => (int) $plan->percentage_completed,
            "days" => $plan->days->map(function ($day) {
                return [
                    "id"          => $day->id,
                    "playlist_id" => $day->playlist_id,
                    "completed"   => (bool) $day->completed
                ];
            }),
            "user" => [
                "id"   => $this->params['user']->id,
                "name" => $this->params['user']->name,
            ],
            "translation_data" => array_map(function ($item_translations) use (&$book_name_indexed_by_id) {
                return array_map(function ($item_translation) use (&$book_name_indexed_by_id) {
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
                                        $bible_translation_item->translations->where(
                                            'language_id',
                                            $GLOBALS['i18n_id']
                                        )->first()
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
                }, $item_translations);
            }, $plan->translation_data),
            "translated_percentage" => $plan->translated_percentage
        ];
    }
}
