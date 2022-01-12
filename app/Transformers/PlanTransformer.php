<?php

namespace App\Transformers;

class PlanTransformer extends BaseTransformer
{
    private $params = [];

    public function __construct($params = [])
    {
        parent::__construct();
        $this->params = $params;
    }

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform($plan)
    {
        return [
            "id"        => $plan->id,
            "name"      => $plan->name,
            "thumbnail" => $plan->thumbnail,
            "featured"  => $plan->featured,
            "suggested_start_date"  => $plan->suggested_start_date,
            "draft"                 => $plan->draft,
            "created_at"            => $plan->created_at,
            "start_date"            => isset($this->params['start_date'])
                ? $this->params['start_date']
                : $this->params['user_plan']->start_date,
            "percentage_completed"  => $this->params['user_plan']->percentage_completed,
            "user"                  => [
                "id"   => $this->params['user']->id,
                "name" => $this->params['user']->name,
            ],
        ];
    }
}
