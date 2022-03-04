<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

class JesusFilmChapterTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @param $joshuaProject
     *
     * @return array
     */
    public function transform($film)
    {
        return [
            'component_id' => $film['component_id'],
            'verses' => $film['verses'],
            'meta' => $film['meta']
        ];
    }
}
