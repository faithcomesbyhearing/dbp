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
                return [
                    "id" => $day->id,
                    "playlist_id" => $day->playlist_id,
                    "playlist" => $this->parsePlaylistData($day->playlist),
                    "completed" => $day->completed
                ];
            }),
            "user" => [
                "id" => $plan->user->id,
                "name" => $plan->user->name
            ]
        ];
    }
}
