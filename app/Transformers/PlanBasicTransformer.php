<?php

namespace App\Transformers;

class PlanBasicTransformer extends PlanTransformerBase
{
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
            "start_date" => $plan->start_date,
            "percentage_completed" => (int) $plan->percentage_completed,
            "user" => [
                "id"   => $this->params['user']->id,
                "name" => $this->params['user']->name,
            ]
        ];
    }
}
