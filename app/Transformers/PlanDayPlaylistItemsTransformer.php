<?php

namespace App\Transformers;

class PlanDayPlaylistItemsTransformer extends PlanTransformerBase
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($plan)
    {
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
                "id" => $plan->user->id,
                "name" => $plan->user->name
            ]
        ];
    }
}
