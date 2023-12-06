<?php

namespace App\Transformers;

use App\Models\Bible\BibleFileset;

class PlanTranslateTransformer extends PlanTransformerBase
{
    /**
     * @OA\Schema (
     *   type="object",
     *   schema="v4_plan_translated_detail",
     *   allOf={
     *      @OA\Schema(ref="#/components/schemas/v4_plan_detail"),
     *   },
     *   @OA\Property(
     *      property="translated_percentage",
     *      type="integer",
     *      description="The percentage completed of the plan translated"
     *   ),
     *   @OA\Property(
     *      property="translation_data",
     *      type="array",
     *      @OA\Items(ref="#/components/schemas/BibleFileset")
     *   )
     * )
     *
     *
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
            "language_id"  => $plan->language_id,
            "featured"   => $plan->featured,
            "suggested_start_date" => $plan->suggested_start_date,
            "draft"      => $plan->draft,
            "created_at" => $plan->created_at,
            "updated_at" => $plan->updated_at,
            "start_date" => $plan->start_date,
            "percentage_completed" => (int) $plan->percentage_completed,
            "days" => $plan->days->map(function ($day) {
                $day_result = [
                    "id" => $day->id,
                    "playlist_id" => $day->playlist_id,
                    "completed" => $day->completed
                ];

                if ($this->params['show_details']) {
                    $day_result["playlist"] = $this->parsePlaylistData($day->playlist);
                }

                return $day_result;
            }),
            "user" => [
                "id"   => $this->params['user']->id,
                "name" => $this->params['user']->name,
            ],
            "translation_data" => array_map(function ($item_translations) {
                return $this->parseTranslationData($item_translations, false, false);
            }, $plan->translation_data),
            "translated_percentage" => $plan->translated_percentage
        ];
    }
}
